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
     * POST /v1/admin/app-releases — upload a build (multipart).
     *
     * Created as `draft`: uploading and publishing are separate steps so a
     * 150 MB upload can be verified before the fleet starts pulling it.
     */
    public function store(StoreAppReleaseRequest $request): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        $platform    = (string) $request->string('platform');
        $versionCode = (int) $request->integer('version_code');

        $file = $this->releases->storeApk($request->file('apk'), $platform, $versionCode);

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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Release uploaded as draft. Publish it when it is verified.',
            'data'    => new AdminAppReleaseResource($release),
        ], 201);
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
