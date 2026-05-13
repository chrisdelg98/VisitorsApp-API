<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidateStationRequest;
use App\Models\Station;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Exchange a station code for its API key.
     * Public endpoint — protected by strict rate limit (5/hour/IP).
     */
    public function validateStation(ValidateStationRequest $request): JsonResponse
    {
        $station = Station::where('code', $request->string('code'))
            ->where('is_active', true)
            ->first();

        if (! $station) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid station code.',
                'code'    => 'STATION_INVALID',
            ], 404);
        }

        // api_key is normally hidden from serialization; access via attribute directly.
        return response()->json([
            'success' => true,
            'data'    => [
                'station_id' => $station->id,
                'name'       => $station->name,
                'code'       => $station->code,
                'api_key'    => $station->getAttribute('api_key'),
            ],
            'message' => 'Station validated successfully.',
        ]);
    }
}
