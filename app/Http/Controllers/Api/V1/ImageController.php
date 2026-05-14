<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitImageRequest;
use App\Http\Resources\VisitImageResource;
use App\Models\Visit;
use App\Models\VisitImage;
use App\Services\ImageService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImageController extends Controller
{
    public function __construct(private readonly ImageService $images)
    {
    }

    /**
     * POST /v1/visits/{visit}/images — upload one image of a given type.
     */
    public function store(StoreVisitImageRequest $request, Visit $visit): JsonResponse
    {
        $station = $request->attributes->get('station');

        if ($visit->station_id !== $station->id) {
            return response()->json([
                'success' => false,
                'message' => 'This visit does not belong to your station.',
                'code'    => 'VISIT_FOREIGN_STATION',
            ], 403);
        }

        $image = $this->images->storeForVisit(
            $visit,
            $request->string('type')->toString(),
            $request->file('image')
        );

        return response()->json([
            'success' => true,
            'data'    => new VisitImageResource($image),
            'message' => 'Image uploaded successfully.',
        ], 201);
    }

    /**
     * GET /v1/visits/{visit}/images/{type} — stream the image bytes.
     * Cross-station read: any authenticated station can download images of any visit.
     * Required for the re-entry flow where Station B downloads photos from a visit
     * originally created by Station A to pre-fill the confirmation modal.
     */
    public function show(Request $request, Visit $visit, string $type): StreamedResponse|JsonResponse
    {
        $image = VisitImage::where('visit_id', $visit->id)
            ->where('type', $type)
            ->first();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        if (! $image || ! $disk->exists($image->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Image not found.',
                'code'    => 'IMAGE_NOT_FOUND',
            ], 404);
        }

        return $disk->response($image->file_path);
    }
}
