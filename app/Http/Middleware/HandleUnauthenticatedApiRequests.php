<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class HandleUnauthenticatedApiRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (AuthenticationException $e) {
            // For API routes, return JSON instead of redirecting
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated. Please login again.',
                    'error' => 'authentication_required'
                ], 401);
            }

            // For admin routes, redirect to admin login
            if ($request->is('admin/*')) {
                return redirect()->guest(route('admin.login'));
            }
            
            // For other web routes, redirect to React SPA (which handles login)
            return redirect('/');
        }
    }
}
