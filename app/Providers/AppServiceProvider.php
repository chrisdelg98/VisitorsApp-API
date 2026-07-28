<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Rate limiters used by route groups. Keys match the named limiter
     * used in routes/api.php via ->middleware('throttle:<name>').
     */
    protected function configureRateLimiters(): void
    {
        // Station authentication — exchange station_code+pin for api_key.
        RateLimiter::for('station-auth', function (Request $request) {
            return Limit::perMinutes(10, 10)->by($request->ip());
        });

        // Admin login — strict.
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        // Authenticated tablet traffic — keyed per station when present.
        RateLimiter::for('api', function (Request $request) {
            $station = $request->attributes->get('station');
            $key = $station?->id ?? $request->ip();

            return Limit::perMinute(120)->by($key);
        });

        // Image uploads from tablets.
        RateLimiter::for('uploads', function (Request $request) {
            $station = $request->attributes->get('station');
            $key = $station?->id ?? $request->ip();

            return Limit::perMinute(30)->by($key);
        });

        // OCR failure reports from tablets — shares the upload profile but is
        // declared separately so it can be tuned without touching image uploads.
        RateLimiter::for('ocr-reports', function (Request $request) {
            $station = $request->attributes->get('station');
            $key = $station?->id ?? $request->ip();

            return Limit::perMinute(20)->by($key);
        });

        // App update checks — cheap, but a device in a retry loop should not
        // be able to hammer it.
        RateLimiter::for('app-updates', function (Request $request) {
            $station = $request->attributes->get('station');
            $key = $station?->id ?? $request->ip();

            return Limit::perMinute(30)->by($key);
        });

        // APK downloads — the binary is ~150 MB, so a handful per minute per
        // station is already generous (a resumed download reuses this budget).
        RateLimiter::for('app-downloads', function (Request $request) {
            $station = $request->attributes->get('station');
            $key = $station?->id ?? $request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Authenticated admin panel.
        RateLimiter::for('admin', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(200)->by($key);
        });

        // Unauthenticated fallback by IP.
        RateLimiter::for('global', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
