<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTiles
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('sanctum')->check()) {
            return response()->json([
                'message' => 'Unauthenticated. Please login again.',
                'error' => 'authentication_required'
            ], 401);
        }

        // Set the user on the request so Auth::user() works in controllers
        $request->setUserResolver(function () {
            return Auth::guard('sanctum')->user();
        });

        return $next($request);
    }
} 