<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    /**
     * POST /v1/visits — register a check-in for the authenticated station.
     */
    public function store(StoreVisitRequest $request): JsonResponse
    {
        $station = $request->attributes->get('station');

        $visit = Visit::create([
            ...$request->validated(),
            'station_id'    => $station->id,
            'check_in'      => now(),
            'status'        => 'active',
            'badge_printed' => false,
        ]);

        return response()->json([
            'success' => true,
            'data'    => new VisitResource($visit->load(['visitor', 'station'])),
            'message' => 'Visit registered successfully.',
        ], 201);
    }

    /**
     * PATCH /v1/visits/{visit}/checkout
     */
    public function checkout(Request $request, Visit $visit): JsonResponse
    {
        $station = $request->attributes->get('station');

        if ($visit->station_id !== $station->id) {
            return response()->json([
                'success' => false,
                'message' => 'This visit does not belong to your station.',
                'code'    => 'VISIT_FOREIGN_STATION',
            ], 403);
        }

        if ($visit->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Visit is already checked out.',
                'code'    => 'VISIT_ALREADY_COMPLETED',
            ], 409);
        }

        $visit->update([
            'check_out' => now(),
            'status'    => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'data'    => new VisitResource($visit->load(['visitor', 'station'])),
            'message' => 'Visit checked out successfully.',
        ]);
    }

    /**
     * GET /v1/visits/{visit}
     */
    public function show(Request $request, Visit $visit): JsonResponse
    {
        $station = $request->attributes->get('station');

        if ($visit->station_id !== $station->id) {
            return response()->json([
                'success' => false,
                'message' => 'This visit does not belong to your station.',
                'code'    => 'VISIT_FOREIGN_STATION',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => new VisitResource($visit->load(['visitor', 'station', 'images'])),
        ]);
    }

    /**
     * GET /v1/visits/active — active visits for the authenticated station.
     */
    public function active(Request $request): JsonResponse
    {
        $station = $request->attributes->get('station');

        $visits = Visit::where('station_id', $station->id)
            ->where('status', 'active')
            ->with('visitor')
            ->orderByDesc('check_in')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => VisitResource::collection($visits),
        ]);
    }
}
