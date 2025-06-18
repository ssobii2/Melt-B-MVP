<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
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

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
            ]
        ], 201);
    }

    /**
     * Authenticate user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Check if user exists and password is correct
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'The provided credentials do not match our records.',
            ], 401);
        }

        // Revoke existing tokens if requested (for security)
        if ($request->boolean('revoke_existing', false)) {
            $user->tokens()->delete();
        }

        // Create API token - always token-based now
        $tokenName = $request->input('token_name', 'Login Token');
        $token = $user->createToken($tokenName);

        // Log the successful login
        AuditLog::createEntry(
            userId: $user->id,
            action: 'user_login',
            targetType: 'user',
            targetId: $user->id,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
            ],
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * Logout user and revoke current token.
     */
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
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'contact_info' => $user->contact_info,
            ]
        ]);
    }

    /**
     * Send password reset link.
     */
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
     * Generate API token for the authenticated user.
     */
    public function generateApiToken(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            if (!$request->hasHeader('Authorization')) {
                return response()->json([
                    'message' => 'Token generation endpoint is working. Authentication required.',
                    'error' => 'authentication_required',
                    'hint' => 'Add "Authorization: Bearer {token}" header to generate additional tokens.'
                ], 401);
            }
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Revoke existing tokens if needed
        if ($request->boolean('revoke_existing')) {
            $user->tokens()->delete();
        }

        $token = $user->createToken($request->name);

        // Log the token generation
        AuditLog::createEntry(
            userId: $user->id,
            action: 'api_token_generated',
            targetType: 'user',
            targetId: $user->id,
            newValues: ['token_name' => $request->name],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'API token generated successfully.',
            'token' => $token->plainTextToken,
            'name' => $request->name,
        ]);
    }

    /**
     * Revoke API tokens for the authenticated user.
     */
    public function revokeApiTokens(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            if (!$request->hasHeader('Authorization')) {
                return response()->json([
                    'message' => 'Token revocation endpoint is working. Authentication required.',
                    'error' => 'authentication_required',
                    'hint' => 'Add "Authorization: Bearer {token}" header to revoke all tokens.'
                ], 401);
            }
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $tokenCount = $user->tokens()->count();
        $user->tokens()->delete();

        // Log the token revocation
        AuditLog::createEntry(
            userId: $user->id,
            action: 'api_tokens_revoked',
            targetType: 'user',
            targetId: $user->id,
            oldValues: ['token_count' => $tokenCount],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => "All API tokens revoked successfully. ({$tokenCount} tokens removed)"
        ]);
    }
}
