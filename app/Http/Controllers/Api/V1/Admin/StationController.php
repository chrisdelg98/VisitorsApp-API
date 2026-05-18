<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStationRequest;
use App\Http\Resources\AdminStationResource;
use App\Models\Station;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StationController extends Controller
{
    /**
     * GET /v1/admin/stations — list all stations (including api_key).
     */
    public function index(): JsonResponse
    {
        $stations = Station::orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data'    => AdminStationResource::collection($stations),
        ]);
    }

    /**
     * POST /v1/admin/stations — create a new station with a fresh api_key.
     */
    public function store(StoreStationRequest $request): JsonResponse
    {
        $pin = (string) $request->string('pin');

        $station = Station::create([
            'name'       => $request->string('name'),
            'location'   => $request->string('location') ?: null,
            'code'       => $request->string('code'),
            'api_key'    => Str::random(64),
            'pin'        => Hash::make($pin),
            'pin_lookup' => Station::makePinLookup($pin),
            'is_active'  => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('admin.station.created', $request, [
            'station_id'   => $station->id,
            'station_code' => (string) $station->code,
            'station_name' => (string) $station->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Station created successfully.',
            'data'    => new AdminStationResource($station),
        ], 201);
    }

    /**
     * POST /v1/admin/stations/{station}/reset-device
     * Clear device registration so the PIN can be used on a new tablet.
     * The previous device data is preserved in station_device_logs.
     */
    public function resetDevice(Request $request, Station $station): JsonResponse
    {
        if ($station->registered_at === null) {
            return response()->json([
                'success' => false,
                'message' => 'This station has no registered device.',
                'code'    => 'STATION_NOT_REGISTERED',
            ], 422);
        }

        $station->unregisterDevice('admin_reset');

        AuditLogger::log('admin.station.device_reset', $request, [
            'station_id'   => $station->id,
            'station_code' => (string) $station->code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device registration cleared. The station PIN can now be used on a new device.',
            'data'    => new AdminStationResource($station->fresh()),
        ]);
    }
}
