<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StationAuthRequest;
use App\Models\Station;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * POST /v1/auth/station
     * Activate a tablet by PIN. One-time registration per station.
     * Public — protected by strict rate limit (5/15 min/IP).
     */
    public function station(StationAuthRequest $request): JsonResponse
    {
        $lookup = Station::makePinLookup($request->string('pin'));

        $station = Station::where('pin_lookup', $lookup)
            ->where('is_active', true)
            ->first();

        if (! $station) {
            return response()->json([
                'success' => false,
                'message' => 'PIN is invalid or station is inactive.',
                'code'    => 'STATION_INVALID',
            ], 401);
        }

        if ($station->registered_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'This station is already registered to a device. Contact your administrator to reset it.',
                'code'    => 'STATION_ALREADY_REGISTERED',
            ], 401);
        }

        $station->update([
            'device_imei'       => $request->string('device_imei') ?: null,
            'device_android_id' => $request->string('device_android_id') ?: null,
            'device_model'      => $request->string('device_model') ?: null,
            'registered_ip'     => $request->string('device_ip') ?: $request->ip(),
            'registered_at'     => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'api_key' => $station->getAttribute('api_key'),
                'station' => [
                    'id'       => $station->id,
                    'code'     => $station->code,
                    'name'     => $station->name,
                    'location' => $station->location,
                ],
            ],
        ]);
    }
}
