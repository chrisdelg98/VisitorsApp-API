<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Centralized audit logging for sensitive admin actions.
 *
 * Writes to the `audit` channel (storage/logs/audit-YYYY-MM-DD.log, retained 90 days).
 * All entries share the same structure so they can be grepped or parsed later.
 */
class AuditLogger
{
    public static function log(string $action, Request $request, array $context = []): void
    {
        $user = $request->user();

        Log::channel('audit')->info($action, array_merge([
            'user_id'    => $user?->id,
            'user_email' => $user?->email,
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $context));
    }
}
