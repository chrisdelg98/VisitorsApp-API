<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\Visit;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VisitController extends Controller
{
    /**
     * Normalize a device-supplied ISO-8601 timestamp (with offset) to the app
     * timezone so the stored instant is correct regardless of the station's
     * local offset. Returns now() when no value was sent.
     */
    private function deviceTime(?string $value): Carbon
    {
        return ($value !== null && $value !== '')
            ? Carbon::parse($value)->setTimezone(config('app.timezone'))
            : now();
    }

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
            // The visitor left station A when they entered station B: close the
            // original visit if it is still open. Guard on active + no check_out
            // so we never overwrite a real checkout already recorded at A.
            Visit::where('id', $visit->original_visit_id)
                ->where('status', 'active')
                ->whereNull('check_out')
                ->update([
                    'check_out'     => $visit->check_in, // real time they left A
                    'status'        => 'completed',
                    'checkout_type' => 'reentry',
                ]);

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
     *
     * Idempotent: the tablet is the source of truth and may retry offline.
     * A re-checkout on an already-completed visit is allowed and overwrites
     * check_out with the incoming (device) time — this is what fixes the
     * "afternoon checkout never arrives" bug after a same-day re-entry.
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

        $validated = $request->validate([
            'check_out'       => ['nullable', 'date'],
            'reentry_count'   => ['nullable', 'integer', 'min:0'],
            'last_reentry_at' => ['nullable', 'date'],
        ]);

        $attributes = [
            'check_out'     => $this->deviceTime($validated['check_out'] ?? null),
            'status'        => 'completed',
            'checkout_type' => 'visitor',
        ];

        // Persist the latest re-entry state if the final checkout carries it.
        if (array_key_exists('reentry_count', $validated) && $validated['reentry_count'] !== null) {
            $attributes['reentry_count'] = $validated['reentry_count'];
        }
        if (array_key_exists('last_reentry_at', $validated) && $validated['last_reentry_at'] !== null) {
            $attributes['last_reentry_at'] = $this->deviceTime($validated['last_reentry_at']);
        }

        $visit->update($attributes);

        return response()->json([
            'success' => true,
            'data'    => new VisitResource($visit->load(['visitor', 'station'])),
            'message' => 'Visit checked out successfully.',
        ]);
    }

    /**
     * PATCH /v1/visits/{visit}/reentry
     *
     * Reopens a (typically completed) visit for a same-day re-entry at the same
     * station. Idempotent by design: values are SET, not incremented, so the
     * tablet can safely retry offline without inflating the re-entry count.
     */
    public function reentry(Request $request, Visit $visit): JsonResponse
    {
        $station = $request->attributes->get('station');

        if ($visit->station_id !== $station->id) {
            return response()->json([
                'success' => false,
                'message' => 'This visit does not belong to your station.',
                'code'    => 'VISIT_FOREIGN_STATION',
            ], 403);
        }

        $validated = $request->validate([
            'reentry_count'   => ['nullable', 'integer', 'min:1'],
            'last_reentry_at' => ['nullable', 'date'],
        ]);

        $reentryCount  = $validated['reentry_count'] ?? ($visit->reentry_count + 1);
        $lastReentryAt = $this->deviceTime($validated['last_reentry_at'] ?? null);

        $visit->update([
            'status'          => 'active',
            'check_out'       => null,
            'checkout_type'   => null,
            'reentry_count'   => $reentryCount,
            'last_reentry_at' => $lastReentryAt,
        ]);

        AuditLogger::log('tablet.visit.reentry', $request, [
            'station_id'    => (string) $station->id,
            'visit_id'      => (string) $visit->id,
            'reentry_count' => $reentryCount,
        ]);

        return response()->json([
            'success' => true,
            'data'    => new VisitResource($visit->load(['visitor', 'station'])),
            'message' => 'Visit re-entry recorded successfully.',
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
