<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fires the DB-resident `sp_auto_close_visits` stored procedure at most once
 * per THROTTLE_MINUTES across all tablet requests, in `terminate()` so the
 * tablet's response latency is unaffected.
 */
class AutoCloseStaleVisits
{
    /** Marker shared across every tablet's requests. */
    private const CACHE_KEY = 'auto_close_visits.last_run';

    /** Upper bound on how often the procedure runs. */
    private const THROTTLE_MINUTES = 60;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Runs AFTER the response has been sent to the client (PHP-FPM/Octane
     * release the connection first). The tablet user waits for nothing here.
     */
    public function terminate(Request $request, Response $response): void
    {
        // The stored procedure is MySQL-only — skip on other drivers (tests run
        // on SQLite, and trying to CALL there just clutters the log).
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Atomic check-and-set: add() returns false if the key already exists,
        // so two concurrent requests can't both fire the procedure.
        if (! Cache::add(self::CACHE_KEY, now(), now()->addMinutes(self::THROTTLE_MINUTES))) {
            return;
        }

        try {
            DB::statement('CALL sp_auto_close_visits()');
        } catch (\Throwable $e) {
            // DB down, procedure missing, etc. — log and leave the marker in
            // place so we don't hammer a broken state. Next try is in 1h.
            report($e);
        }
    }
}
