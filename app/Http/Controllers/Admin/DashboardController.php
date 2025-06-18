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
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index(Request $request)
    {
        // Get current user from session
        $userId = $request->session()->get('admin_user_id');
        $currentUser = User::find($userId);

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
        // If already logged in, redirect to dashboard
        $token = $request->session()->get('admin_token');
        $userId = $request->session()->get('admin_user_id');

        if ($token && $userId) {
            // Verify token is still valid
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken && $accessToken->tokenable_id == $userId) {
                $user = User::find($userId);
                if ($user && $user->isAdmin()) {
                    return redirect()->route('admin.dashboard');
                }
            }
            // If token is invalid, clear session
            $request->session()->forget(['admin_token', 'admin_user_id']);
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login and create token.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Check if user exists and password is correct
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        // Check if user is admin
        if (!$user->isAdmin()) {
            return back()->withErrors([
                'email' => 'Access denied. Admin privileges required.',
            ]);
        }

        // Create admin token and store in session for dashboard access
        $token = $user->createToken('Admin Dashboard Token');

        // Store token in session for dashboard use
        $request->session()->put('admin_token', $token->plainTextToken);
        $request->session()->put('admin_user_id', $user->id);
        $request->session()->regenerate();

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

    /**
     * Handle admin logout and revoke token.
     */
    public function logout(Request $request)
    {
        $token = $request->session()->get('admin_token');
        $userId = $request->session()->get('admin_user_id');

        if ($token && $userId) {
            // Find and revoke the token
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $accessToken->delete();
            }

            // Log the admin logout
            AuditLog::createEntry(
                userId: $userId,
                action: 'admin_logout',
                targetType: 'user',
                targetId: $userId,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        // Clear session
        $request->session()->forget(['admin_token', 'admin_user_id']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
