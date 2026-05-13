<?php

use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\StationController as AdminStationController;
use App\Http\Controllers\Api\V1\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\V1\Admin\VisitController as AdminVisitController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ImageController;
use App\Http\Controllers\Api\V1\StationController;
use App\Http\Controllers\Api\V1\VisitController;
use App\Http\Controllers\Api\V1\VisitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Visitors App v1
|--------------------------------------------------------------------------
| All routes are prefixed with /api (configured in bootstrap/app.php).
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function () {
    // -----------------------------------------------------------------
    // Public — strict rate limit (5/hour/IP)
    // -----------------------------------------------------------------
    Route::post('/auth/validate-station', [AuthController::class, 'validateStation'])
        ->middleware('throttle:validate-station');

    // -----------------------------------------------------------------
    // Tablet endpoints — require valid X-API-Key
    // -----------------------------------------------------------------
    Route::middleware(['api.key', 'throttle:api'])->group(function () {
        Route::get('/station/me', [StationController::class, 'me']);

        // Visitors
        Route::get('/visitors/search', [VisitorController::class, 'search']);
        Route::get('/visitors/{visitor}/latest-visit', [VisitorController::class, 'latestVisit']);
        Route::post('/visitors', [VisitorController::class, 'store']);
        Route::put('/visitors/{visitor}', [VisitorController::class, 'update']);

        // Visits
        Route::post('/visits', [VisitController::class, 'store']);
        Route::get('/visits/active', [VisitController::class, 'active']);
        Route::patch('/visits/{visit}/checkout', [VisitController::class, 'checkout']);
        Route::get('/visits/{visit}', [VisitController::class, 'show']);
    });

    // -----------------------------------------------------------------
    // Image upload — same auth, separate rate limiter (30/min/station)
    // -----------------------------------------------------------------
    Route::middleware(['api.key', 'throttle:uploads'])->group(function () {
        Route::post('/visits/{visit}/images', [ImageController::class, 'store']);
        Route::get('/visits/{visit}/images/{type}', [ImageController::class, 'show'])
            ->name('visits.images.show');
    });

    // -----------------------------------------------------------------
    // Admin — Sanctum Bearer tokens
    // -----------------------------------------------------------------
    Route::prefix('admin')->group(function () {
        // Public login — strict rate limit (10/hour/IP)
        Route::post('/login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:admin-login');

        // Authenticated admin endpoints
        Route::middleware(['auth:sanctum', 'throttle:admin'])->group(function () {
            Route::post('/logout', [AdminAuthController::class, 'logout']);
            Route::get('/me', [AdminAuthController::class, 'me']);

            // Visits
            Route::get('/visits', [AdminVisitController::class, 'index']);
            Route::get('/visits/{visit}', [AdminVisitController::class, 'show']);
            Route::patch('/visits/{visit}/status', [AdminVisitController::class, 'updateStatus']);

            // Stats
            Route::get('/stats', [AdminStatsController::class, 'index']);

            // Stations
            Route::get('/stations', [AdminStationController::class, 'index']);
            Route::post('/stations', [AdminStationController::class, 'store']);
        });
    });
});
