<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\UserEntitlementService;

class CheckEntitlementsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If no user is authenticated, let the auth middleware handle it
        if (!$user) {
            return $next($request);
        }

        // Get user entitlements and generate filters
        $entitlementService = app(UserEntitlementService::class);
        $entitlements = $entitlementService->getUserEntitlements($user);
        $filters = $entitlementService->generateEntitlementFilters($entitlements);

        // Store filters in request for use by controllers
        $request->merge(['entitlement_filters' => $filters]);

        // Store original user entitlements for additional checks
        $request->merge(['user_entitlements' => $entitlements]);

        return $next($request);
    }
}
