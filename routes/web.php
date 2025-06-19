<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Http\Controllers\Admin\DashboardController;

Route::get('/', function () {
    return Inertia::render('Home', [
        'message' => 'MELT-B MVP - Thermal Analysis Platform is running successfully!',
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Add login route for Laravel's auth system (redirects to admin login)
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');

// Add password reset routes for Laravel's password reset system
Route::get('/password/reset', function () {
    return response()->json(['message' => 'Password reset not available via web interface. Use API: /api/forgot-password'], 404);
})->name('password.reset');

Route::get('/password/reset/{token}', function ($token) {
    return response()->json([
        'message' => 'Use API to reset password: /api/reset-password',
        'token' => $token
    ], 200);
})->name('password.reset.token');

// Admin routes for AdminLTE dashboard
Route::prefix('admin')->group(function () {
    // Admin authentication routes (no middleware)
    Route::get('/login', [DashboardController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [DashboardController::class, 'login'])->name('admin.login.post');

    // Redirect /admin to dashboard if authenticated and admin, otherwise to login
    Route::get('/', function () {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        return redirect()->route('admin.login');
    });

    // Protected admin routes
    Route::middleware(['admin.token'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/logout', [DashboardController::class, 'logout'])->name('admin.logout');

        // Admin management views
        Route::get('/users', function () {
            return view('admin.users');
        })->name('admin.users');

        Route::get('/datasets', function () {
            return view('admin.datasets');
        })->name('admin.datasets');

        Route::get('/entitlements', function () {
            return view('admin.entitlements');
        })->name('admin.entitlements');

        Route::get('/audit-logs', function () {
            return view('admin.audit-logs');
        })->name('admin.audit-logs');
    });
});

// Handle 404 for API routes
Route::fallback(function (Request $request) {
    if ($request->is('api/*')) {
        return response()->json([
            'message' => 'API endpoint not found',
            'error' => 'route_not_found'
        ], 404);
    }

    // For web routes, show React NotFound page
    return Inertia::render('NotFound');
})->name('fallback');
