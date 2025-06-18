<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserController extends Controller
{
    protected UserEntitlementService $entitlementService;

    public function __construct(UserEntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    /**
     * Get a paginated list of all users.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $role = $request->input('role');

        $query = User::query()->with('entitlements:id,type,expires_at');

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Apply role filter
        if ($role) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($users);
    }

    /**
     * Get details of a specific user.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['entitlements.dataset', 'auditLogs' => function ($query) {
            $query->latest()->limit(20);
        }])->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'user' => $user,
            'active_entitlements_count' => $user->entitlements()->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->count()
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', PasswordRule::defaults()],
            'role' => ['required', 'string', 'in:user,researcher,contractor,municipality,admin'],
            'contact_info' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'contact_info' => $request->contact_info ?? null,
        ]);

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_user_created',
            targetType: 'user',
            targetId: $user->id,
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $id],
            'password' => ['sometimes', PasswordRule::defaults()],
            'role' => ['sometimes', 'string', 'in:user,researcher,contractor,municipality,admin'],
            'contact_info' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $user->only(['name', 'email', 'role', 'contact_info']);
        $updateData = $request->only(['name', 'email', 'role', 'contact_info']);

        // Hash password if provided
        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        // Clear user's entitlement cache if role changed
        if ($request->has('role')) {
            $this->entitlementService->clearUserEntitlementsCache($user);
        }

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_user_updated',
            targetType: 'user',
            targetId: $user->id,
            oldValues: $oldValues,
            newValues: $user->only(['name', 'email', 'role', 'contact_info']),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent deletion of the current admin user
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 422);
        }

        $userData = $user->only(['name', 'email', 'role']);

        // Clear user's entitlement cache
        $this->entitlementService->clearUserEntitlementsCache($user);

        // Delete the user (this will also delete related entitlement pivot records)
        $user->delete();

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_user_deleted',
            targetType: 'user',
            targetId: $id,
            oldValues: $userData,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get user's entitlements.
     */
    public function entitlements(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $entitlements = $this->entitlementService->getUserEntitlements($user);

        return response()->json([
            'user_id' => $id,
            'entitlements' => $entitlements
        ]);
    }

    /**
     * Assign an entitlement to a user.
     */
    public function assignEntitlement(Request $request, int $userId, int $entitlementId): JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $entitlement = \App\Models\Entitlement::find($entitlementId);
        if (!$entitlement) {
            return response()->json(['message' => 'Entitlement not found'], 404);
        }

        // Check if already assigned
        if ($user->entitlements()->where('entitlement_id', $entitlementId)->exists()) {
            return response()->json([
                'message' => 'Entitlement already assigned to user'
            ], 422);
        }

        $user->entitlements()->attach($entitlementId);

        // Clear user's entitlement cache
        $this->entitlementService->clearUserEntitlementsCache($user);

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_entitlement_assigned',
            targetType: 'user_entitlement',
            targetId: $userId,
            newValues: [
                'user_id' => $userId,
                'entitlement_id' => $entitlementId
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Entitlement assigned successfully'
        ]);
    }

    /**
     * Remove an entitlement from a user.
     */
    public function removeEntitlement(Request $request, int $userId, int $entitlementId): JsonResponse
    {
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $removed = $user->entitlements()->detach($entitlementId);

        if (!$removed) {
            return response()->json([
                'message' => 'Entitlement was not assigned to this user'
            ], 422);
        }

        // Clear user's entitlement cache
        $this->entitlementService->clearUserEntitlementsCache($user);

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_entitlement_removed',
            targetType: 'user_entitlement',
            targetId: $userId,
            oldValues: [
                'user_id' => $userId,
                'entitlement_id' => $entitlementId
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Entitlement removed successfully'
        ]);
    }
}
