<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStationRequest;
use App\Http\Resources\AdminStationResource;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
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
        $station = Station::create([
            'name'      => $request->string('name'),
            'code'      => $request->string('code'),
            'api_key'   => Str::random(64),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Station created successfully.',
            'data'    => new AdminStationResource($station),
        ], 201);
    }
}
