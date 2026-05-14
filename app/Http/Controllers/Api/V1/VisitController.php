<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Visit;
use App\Support\AuditLogger;
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

        // Audit re-entry creation (visit derived from a previous one at another station).
        if ($visit->original_visit_id) {
            AuditLogger::log('tablet.visit.reentry_created', $request, [
                'station_id'              => (string) $station->id,
                'station_code'            => (string) $station->code,
                'new_visit_id'            => (string) $visit->id,
                'original_visit_id'       => (string) $visit->original_visit_id,
                'reentry_from_station_id' => $visit->reentry_from_station_id ? (string) $visit->reentry_from_station_id : null,
                'visitor_id'              => (string) $visit->visitor_id,
            ]);
        }

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
     * Cross-station read: any authenticated station can look up any visit.
     * Used by the re-entry flow — Station B looks up a visit originally created by Station A.
     * Ownership is only enforced on write operations (checkout, image upload).
     */
    public function show(Request $request, Visit $visit): JsonResponse
    {
        $station = $request->attributes->get('station');

        // Audit any cross-station lookup for traceability of the re-entry flow.
        if ($station && $visit->station_id !== $station->id) {
            AuditLogger::log('tablet.visit.cross_station_lookup', $request, [
                'station_id'        => (string) $station->id,
                'station_code'      => (string) $station->code,
                'visit_id'          => (string) $visit->id,
                'owner_station_id'  => (string) $visit->station_id,
            ]);
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
