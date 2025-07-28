<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Resources\AuditLogResource;
use App\Models\User;
use App\Models\Dataset;
use App\Models\Building;
use App\Models\Entitlement;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\RequestBody;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;

#[Tag('Admin Dashboard')]
class DashboardController extends Controller
{
    /**
     * Display the admin dashboard with statistics and recent activity
     */
    #[OperationId('adminDashboard')]
    #[Summary('Admin dashboard')]
    #[Description('Retrieve dashboard statistics including user counts, dataset information, and recent activity.')]
    #[Response(200, 'Dashboard data retrieved', [
        'stats' => [
            'total_users' => 150,
            'active_users' => 120,
            'total_datasets' => 5,
            'total_buildings' => 50000,
            'recent_logins' => 25
        ],
        'recent_activity' => [
            [
                'id' => 1,
                'action' => 'user_login',
                'user_name' => 'John Doe',
                'created_at' => '2024-01-01T12:00:00.000000Z'
            ]
        ]
    ])]
    #[Response(401, 'Not authenticated', [
        'message' => 'Unauthenticated'
    ])]
    #[Response(403, 'Access denied', [
        'message' => 'Access denied. Admin role required.'
    ])]
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
            'recent_registrations' => UserResource::collection(
                User::latest()
                    ->take(5)
                    ->get(['id', 'name', 'email', 'role', 'created_at'])
            ),
            'recent_audit_logs' => AuditLogResource::collection(
                AuditLog::with('user:id,name,email')
                    ->latest()
                    ->take(10)
                    ->get()
            ),
        ];

        return view('admin.dashboard', compact('stats', 'currentUser'));
    }

    /**
     * Show the admin login form.
     */
    #[OperationId('showAdminLoginForm')]
    #[Summary('Show admin login form')]
    #[Description('Display the admin login form or redirect to dashboard if already authenticated.')]
    #[Response(200, 'Login form displayed')]
    #[Response(302, 'Already authenticated - redirect to dashboard')]
    public function showLoginForm(Request $request)
    {
        // Check if admin is already authenticated via Laravel's admin guard
        if (Auth::guard('admin')->check()) {
            $user = Auth::guard('admin')->user();
            if ($user && $user->isAdmin()) {
                return redirect()->route('admin.dashboard');
            }
            // If user is not admin, logout from admin guard
            Auth::guard('admin')->logout();
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login using Laravel's session authentication.
     */
    #[OperationId('adminLogin')]
    #[Summary('Admin login')]
    #[Description('Authenticate admin user and create session with API token.')]
    #[RequestBody([
        'email' => 'string|required|Admin email address',
        'password' => 'string|required|Admin password'
    ])]
    #[Response(302, 'Login successful - redirect to dashboard')]
    #[Response(422, 'Invalid credentials', [
        'errors' => [
            'email' => ['The provided credentials do not match our records.']
        ]
    ])]
    #[Response(403, 'Access denied', [
        'errors' => [
            'email' => ['Access denied. Admin privileges required.']
        ]
    ])]
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Attempt authentication with admin guard
        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('admin')->user();
            
            // Check if user is admin
            if (!$user->isAdmin()) {
                // Log failed admin access attempt
                AuditLog::createEntry(
                    userId: $user->id,
                    action: 'admin_login_failed',
                    targetType: 'user',
                    targetId: $user->id,
                    newValues: [
                        'email' => $credentials['email'],
                        'reason' => 'insufficient_privileges'
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                // Logout from admin guard since user is not admin
                Auth::guard('admin')->logout();
                
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

        // Log failed admin login attempt
        $user = User::where('email', $credentials['email'])->first();
        AuditLog::createEntry(
            userId: $user?->id,
            action: 'admin_login_failed',
            targetType: 'user',
            targetId: $user?->id,
            newValues: [
                'email' => $credentials['email'],
                'reason' => !$user ? 'user_not_found' : 'invalid_password'
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Handle admin logout using Laravel's session authentication.
     */
    #[OperationId('adminLogout')]
    #[Summary('Admin logout')]
    #[Description('Logout admin user, revoke tokens and clear session.')]
    #[Response(302, 'Logout successful - redirect to login')]
    public function logout(Request $request)
    {
        // Get admin user before logout
        $user = Auth::guard('admin')->user();

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

            // Revoke the admin token if it exists
            if ($request->session()->has('admin_token')) {
                // Find and revoke the admin-dashboard token
                $user->tokens()->where('name', 'admin-dashboard')->delete();
            }
        }

        // Logout from admin guard
        Auth::guard('admin')->logout();
        
        // Clear admin token from session
        $request->session()->forget('admin_token');
        
        // Regenerate session for security
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
