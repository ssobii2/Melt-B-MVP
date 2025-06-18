<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureAdminToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if admin token exists in session
        $token = $request->session()->get('admin_token');
        $userId = $request->session()->get('admin_user_id');

        if (!$token || !$userId) {
            return redirect()->route('admin.login');
        }

        // Verify token is valid
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken || $accessToken->tokenable_id != $userId) {
            $request->session()->forget(['admin_token', 'admin_user_id']);
            return redirect()->route('admin.login');
        }

        // Verify user is admin
        $user = User::find($userId);
        if (!$user || !$user->isAdmin()) {
            $request->session()->forget(['admin_token', 'admin_user_id']);
            return redirect()->route('admin.login');
        }

        // Set user for the request and Auth facade
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Also set for Auth facade so AdminLTE can access user
        Auth::setUser($user);

        return $next($request);
    }
}
