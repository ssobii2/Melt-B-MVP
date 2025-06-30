<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Dataset;
use App\Models\Building;
use App\Models\Entitlement;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index(Request $request)
    {
        // Get current user from Laravel's authentication
        $currentUser = Auth::user();

        // Get dashboard statistics
        $stats = [
            'total_users' => User::count(),
            'total_datasets' => Dataset::count(),
            'total_buildings' => Building::count(),
            'total_entitlements' => Entitlement::count(),
            'users_by_role' => User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->pluck('count', 'role')
                ->toArray(),
            'recent_registrations' => User::latest()
                ->take(5)
                ->get(['id', 'name', 'email', 'role', 'created_at']),
            'recent_audit_logs' => AuditLog::with('user:id,name,email')
                ->latest()
                ->take(10)
                ->get(),
        ];

        return view('admin.dashboard', compact('stats', 'currentUser'));
    }

    /**
     * Show the admin login form.
     */
    public function showLoginForm(Request $request)
    {
        // If already logged in and user is admin, redirect to dashboard
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login using Laravel's session authentication.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Attempt to authenticate using Laravel's built-in authentication
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // Check if user is admin
            if (!$user->isAdmin()) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Access denied. Admin privileges required.',
                ]);
            }

            // Regenerate session for security
            $request->session()->regenerate();

            // Create a Sanctum token for API calls from admin interface
            $token = $user->createToken('admin-dashboard')->plainTextToken;
            $request->session()->put('admin_token', $token);

            // Log the admin login
            AuditLog::createEntry(
                userId: $user->id,
                action: 'admin_login',
                targetType: 'user',
                targetId: $user->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return redirect()->intended('/admin/dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Handle admin logout using Laravel's session authentication.
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            // Log the admin logout
            AuditLog::createEntry(
                userId: $user->id,
                action: 'admin_logout',
                targetType: 'user',
                targetId: $user->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        // Revoke the admin token if it exists
        if ($user && $request->session()->has('admin_token')) {
            // Find and revoke the admin-dashboard token
            $user->tokens()->where('name', 'admin-dashboard')->delete();
            $request->session()->forget('admin_token');
        }

        // Log out using Laravel's built-in authentication
        Auth::logout();
        
        // Invalidate session for security
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
