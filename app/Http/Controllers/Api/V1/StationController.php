<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StationController extends Controller
{
    /**
     * Return info about the station currently authenticated via X-API-Key.
     */
    public function me(Request $request): JsonResponse
    {
        $station = $request->attributes->get('station');

        return response()->json([
            'success' => true,
            'data'    => new StationResource($station),
        ]);
    }

    /**
     * POST /v1/station/logout
     * Tablet logs out manually — clears device registration so the PIN can be used again.
     */
    public function logout(Request $request): JsonResponse
    {
        $station = $request->attributes->get('station');
        $station->unregisterDevice('device_logout');

        return response()->json([
            'success' => true,
            'message' => 'Station logged out. PIN can now be used to register a new device.',
        ]);
    }
}
