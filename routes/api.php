<?php

use App\Http\Controllers\Api\V1\Admin\AppReleaseController as AdminAppReleaseController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\CountryController as AdminCountryController;
use App\Http\Controllers\Api\V1\Admin\StationController as AdminStationController;
use App\Http\Controllers\Api\V1\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\VisitController as AdminVisitController;
use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ImageController;
use App\Http\Controllers\Api\V1\OcrFailedDocumentController;
use App\Http\Controllers\Api\V1\OcrTemplateController;
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
    // Public — strict rate limit (5/15min/IP)
    // -----------------------------------------------------------------
    Route::post('/auth/station', [AuthController::class, 'station'])
        ->middleware('throttle:station-auth');

    // -----------------------------------------------------------------
    // Tablet endpoints — require valid X-API-Key
    // -----------------------------------------------------------------
    Route::middleware(['api.key', 'throttle:api', 'auto-close-visits'])->group(function () {
        Route::get('/station/me', [StationController::class, 'me']);
        Route::post('/station/logout', [StationController::class, 'logout']);

        // Visitors
        Route::get('/visitors/search', [VisitorController::class, 'search']);
        Route::get('/visitors/{visitor}/latest-visit', [VisitorController::class, 'latestVisit']);
        Route::post('/visitors', [VisitorController::class, 'store']);
        Route::put('/visitors/{visitor}', [VisitorController::class, 'update']);

        // Visits
        Route::post('/visits', [VisitController::class, 'store']);
        Route::get('/visits/active', [VisitController::class, 'active']);
        Route::patch('/visits/{visit}/checkout', [VisitController::class, 'checkout']);
        Route::patch('/visits/{visit}/reentry', [VisitController::class, 'reentry']);
        Route::get('/visits/{visit}', [VisitController::class, 'show']);

        // OCR template catalog — full snapshot, conditional via ETag/If-None-Match.
        Route::get('/ocr/templates', [OcrTemplateController::class, 'index']);
    });

    // -----------------------------------------------------------------
    // OCR failure reports — same auth, own rate limiter (20/min/station)
    // -----------------------------------------------------------------
    Route::middleware(['api.key', 'throttle:ocr-reports'])->group(function () {
        Route::post('/ocr/failed-documents', [OcrFailedDocumentController::class, 'store']);
    });

    // -----------------------------------------------------------------
    // App auto-update — cheap check (30/min) and heavy download (5/min).
    // Kept out of the tablet group above so the daily check does not drag
    // the auto-close sweep along with it.
    // -----------------------------------------------------------------
    Route::middleware(['api.key', 'throttle:app-updates'])->group(function () {
        Route::get('/app/latest', [AppUpdateController::class, 'latest']);
    });

    Route::middleware(['api.key', 'throttle:app-downloads'])->group(function () {
        Route::get('/app/releases/{appRelease}/download', [AppUpdateController::class, 'download'])
            ->name('app.releases.download');
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

            // Countries
            Route::get('/countries', [AdminCountryController::class, 'index']);

            // Stations
            Route::get('/stations', [AdminStationController::class, 'index']);
            Route::post('/stations', [AdminStationController::class, 'store']);
            Route::post('/stations/{station}/reset-device', [AdminStationController::class, 'resetDevice']);
            Route::get('/stations/{station}/device-logs', [AdminStationController::class, 'deviceLogs']);

            // App releases (super_admin only — enforced inside the controller)
            Route::get('/app-releases', [AdminAppReleaseController::class, 'index']);
            Route::get('/app-releases/staged', [AdminAppReleaseController::class, 'staged']);
            Route::post('/app-releases', [AdminAppReleaseController::class, 'store']);
            Route::patch('/app-releases/{appRelease}', [AdminAppReleaseController::class, 'update']);
            Route::delete('/app-releases/{appRelease}', [AdminAppReleaseController::class, 'destroy']);

            // Users (super_admin only — enforced inside the controller)
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::patch('/users/{user}', [AdminUserController::class, 'update']);
            Route::post('/users/{user}/revoke-tokens', [AdminUserController::class, 'revokeTokens']);
        });
    });
});
