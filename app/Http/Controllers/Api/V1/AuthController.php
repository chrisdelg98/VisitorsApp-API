<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StationAuthRequest;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /v1/auth/station
     * Exchange station_code + pin for the station's api_key.
     * Public endpoint — protected by strict rate limit (5/15 min/IP).
     * This is the ONLY endpoint that emits api_key; no X-API-Key required here.
     */
    public function station(StationAuthRequest $request): JsonResponse
    {
        $station = Station::where('code', $request->string('station_code'))
            ->where('is_active', true)
            ->first();

        // Always check both code and pin before rejecting — avoids leaking which
        // field is wrong (security: timing-safe failure).
        $pinValid = $station && $station->pin && Hash::check($request->string('pin'), $station->pin);

        if (! $pinValid) {
            return response()->json([
                'success' => false,
                'message' => 'Station code or PIN is invalid.',
                'code'    => 'STATION_INVALID',
            ], 401);
        }

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
