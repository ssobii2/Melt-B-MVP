<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    /**
     * Generate a new API token for the authenticated user
     */
    public function generate(Request $request)
    {
        $user = $request->user();
        
        // Check if user already has API tokens (excluding login and admin tokens)
        $existingApiTokens = $user->tokens()
            ->where('name', '!=', 'Login Token')
            ->where('name', '!=', 'admin-dashboard')
            ->count();
            
        if ($existingApiTokens > 0) {
            return response()->json([
                'message' => 'You already have an active API token. Please revoke it before generating a new one.'
            ], 400);
        }

        // Create new token with default name
        $tokenName = 'API Token - ' . now()->format('Y-m-d H:i:s');
        $token = $user->createToken($tokenName);

        // Log the token generation
        AuditLog::createEntry(
            userId: $user->id,
            action: 'api_token_generated',
            targetType: 'personal_access_token',
            targetId: $token->accessToken->id,
            newValues: ['token_name' => $tokenName],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'message' => 'API token generated successfully'
        ]);
    }

    /**
     * List all API tokens for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Filter out login and admin tokens - only show API tokens
        $tokens = $user->tokens()
            ->where('name', '!=', 'Login Token')
            ->where('name', '!=', 'admin-dashboard')
            ->select('id', 'name', 'created_at', 'last_used_at')
            ->get();

        return response()->json([
            'tokens' => $tokens
        ]);
    }

    /**
     * Revoke a specific API token
     */
    public function revoke(Request $request, $tokenId)
    {
        $user = $request->user();
        
        $token = $user->tokens()->where('id', $tokenId)->first();
        
        if (!$token) {
            return response()->json([
                'message' => 'Token not found'
            ], 404);
        }

        $tokenName = $token->name;
        $token->delete();

        // Log the token revocation
        AuditLog::createEntry(
            userId: $user->id,
            action: 'api_token_revoked',
            targetType: 'personal_access_token',
            targetId: $tokenId,
            oldValues: ['token_name' => $tokenName],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'API token revoked successfully'
        ]);
    }
}