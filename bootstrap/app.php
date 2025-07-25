<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Exclude API routes from CSRF protection
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        
        // Web middleware for React SPA (no Inertia needed)
        $middleware->web(append: [
            \App\Http\Middleware\HandleUnauthenticatedApiRequests::class,
        ]);

        // Configure Sanctum middleware for API authentication
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Add aliases for easier use
        $middleware->alias([
            'auth.admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'auth.api' => \App\Http\Middleware\HandleUnauthenticatedApiRequests::class,
            'check.entitlements' => \App\Http\Middleware\CheckEntitlementsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle authentication exceptions for API routes
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated. Please provide a valid Bearer token.',
                    'error' => 'authentication_required'
                ], 401);
            }
            
            // For admin routes, redirect to admin login
            if ($request->is('admin/*')) {
                return redirect()->route('admin.login');
            }
            
            // For other web routes, redirect to React SPA (which handles login)
            return redirect('/');
        });
    })
    ->create();
