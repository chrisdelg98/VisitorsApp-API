<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Ensure the authenticated user is a super_admin.
     * All endpoints in this controller require it.
     */
    private function ensureSuperAdmin(Request $request): ?JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can manage users.',
                'code'    => 'FORBIDDEN',
            ], 403);
        }

        return null;
    }

    /**
     * GET /v1/admin/users — list admin users with active session count.
     */
    public function index(Request $request): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        $users = User::withCount('tokens')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => UserResource::collection($users),
        ]);
    }

    /**
     * POST /v1/admin/users — create a new admin user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        $user = User::create([
            'name'      => (string) $request->string('name'),
            'email'     => (string) $request->string('email'),
            'password'  => (string) $request->string('password'),
            'role'      => (string) $request->string('role'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::log('admin.user.created', $request, [
            'target_user_id'    => $user->id,
            'target_user_email' => $user->email,
            'target_user_role'  => $user->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data'    => new UserResource($user),
        ], 201);
    }

    /**
     * PATCH /v1/admin/users/{user} — update name/email/password/role/is_active.
     * Disabling is_active also revokes all active sessions for that user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        $current = $request->user();

        // Prevent locking yourself out / demoting yourself.
        if ($current->id === $user->id) {
            if ($request->has('is_active') && ! $request->boolean('is_active')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot deactivate your own account.',
                    'code'    => 'SELF_DEACTIVATION_FORBIDDEN',
                ], 422);
            }

            if ($request->filled('role') && (string) $request->string('role') !== $user->role) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own role.',
                    'code'    => 'SELF_ROLE_CHANGE_FORBIDDEN',
                ], 422);
            }
        }

        $changes = $request->only(['name', 'email', 'role', 'is_active']);
        if ($request->filled('password')) {
            $changes['password'] = (string) $request->string('password');
        }

        $wasActive = (bool) $user->is_active;
        $user->fill($changes)->save();

        // If we just deactivated the user, revoke their sessions immediately.
        $sessionsRevoked = 0;
        if ($wasActive && ! $user->is_active) {
            $sessionsRevoked = $user->tokens()->count();
            $user->tokens()->delete();
        }

        AuditLogger::log('admin.user.updated', $request, [
            'target_user_id'    => $user->id,
            'target_user_email' => $user->email,
            'changed_fields'    => array_keys($changes),
            'sessions_revoked'  => $sessionsRevoked,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data'    => new UserResource($user->loadCount('tokens')),
            'meta'    => ['sessions_revoked' => $sessionsRevoked],
        ]);
    }

    /**
     * POST /v1/admin/users/{user}/revoke-tokens — force logout of all sessions
     * for the target user without deactivating the account.
     */
    public function revokeTokens(Request $request, User $user): JsonResponse
    {
        if ($block = $this->ensureSuperAdmin($request)) {
            return $block;
        }

        $count = $user->tokens()->count();
        $user->tokens()->delete();

        AuditLogger::log('admin.user.tokens_revoked', $request, [
            'target_user_id'    => $user->id,
            'target_user_email' => $user->email,
            'sessions_revoked'  => $count,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'All sessions revoked for this user.',
            'meta'    => ['sessions_revoked' => $count],
        ]);
    }
}
