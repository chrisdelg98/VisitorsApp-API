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
     * POST /v1/ocr/failed-documents — a tablet reports a document it could not
     * read. The station is taken from the X-API-Key, never from the body.
     *
     * Accepts JSON or multipart. The row lands as `pending` in the review queue
     * the portal turns into templates.
     */
    public function store(StoreOcrFailedDocumentRequest $request): JsonResponse
    {
        $station   = $request->attributes->get('station');
        $validated = $request->validated();

        $failed = OcrFailedDocument::create([
            'station_id'          => $station?->id,
            'detected_type'       => $validated['detected_type'] ?? null,
            'detected_confidence' => $validated['detected_confidence'] ?? null,
            'ocr_blocks'          => $validated['ocr_blocks'] ?? null,
            'ocr_text'            => $validated['ocr_text'] ?? null,
            'app_version'         => $validated['app_version'] ?? null,
            'status'              => 'pending',
        ]);

        // Stored after the insert so the file lives under the row's own UUID.
        if ($request->hasFile('image')) {
            $failed->update([
                'image_path' => $this->images->storeOcrSample($failed->id, $request->file('image')),
            ]);
        }

        // Audit the report itself — never its contents (ocr_text/blocks are PII).
        AuditLogger::log('tablet.ocr.failed_document_reported', $request, [
            'station_id'          => $station ? (string) $station->id : null,
            'station_code'        => $station ? (string) $station->code : null,
            'failed_document_id'  => (string) $failed->id,
            'detected_type'       => $failed->detected_type,
            'has_image'           => $failed->image_path !== null,
            'has_ocr_text'        => $failed->ocr_text !== null,
            'app_version'         => $failed->app_version,
        ]);

        return response()->json([
            'success' => true,
            'data'    => ['id' => $failed->id],
            'message' => 'Document reported for review.',
        ], 201);
    }
}
