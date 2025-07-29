<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        // Check if admin is authenticated via Laravel's admin guard
        if (!Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        // Get the authenticated admin user
        $user = Auth::guard('admin')->user();
        
        // Verify user is still admin (in case role changed)
        if (!$user || !$user->isAdmin()) {
            Auth::guard('admin')->logout();
            $request->session()->forget('admin_token');
            // Invalidate and regenerate session for security
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('admin.login');
        }

        // Set the admin guard as the default for this request to maintain consistency
        Auth::shouldUse('admin');

        return $next($request);
    }
}
