<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * GET /v1/admin/countries — list all active countries.
     * Used by the AccessPortal to populate dropdowns for station/user assignment.
     */
    public function index(): JsonResponse
    {
        $countries = Country::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => CountryResource::collection($countries),
        ]);
    }
}
