<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Api\AuthController;

// --- KEEP ALL ADMIN ROUTES AS THEY ARE ---

// Email verification routes
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');

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

    // Protected admin routes using Laravel's session authentication
    Route::middleware(['auth', 'auth.admin'])->group(function () {
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

        Route::get('/buildings', [\App\Http\Controllers\Admin\BuildingController::class, 'index'])->name('admin.buildings');

        Route::get('/analysis-jobs', function () {
            return view('admin.analysis-jobs');
        })->name('admin.analysis-jobs');
    });
});

// --- ADD THIS CATCH-ALL FOR THE REACT SPA ---
// This must be placed AFTER the admin routes.
// It will catch '/', '/dashboard', '/profile', etc., and load the React app.
// Exclude docs routes to allow Scramble documentation to work
Route::get('/{any?}', function () {
    return view('app'); // Or whatever the main Blade file for the React SPA is named
})->where('any', '^(?!docs).*')->name('react.spa');
