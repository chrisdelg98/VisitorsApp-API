<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Visitors App v1
|--------------------------------------------------------------------------
| All routes are prefixed with /api (configured in bootstrap/app.php).
| V1 routes will be registered here grouped under /api/v1.
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function () {
    // Tablet routes — authenticated by station API key.
    Route::middleware(['api.key', 'throttle:api'])->group(function () {
        Route::get('/station/me', function (\Illuminate\Http\Request $request) {
            $station = $request->attributes->get('station');

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'   => $station->id,
                    'name' => $station->name,
                    'code' => $station->code,
                ],
            ]);
        });
    });
});
