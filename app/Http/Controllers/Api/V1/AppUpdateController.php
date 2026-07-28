<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppReleaseResource;
use App\Models\AppRelease;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppUpdateController extends Controller
{
    /**
     * GET /v1/app/latest — is there a newer build for this device?
     *
     * The device passes its own `current_version_code`; the server does the
     * comparing and answers with two booleans, so the app never has to decide
     * what "critical" means. Without that parameter we can only report that a
     * build exists and whether the release itself is flagged critical.
     *
     * Cheap check: ETag / If-None-Match → 304, same as the OCR catalog.
     */
    public function latest(Request $request): JsonResponse|Response
    {
        $platform = $request->string('platform')->toString() ?: (string) config('app_updates.default_platform');
        $current  = max(0, $request->integer('current_version_code'));

        $release = AppRelease::latestPublished($platform);

        // The client version is part of the validator on purpose: the same
        // release is a different answer for a device that just updated, so a
        // stale 304 must not hide `update_available: false` from it.
        $etag = $this->etagFor($release, $current);

        if ($this->clientHasVersion($request, $etag)) {
            return response()->noContent(304)->withHeaders($this->cacheHeaders($etag));
        }

        $updateAvailable = $release !== null && ($current === 0 || $release->version_code > $current);

        $updateRequired = $updateAvailable && $release !== null && (
            $release->is_critical
            || ($current > 0 && $current < $release->min_supported_version_code)
        );

        $station = $request->attributes->get('station');

        AuditLogger::log('tablet.app.update_checked', $request, [
            'station_id'           => $station ? (string) $station->id : null,
            'station_code'         => $station ? (string) $station->code : null,
            'platform'             => $platform,
            'current_version_code' => $current ?: null,
            'latest_version_code'  => $release?->version_code,
            'update_required'      => $updateRequired,
        ]);

        return response()->json([
            'success'          => true,
            'data'             => $release ? new AppReleaseResource($release) : null,
            'update_available' => $updateAvailable,
            'update_required'  => $updateRequired,
        ], 200, $this->cacheHeaders($etag));
    }

    /**
     * GET /v1/app/releases/{appRelease}/download — the APK bytes.
     *
     * Served with BinaryFileResponse rather than a stream so Range requests
     * work: a 150 MB download interrupted by the station's wifi resumes where
     * it stopped instead of starting over.
     */
    public function download(Request $request, AppRelease $appRelease): BinaryFileResponse|JsonResponse
    {
        if ($appRelease->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'This release is not available for download.',
                'code'    => 'RELEASE_NOT_PUBLISHED',
            ], 404);
        }

        $disk = Storage::disk((string) config('app_updates.disk'));

        if (! $disk->exists($appRelease->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Release binary not found.',
                'code'    => 'RELEASE_FILE_MISSING',
            ], 404);
        }

        $station = $request->attributes->get('station');

        AuditLogger::log('tablet.app.downloaded', $request, [
            'station_id'   => $station ? (string) $station->id : null,
            'station_code' => $station ? (string) $station->code : null,
            'release_id'   => (string) $appRelease->id,
            'version_code' => $appRelease->version_code,
        ]);

        return response()->download(
            $disk->path($appRelease->file_path),
            $appRelease->file_name,
            [
                // Explicit and exact: SecurityHeaders sends `nosniff`, so a
                // wrong Content-Type here would break the install on Android.
                'Content-Type'  => 'application/vnd.android.package-archive',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    /** Release identity + the asking device's version. `0` when nothing is published. */
    private function etagFor(?AppRelease $release, int $currentVersionCode): string
    {
        if ($release === null) {
            return '0-'.$currentVersionCode;
        }

        return $release->version_code
            .'.'.($release->updated_at?->getTimestamp() ?? 0)
            .'-'.$currentVersionCode;
    }

    /**
     * True when If-None-Match matches the current version. Tolerates the weak
     * validator prefix and the quoting some HTTP clients add.
     */
    private function clientHasVersion(Request $request, string $etag): bool
    {
        $header = $request->header('If-None-Match');

        if ($header === null || $header === '') {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            $candidate = preg_replace('/^W\//', '', $candidate) ?? $candidate;
            $candidate = trim($candidate, '"');

            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function cacheHeaders(string $etag): array
    {
        return [
            'ETag'          => '"'.$etag.'"',
            'Cache-Control' => 'private, no-cache',
        ];
    }
}
