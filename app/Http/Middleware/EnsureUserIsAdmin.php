<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is authenticated
        if (!$user) {
            // For AJAX requests, return JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated. Please login again.',
                    'error' => 'authentication_required'
                ], 401);
            }
            // For web requests, redirect to login
            return redirect()->route('admin.login');
        }

        // Check if user has admin role
        if (!$user->isAdmin()) {
            // For AJAX requests, return JSON
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Access denied. Admin privileges required.',
                    'error' => 'access_denied'
                ], 403);
            }
            // For web requests, redirect to login with error
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Access denied. Admin privileges required.'
            ]);
        }

        return $next($request);
    }
}
