<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\RequestBody;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;
use Dedoc\Scramble\Attributes\Parameters;

#[Tag('Admin - User Management')]
class UserController extends Controller
{
    protected UserEntitlementService $entitlementService;

    public function __construct(UserEntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    /**
     * Display a listing of users with optional search and filtering
     */
    #[OperationId('listUsers')]
    #[Summary('List users')]
    #[Description('Retrieve a paginated list of users with optional search and role filtering.')]
    #[Parameters([
        'search' => 'string|optional|Search term for user name or email',
        'role' => 'string|optional|Filter by user role (admin, user)',
        'page' => 'integer|optional|Page number for pagination',
        'per_page' => 'integer|optional|Items per page (max 100)'
    ])]
    #[Response(200, 'Users retrieved successfully', [
        'data' => [
            [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => 'user',
                'created_at' => '2024-01-01T12:00:00.000000Z',
                'entitlements_count' => 3
            ]
        ],
        'meta' => [
            'current_page' => 1,
            'total' => 150,
            'per_page' => 15
        ]
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(403, 'Access denied', [
        'message' => 'Access denied. Admin role required.'
    ])]
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

        return UserResource::collection($users);
    }

    /**
     * Display the specified user with their entitlements and recent audit logs
     */
    #[OperationId('showUser')]
    #[Summary('Show user details')]
    #[Description('Retrieve detailed information about a specific user including entitlements and recent audit logs.')]
    #[Response(200, 'User details retrieved', [
        'data' => [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'user',
            'phone' => '+1234567890',
            'company' => 'Example Corp',
            'created_at' => '2024-01-01T12:00:00.000000Z',
            'entitlements' => [
                [
                    'id' => 1,
                    'name' => 'Dataset Access',
                    'type' => 'DS-ALL'
                ]
            ],
            'recent_audit_logs' => [
                [
                    'id' => 1,
                    'action' => 'user_login',
                    'created_at' => '2024-01-01T12:00:00.000000Z'
                ]
            ]
        ]
    ])]
    #[Response(404, 'User not found', [
        'message' => 'User not found'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(403, 'Access denied', [
        'message' => 'Access denied. Admin role required.'
    ])]
    public function show(string $id): JsonResponse
    {
        $user = User::with(['entitlements.dataset', 'auditLogs' => function ($query) {
            $query->latest()->limit(20);
        }])->find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $activeEntitlementsCount = $user->entitlements()->where(function ($query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();

        return response()->json([
            'user' => new UserResource($user),
            'active_entitlements_count' => $activeEntitlementsCount
        ]);
    }

    /**
     * Store a newly created user
     */
    #[OperationId('createUser')]
    #[Summary('Create user')]
    #[Description('Create a new user with validation for name, email, password, role, and contact information.')]
    #[RequestBody([
        'name' => 'string|required|User full name',
        'email' => 'string|required|email|unique|User email address',
        'password' => 'string|required|min:8|User password',
        'role' => 'string|required|in:admin,user|User role',
        'phone' => 'string|optional|User phone number',
        'company' => 'string|optional|User company name'
    ])]
    #[Response(201, 'User created successfully', [
        'message' => 'User created successfully',
        'user' => [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'user',
            'created_at' => '2024-01-01T12:00:00.000000Z'
        ]
    ])]
    #[Response(422, 'Validation failed', [
        'message' => 'The given data was invalid.',
        'errors' => [
            'email' => ['The email has already been taken.']
        ]
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(403, 'Access denied', [
        'message' => 'Access denied. Admin role required.'
    ])]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', PasswordRule::defaults()],
            'role' => ['required', 'string', 'in:user,researcher,contractor,municipality,admin'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Build contact_info array from individual fields
        $contactInfo = [];
        if ($request->phone) $contactInfo['phone'] = $request->phone;
        if ($request->company) $contactInfo['company'] = $request->company;
        if ($request->department) $contactInfo['department'] = $request->department;
        if ($request->address) $contactInfo['address'] = $request->address;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'contact_info' => !empty($contactInfo) ? $contactInfo : null,
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
            'user' => new UserResource($user)
        ], 201);
    }

    /**
     * Update the specified user
     */
    #[OperationId('updateUser')]
    #[Summary('Update user')]
    #[Description('Update user details with validation, password hashing, contact info handling, and entitlement cache clearing.')]
    #[RequestBody([
        'name' => 'string|optional|User full name',
        'email' => 'string|optional|email|unique|User email address',
        'password' => 'string|optional|min:8|User password',
        'role' => 'string|optional|in:admin,user|User role',
        'phone' => 'string|optional|User phone number',
        'company' => 'string|optional|User company name'
    ])]
    #[Response(200, 'User updated successfully', [
        'message' => 'User updated successfully',
        'user' => [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'user',
            'updated_at' => '2024-01-01T12:00:00.000000Z'
        ]
    ])]
    #[Response(404, 'User not found', [
        'message' => 'User not found'
    ])]
    #[Response(422, 'Validation failed', [
        'message' => 'The given data was invalid.',
        'errors' => [
            'email' => ['The email has already been taken.']
        ]
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(403, 'Access denied', [
        'message' => 'Access denied. Admin role required.'
    ])]
    public function update(Request $request, string $id): JsonResponse
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
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $user->only(['name', 'email', 'role', 'contact_info']);
        $updateData = $request->only(['name', 'email', 'role']);

        // Build contact_info array from individual fields if any contact fields are provided
        if ($request->hasAny(['phone', 'company', 'department', 'address'])) {
            $contactInfo = [];
            if ($request->has('phone')) $contactInfo['phone'] = $request->phone;
            if ($request->has('company')) $contactInfo['company'] = $request->company;
            if ($request->has('department')) $contactInfo['department'] = $request->department;
            if ($request->has('address')) $contactInfo['address'] = $request->address;

            $updateData['contact_info'] = !empty($contactInfo) ? $contactInfo : null;
        }

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
            'user' => new UserResource($user->fresh())
        ]);
    }

    /**
     * Delete a user.
     */
    #[OperationId('deleteUser')]
    #[Summary('Delete user')]
    #[Description('Delete a user with checks to prevent self-deletion or deletion of users with active entitlements.')]
    #[Response(200, 'User deleted successfully', [
        'message' => 'User deleted successfully'
    ])]
    #[Response(400, 'Cannot delete user', [
        'message' => 'Cannot delete user with active entitlements'
    ])]
    #[Response(403, 'Cannot delete self', [
        'message' => 'You cannot delete your own account'
    ])]
    #[Response(404, 'User not found', [
        'message' => 'User not found'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    public function destroy(Request $request, string $id): JsonResponse
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

        // Check if user has entitlements assigned
        $entitlementCount = $user->entitlements()->count();
        if ($entitlementCount > 0) {
            return response()->json([
                'message' => "Cannot delete user. They have {$entitlementCount} entitlement(s) assigned. Please remove all entitlements first."
            ], 422);
        }

        $userData = $user->only(['name', 'email', 'role']);

        // Clear user's entitlement cache
        $this->entitlementService->clearUserEntitlementsCache($user);

        // Delete the user (audit logs with user_id will be set to null automatically)
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
    #[OperationId('getUserEntitlements')]
    #[Summary('Get user entitlements')]
    #[Description('Retrieve all entitlements assigned to a specific user.')]
    #[Response(200, 'User entitlements retrieved', [
        'entitlements' => [
            [
                'id' => 1,
                'name' => 'Dataset Access',
                'type' => 'DS-ALL',
                'description' => 'Full access to all datasets',
                'assigned_at' => '2024-01-01T12:00:00.000000Z'
            ]
        ]
    ])]
    #[Response(404, 'User not found', [
        'message' => 'User not found'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(403, 'Access denied', [
        'message' => 'Access denied. Admin role required.'
    ])]
    public function entitlements(string $id): JsonResponse
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
    #[OperationId('assignUserEntitlement')]
    #[Summary('Assign entitlement to user')]
    #[Description('Assign an entitlement to a user with checks for existing assignments and audit logging.')]
    #[Response(200, 'Entitlement assigned successfully', [
        'message' => 'Entitlement assigned successfully'
    ])]
    #[Response(400, 'Entitlement already assigned', [
        'message' => 'User already has this entitlement'
    ])]
    #[Response(404, 'User or entitlement not found', [
        'message' => 'User not found'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(403, 'Access denied', [
        'message' => 'Access denied. Admin role required.'
    ])]
    public function assignEntitlement(Request $request, string $userId, string $entitlementId): JsonResponse
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
    #[OperationId('removeUserEntitlement')]
    #[Summary('Remove entitlement from user')]
    #[Description('Remove an entitlement from a user with checks for existing assignments and audit logging.')]
    #[Response(200, 'Entitlement removed successfully', [
        'message' => 'Entitlement removed successfully'
    ])]
    #[Response(400, 'Entitlement not assigned', [
        'message' => 'User does not have this entitlement'
    ])]
    #[Response(404, 'User or entitlement not found', [
        'message' => 'User not found'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(403, 'Access denied', [
        'message' => 'Access denied. Admin role required.'
    ])]
    public function removeEntitlement(Request $request, string $userId, string $entitlementId): JsonResponse
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
