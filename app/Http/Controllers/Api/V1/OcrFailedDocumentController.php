<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOcrFailedDocumentRequest;
use App\Models\OcrFailedDocument;
use App\Services\ImageService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;

class OcrFailedDocumentController extends Controller
{
    public function __construct(private readonly ImageService $images)
    {
    }

    /**
     * POST /v1/ocr/failed-documents — a tablet reports ONE document it could not
     * read, front and back together in a single request → a single queue row.
     *
     * The station is taken from the X-API-Key, never from the body. Accepts JSON
     * or multipart. Only the FRONT confidence gates acceptance: the back has no
     * templates yet so it always scores low and is ignored for the threshold.
     */
    public function store(StoreOcrFailedDocumentRequest $request): JsonResponse
    {
        $station = $request->attributes->get('station');
        $data    = $request->validated();

        $min             = (float) config('ocr.min_front_confidence', 0.20);
        $max             = (float) config('ocr.max_front_confidence', 0.40);
        $frontConfidence = (float) $data['front_confidence'];

        // Only reports inside [min, max) are worth a human's time: below is
        // unusable noise, at or above it the app already recognized the
        // document. Return 200 (not 4xx) either way so the device treats it as
        // handled and does not retry — the report is simply dropped.
        $reason = match (true) {
            $frontConfidence < $min  => 'front_confidence_below_threshold',
            $frontConfidence >= $max => 'front_confidence_above_threshold',
            default                  => null,
        };

        if ($reason !== null) {
            AuditLogger::log('tablet.ocr.failed_document_skipped', $request, [
                'station_id'       => $station ? (string) $station->id : null,
                'station_code'     => $station ? (string) $station->code : null,
                'detected_type'    => $data['detected_type'] ?? null,
                'front_confidence' => $frontConfidence,
                'reason'           => $reason,
                'min_confidence'   => $min,
                'max_confidence'   => $max,
                'app_version'      => $data['app_version'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'stored'           => false,
                    'reason'           => $reason,
                    'front_confidence' => $frontConfidence,
                    'min_confidence'   => $min,
                    'max_confidence'   => $max,
                ],
                'message' => 'Report received but not queued: front confidence outside the review band.',
            ], 200);
        }

        $failed = OcrFailedDocument::create([
            'station_id'          => $station?->id,
            'detected_type'       => $data['detected_type'] ?? null,
            'detected_confidence' => $frontConfidence,
            'match_score'         => $data['match_score'] ?? null,
            'ocr_blocks'          => [
                'front' => $data['front_blocks'],
                'back'  => $data['back_blocks'] ?? [],
            ],
            'ocr_text'            => null,
            // The app's structured reading (field => value), for the review queue.
            'extracted_fields'    => $data['extracted_fields'] ?? null,
            'app_version'         => $data['app_version'] ?? null,
            'status'              => 'pending',
        ]);

        // Optional images, stored on the private disk under the row's own UUID.
        // `image` is the legacy single-field name, treated as the front image.
        $frontImage = $request->file('front_image') ?? $request->file('image');
        if ($frontImage) {
            $failed->image_path = $this->images->storeOcrSample($failed->id, $frontImage, 'front');
        }
        if ($request->hasFile('back_image')) {
            $failed->image_back_path = $this->images->storeOcrSample($failed->id, $request->file('back_image'), 'back');
        }
        if ($failed->isDirty()) {
            $failed->save();
        }

        // Audit the report itself — never its contents (blocks are PII).
        AuditLogger::log('tablet.ocr.failed_document_reported', $request, [
            'station_id'         => $station ? (string) $station->id : null,
            'station_code'       => $station ? (string) $station->code : null,
            'failed_document_id'   => (string) $failed->id,
            'detected_type'        => $failed->detected_type,
            'front_confidence'     => $frontConfidence,
            'match_score'          => $failed->match_score,
            'has_extracted_fields' => $failed->extracted_fields !== null,
            'has_front_image'      => $failed->image_path !== null,
            'has_back_image'       => $failed->image_back_path !== null,
            'app_version'          => $failed->app_version,
        ]);

        return response()->json([
            'success' => true,
            'data'    => ['id' => $failed->id, 'stored' => true],
            'message' => 'Document reported for review.',
        ], 201);
    }
}
