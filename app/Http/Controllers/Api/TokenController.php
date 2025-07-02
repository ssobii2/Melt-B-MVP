<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;

#[Tag('API Tokens')]
class TokenController extends Controller
{
    /**
     * Generate a new API token for the authenticated user
     */
    #[OperationId('generateApiToken')]
    #[Summary('Generate API token')]
    #[Description('Generate a new API token for the authenticated user. Only one API token per user is allowed.')]
    #[Response(200, 'Token generated successfully', [
        'token' => '1|abcdef123456789...',
        'message' => 'API token generated successfully'
    ])]
    #[Response(400, 'Token already exists', [
        'message' => 'You already have an active API token. Please revoke it before generating a new one.'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
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
    #[OperationId('listApiTokens')]
    #[Summary('List API tokens')]
    #[Description('Retrieve all API tokens for the authenticated user (excludes login and admin tokens).')]
    #[Response(200, 'Tokens retrieved successfully', [
        'tokens' => [
            [
                'id' => 1,
                'name' => 'API Token - 2024-01-01 12:00:00',
                'created_at' => '2024-01-01T12:00:00.000000Z',
                'last_used_at' => '2024-01-01T13:00:00.000000Z'
            ]
        ]
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
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
    #[OperationId('revokeApiToken')]
    #[Summary('Revoke API token')]
    #[Description('Revoke a specific API token by its ID.')]
    #[Response(200, 'Token revoked successfully', [
        'message' => 'API token revoked successfully'
    ])]
    #[Response(404, 'Token not found', [
        'message' => 'Token not found'
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
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