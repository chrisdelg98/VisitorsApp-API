<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppReleaseRequest;
use App\Http\Requests\Admin\UpdateAppReleaseRequest;
use App\Http\Resources\AdminAppReleaseResource;
use App\Models\AppRelease;
use App\Services\AppReleaseService;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Release management for the tablet app. Everything here is super_admin only:
 * uploading a build is effectively pushing code to every station.
 */
class AppReleaseController extends Controller
{
    public function __construct(private readonly AppReleaseService $releases)
    {
    }

    private function ensureSuperAdmin(Request $request): ?JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can manage app releases.',
                'code'    => 'FORBIDDEN',
            ], 403);
        }

        return null;
    }

    /**
     * GET /v1/admin/app-releases — every release, newest build first.
     */
    public function index(Request $request): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        $releases = AppRelease::with('createdBy')
            ->orderBy('platform')
            ->orderByDesc('version_code')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => AdminAppReleaseResource::collection($releases),
        ]);
    }

    /**
     * GET /v1/admin/app-releases/staged — APKs waiting in the staging directory.
     *
     * The secondary way in: drop the build there over SFTP and register it with
     * `staged_file` instead of pushing 150 MB through an HTTP request. Nothing
     * listed here is distributed — a staged file is not a release yet.
     */
    public function staged(Request $request): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        return response()->json([
            'success'      => true,
            'data'         => $this->releases->stagedFiles(),
            'staging_path' => $this->releases->stagingAbsolutePath(),
        ]);
    }

    /**
     * POST /v1/admin/app-releases — register a build, uploaded either as
     * multipart `apk` or as a `staged_file` already sitting in staging.
     *
     * Created as `draft`: registering and publishing are separate steps so a
     * 150 MB binary can be verified before the fleet starts pulling it.
     */
    public function store(StoreAppReleaseRequest $request): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        $platform    = (string) $request->string('platform');
        $versionCode = (int) $request->integer('version_code');

        $file = $request->filled('staged_file')
            ? $this->adoptStagedFile($request, $platform, $versionCode)
            : $this->releases->storeApk($request->file('apk'), $platform, $versionCode);

        $release = AppRelease::create([
            'platform'                   => $platform,
            'version_code'               => $versionCode,
            'version_name'               => (string) $request->string('version_name'),
            'status'                     => 'draft',
            'release_notes'              => $request->input('release_notes'),
            'min_supported_version_code' => (int) $request->integer('min_supported_version_code'),
            'is_critical'                => $request->boolean('is_critical'),
            'created_by'                 => $request->user()?->id,
            ...$file,
        ]);

        AuditLogger::log('admin.app_release.uploaded', $request, [
            'release_id'   => (string) $release->id,
            'platform'     => $release->platform,
            'version_code' => $release->version_code,
            'version_name' => $release->version_name,
            'file_size'    => $release->file_size,
            'file_hash'    => $release->file_hash,
            'source'       => $request->filled('staged_file') ? 'staging' : 'upload',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Release registered as draft. Publish it when it is verified.',
            'data'    => new AdminAppReleaseResource($release),
        ], 201);
    }

    /**
     * Take a staged file as the release binary. The request already proved the
     * name resolves inside the staging directory and that the bytes really are
     * an APK, so all that is left is the hash the tablets check their download
     * against — computed here, never asked of the operator.
     *
     * @return array{file_path: string, file_name: string, file_hash: string, file_size: int}
     */
    private function adoptStagedFile(StoreAppReleaseRequest $request, string $platform, int $versionCode): array
    {
        $fileName = (string) $request->string('staged_file');
        $hash     = (string) hash_file('sha256', (string) $this->releases->resolveStagedPath($fileName));

        return $this->releases->promoteStaged($fileName, $platform, $versionCode, $hash);
    }

    /**
     * PATCH /v1/admin/app-releases/{appRelease} — publish, roll back or edit
     * the notes. Rolling back is `status: deprecated`: the previous published
     * build automatically becomes the latest again.
     */
    public function update(UpdateAppReleaseRequest $request, AppRelease $appRelease): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        $previousStatus = $appRelease->status;
        $data           = $request->validated();

        // First publication stamps the date; re-publishing keeps the original.
        if (($data['status'] ?? null) === 'published' && $appRelease->published_at === null) {
            $data['published_at'] = now();
        }

        $appRelease->update($data);

        AuditLogger::log('admin.app_release.updated', $request, [
            'release_id'      => (string) $appRelease->id,
            'version_code'    => $appRelease->version_code,
            'previous_status' => $previousStatus,
            'status'          => $appRelease->status,
            'is_critical'     => $appRelease->is_critical,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Release updated.',
            'data'    => new AdminAppReleaseResource($appRelease->fresh()),
        ]);
    }

    /**
     * DELETE /v1/admin/app-releases/{appRelease} — drop the row and its binary.
     *
     * Only for builds nobody can be running: a published release must be
     * deprecated first, which is also how a rollback is done.
     */
    public function destroy(Request $request, AppRelease $appRelease): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        if ($appRelease->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Deprecate the release before deleting it.',
                'code'    => 'RELEASE_PUBLISHED',
            ], 422);
        }

        $this->releases->deleteApk($appRelease);
        $appRelease->delete();

        AuditLogger::log('admin.app_release.deleted', $request, [
            'release_id'   => (string) $appRelease->id,
            'platform'     => $appRelease->platform,
            'version_code' => $appRelease->version_code,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Release deleted.',
        ]);
    }
}
