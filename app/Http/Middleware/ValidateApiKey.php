<?php

namespace App\Http\Middleware;

use App\Models\Station;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Authenticates tablet requests using the X-API-Key header.
     * On success, binds the Station model to the request as `station`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API key is required.',
                'code'    => 'API_KEY_MISSING',
            ], 401);
        }

        $station = Station::where('api_key', $apiKey)->first();

        if (! $station || ! $station->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive API key.',
                'code'    => 'API_KEY_INVALID',
            ], 401);
        }

        // Make the authenticated station available to controllers via $request->station.
        $request->attributes->set('station', $station);

        return $next($request);
    }
}
