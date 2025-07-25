<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use App\Http\Resources\UserResource;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\RequestBody;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;

#[Tag('Authentication')]
class AuthController extends Controller
{
    protected UserEntitlementService $entitlementService;

    public function __construct(UserEntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }
    /**
     * Register a new user.
     */
    #[OperationId('register')]
    #[Summary('Register a new user')]
    #[Description('Create a new user account with email verification.')]
    #[RequestBody([
        'name' => 'string|required|max:255',
        'email' => 'string|required|email|max:255|unique:users',
        'password' => 'string|required|confirmed',
        'password_confirmation' => 'string|required',
        'role' => 'string|optional|in:user,researcher,contractor,municipality'
    ])]
    #[Response(201, 'User registered successfully', [
        'message' => 'Registration successful. Please verify your email address.',
        'user' => [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'user',
            'email_verified_at' => null
        ]
    ])]
    #[Response(422, 'Validation failed', [
        'message' => 'Validation failed',
        'errors' => [
            'email' => ['The email has already been taken.']
        ]
    ])]
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'role' => ['sometimes', 'string', 'in:user,researcher,contractor,municipality'],
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
            'role' => $request->role ?? 'user',
        ]);

        // Trigger email verification
        event(new Registered($user));

        // Log the registration
        AuditLog::createEntry(
            userId: $user->id,
            action: 'user_registered',
            targetType: 'user',
            targetId: $user->id,
            newValues: ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Registration successful. Please verify your email address.',
            'user' => new UserResource($user)
        ], 201);
    }

    /**
     * Authenticate user and create token.
     */
    #[OperationId('login')]
    #[Summary('Authenticate user')]
    #[Description('Login with email and password to receive an API token.')]
    #[RequestBody([
        'email' => 'string|required|email',
        'password' => 'string|required',
        'token_name' => 'string|optional',
        'revoke_existing' => 'boolean|optional'
    ])]
    #[Response(200, 'Login successful', [
        'message' => 'Login successful',
        'user' => [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'user',
            'email_verified_at' => '2024-01-01T00:00:00.000000Z'
        ],
        'token' => '1|abcdef123456...',
        'token_type' => 'Bearer'
    ])]
    #[Response(401, 'Invalid credentials', [
        'message' => 'The provided credentials do not match our records.'
    ])]
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Check if user exists and password is correct
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            // Log the failed login attempt
            AuditLog::createEntry(
                userId: $user?->id, // null if user doesn't exist
                action: 'user_login_failed',
                targetType: 'user',
                targetId: $user?->id,
                newValues: [
                    'email' => $credentials['email'],
                    'reason' => !$user ? 'user_not_found' : 'invalid_password'
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        }

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            // Log the failed login attempt due to unverified email
            AuditLog::createEntry(
                userId: $user->id,
                action: 'user_login_failed',
                targetType: 'user',
                targetId: $user->id,
                newValues: [
                    'email' => $credentials['email'],
                    'reason' => 'email_not_verified'
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'message' => 'Please verify your email address before logging in.',
                'error' => 'email_not_verified'
            ], 403);
        }

        // Revoke existing tokens if requested (for security)
        if ($request->boolean('revoke_existing', false)) {
            $user->tokens()->delete();
        }

        // Create API token - always token-based now
        $tokenName = $request->input('token_name', 'Login Token');
        $token = $user->createToken($tokenName);

        // Log the login
        AuditLog::createEntry(
            userId: $user->id,
            action: 'user_login',
            targetType: 'user',
            targetId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // Note: Entitlements are now loaded lazily when needed to avoid login performance issues

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * Logout user and revoke current token.
     */
    #[OperationId('logout')]
    #[Summary('Logout user')]
    #[Description('Revoke the current API token and logout the user.')]
    #[Response(200, 'Logout successful', [
        'message' => 'Logout successful - token revoked'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Not authenticated'
    ])]
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            if (!$request->hasHeader('Authorization')) {
                return response()->json([
                    'message' => 'Logout endpoint is working. Authentication required.',
                    'error' => 'authentication_required',
                    'hint' => 'Add "Authorization: Bearer {token}" header to logout.'
                ], 401);
            }
            return response()->json([
                'message' => 'Not authenticated'
            ], 401);
        }

        // Log the logout
        AuditLog::createEntry(
            userId: $user->id,
            action: 'user_logout',
            targetType: 'user',
            targetId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful - token revoked'
        ]);
    }

    /**
     * Get authenticated user details.
     */
    #[OperationId('getUser')]
    #[Summary('Get current user')]
    #[Description('Retrieve the authenticated user\'s profile information.')]
    #[Response(200, 'User details retrieved', [
        'user' => [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'user',
            'email_verified_at' => '2024-01-01T00:00:00.000000Z',
            'contact_info' => null
        ]
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            // Better message for browser access
            if (!$request->hasHeader('Authorization')) {
                return response()->json([
                    'message' => 'API endpoint is working. Authentication required.',
                    'error' => 'authentication_required',
                    'hint' => 'Add "Authorization: Bearer {token}" header to access this endpoint.'
                ], 401);
            }

            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Send password reset link.
     */
    #[OperationId('forgotPassword')]
    #[Summary('Send password reset link')]
    #[Description('Send a password reset link to the user\'s email address.')]
    #[RequestBody([
        'email' => 'string|required|email'
    ])]
    #[Response(200, 'Reset link sent', [
        'message' => 'Password reset link sent to your email.'
    ])]
    #[Response(400, 'Unable to send reset link', [
        'message' => 'Unable to send password reset link.',
        'error' => 'passwords.user'
    ])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Password reset link sent to your email.']);
        }

        return response()->json([
            'message' => 'Unable to send password reset link.',
            'error' => __($status)
        ], 400);
    }

    /**
     * Reset password using token.
     */
    #[OperationId('resetPassword')]
    #[Summary('Reset password')]
    #[Description('Reset user password using the token received via email.')]
    #[RequestBody([
        'token' => 'string|required',
        'email' => 'string|required|email',
        'password' => 'string|required|confirmed',
        'password_confirmation' => 'string|required'
    ])]
    #[Response(200, 'Password reset successful', [
        'message' => 'Password has been reset successfully.'
    ])]
    #[Response(400, 'Unable to reset password', [
        'message' => 'Unable to reset password.',
        'error' => 'passwords.token'
    ])]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Log the password reset
                AuditLog::createEntry(
                    userId: $user->id,
                    action: 'password_reset',
                    targetType: 'user',
                    targetId: $user->id,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password has been reset successfully.']);
        }

        return response()->json([
            'message' => 'Unable to reset password.',
            'error' => __($status)
        ], 400);
    }



    /**
     * Update user profile information.
     */
    #[OperationId('updateProfile')]
    #[Summary('Update user profile')]
    #[Description('Update the authenticated user\'s profile information.')]
    #[RequestBody([
        'name' => 'string|required|max:255',
        'email' => 'string|required|email|max:255|unique:users,email,{user_id}'
    ])]
    #[Response(200, 'Profile updated successfully', [
        'message' => 'Profile updated successfully.',
        'user' => [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'user',
            'email_verified_at' => '2024-01-01T00:00:00.000000Z'
        ]
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(422, 'Validation failed', [
        'message' => 'The given data was invalid.',
        'errors' => [
            'email' => ['The email has already been taken.']
        ]
    ])]
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
        ];

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        // Log the profile update
        AuditLog::createEntry(
            userId: $user->id,
            action: 'profile_updated',
            targetType: 'user',
            targetId: $user->id,
            oldValues: $oldValues,
            newValues: [
                'name' => $user->name,
                'email' => $user->email,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Update user password.
     */
    #[OperationId('updatePassword')]
    #[Summary('Update user password')]
    #[Description('Update the authenticated user\'s password.')]
    #[RequestBody([
        'current_password' => 'string|required',
        'password' => 'string|required|confirmed',
        'password_confirmation' => 'string|required'
    ])]
    #[Response(200, 'Password updated successfully', [
        'message' => 'Password updated successfully.'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(422, 'Current password incorrect', [
        'message' => 'The current password is incorrect.',
        'errors' => [
            'current_password' => ['The current password is incorrect.']
        ]
    ])]
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.']
                ]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Log the password change
        AuditLog::createEntry(
            userId: $user->id,
            action: 'password_changed',
            targetType: 'user',
            targetId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Password updated successfully.'
        ]);
    }

    /**
     * Send email verification notification.
     */
    #[OperationId('sendEmailVerification')]
    #[Summary('Send email verification')]
    #[Description('Send email verification notification to the authenticated user.')]
    #[Response(200, 'Verification email sent', [
        'message' => 'Verification email sent successfully.'
    ])]
    #[Response(400, 'Email already verified', [
        'message' => 'Email address is already verified.'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    public function sendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email address is already verified.'
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        // Log the verification email sent
        AuditLog::createEntry(
            userId: $user->id,
            action: 'email_verification_sent',
            targetType: 'user',
            targetId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Verification email sent successfully.'
        ]);
    }

    /**
     * Verify email address.
     */
    #[OperationId('verifyEmail')]
    #[Summary('Verify email address')]
    #[Description('Verify user email address using the verification link.')]
    #[RequestBody([
        'id' => 'string|required',
        'hash' => 'string|required'
    ])]
    #[Response(200, 'Email verified successfully', [
        'message' => 'Email verified successfully.'
    ])]
    #[Response(400, 'Invalid verification link', [
        'message' => 'Invalid verification link.'
    ])]
    #[Response(404, 'User not found', [
        'message' => 'User not found.'
    ])]
    public function verifyEmail(Request $request, $id = null, $hash = null)
    {
        // Handle both API requests (JSON) and web requests (URL parameters)
        $userId = $id ?? $request->input('id');
        $hashValue = $hash ?? $request->input('hash');

        if (!$userId || !$hashValue) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Missing verification parameters.'
                ], 400);
            }
            return redirect()->to(config('app.url') . '/email-verification-result?error=' . urlencode('Missing verification parameters.'));
        }

        try {
            $user = User::findOrFail($userId);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'User not found.'
                ], 404);
            }
            return redirect()->to(config('app.url') . '/email-verification-result?error=' . urlencode('User not found.'));
        }

        if (!hash_equals((string) $hashValue, sha1($user->getEmailForVerification()))) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Invalid verification link.'
                ], 400);
            }
            return redirect()->to(config('app.url') . '/email-verification-result?error=' . urlencode('Invalid verification link.'));
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Email address is already verified.'
                ], 400);
            }
            return redirect()->to(config('app.url') . '/email-verification-result?success=true&message=' . urlencode('Email address is already verified.'));
        }

        if ($user->markEmailAsVerified()) {
            // Log the email verification
            AuditLog::createEntry(
                userId: $user->id,
                action: 'email_verified',
                targetType: 'user',
                targetId: $user->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Email verified successfully.'
            ]);
        }

        // For web requests, redirect to verification result page
        return redirect()->to(config('app.url') . '/email-verification-result?success=true');
    }
}
