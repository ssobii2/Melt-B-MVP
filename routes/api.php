<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\EntitlementController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DatasetController;
use App\Http\Controllers\Admin\AnalysisJobController;
use App\Http\Controllers\Admin\TilesController as AdminTilesController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\TilesController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes (no middleware)
// Route::post('/register', [AuthController::class, 'register']); // Commented out to disable registration
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Health check endpoint (public)
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now(),
        'version' => '1.0.0',
        'message' => 'MELT-B MVP API is working correctly'
    ]);
});

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    // User authentication endpoints
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // API token management
    Route::post('/tokens/generate', [TokenController::class, 'generate']);
    
    // List user's API tokens
    Route::get('/tokens', [TokenController::class, 'index']);
    
    // Revoke API token
    Route::delete('/tokens/{token}', [TokenController::class, 'revoke']);

    // Profile management
    Route::put('/user/profile-information', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);

    // Email verification
    Route::post('/email/verification-notification', [AuthController::class, 'sendEmailVerification']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmail']);

    // Feedback system
    Route::get('/feedback/options', [FeedbackController::class, 'options']);
    Route::post('/feedback', [FeedbackController::class, 'store']);

    // User entitlements
    Route::get('/me/entitlements', function (Request $request) {
        $user = $request->user();

        if (!$user) {
            if (!$request->hasHeader('Authorization')) {
                return response()->json([
                    'message' => 'API endpoint is working. Authentication required.',
                    'error' => 'authentication_required',
                    'hint' => 'Add "Authorization: Bearer {token}" header to access this endpoint.'
                ], 401);
            }
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Add include_geometry to request for EntitlementResource
        $request->merge(['include_geometry' => '1']);

        $entitlements = $user->entitlements()
            ->with('dataset:id,name,data_type')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        // If user has DS-ALL access to any datasets, include all AOI entitlements from those datasets
        $dsAllDatasets = $entitlements->where('type', 'DS-ALL')->pluck('dataset_id')->toArray();
        
        if (!empty($dsAllDatasets)) {
            // Get all AOI entitlements from DS-ALL datasets that the user doesn't already have
            $existingEntitlementIds = $entitlements->pluck('id')->toArray();
            
            $additionalAoiEntitlements = \App\Models\Entitlement::with('dataset:id,name,data_type')
                ->whereIn('type', ['DS-AOI', 'TILES'])
                ->whereIn('dataset_id', $dsAllDatasets)
                ->whereNotIn('id', $existingEntitlementIds)
                ->whereNotNull('aoi_geom')
                ->get();
            
            // Merge the additional AOI entitlements with the user's direct entitlements
            $entitlements = $entitlements->merge($additionalAoiEntitlements);
        }

        return response()->json([
            'entitlements' => \App\Http\Resources\EntitlementResource::collection($entitlements)
        ]);
    });
});

// Protected data access routes with entitlement filtering
Route::middleware(['auth:sanctum', 'check.entitlements'])->group(function () {
    // Building data endpoints - specific routes first
    Route::get('/buildings/stats', [BuildingController::class, 'stats']);
    Route::get('/buildings/stats/within-bounds', [BuildingController::class, 'statsWithinBounds']);
    Route::get('/buildings/analytics/heat-loss', [BuildingController::class, 'heatLossAnalytics']);
    Route::get('/buildings/within/bounds', [BuildingController::class, 'withinBounds']);
    Route::get('/buildings/{gid}/find-page', [BuildingController::class, 'findPage']);
    Route::get('/buildings', [BuildingController::class, 'index']);
    Route::get('/buildings/{gid}', [BuildingController::class, 'show']);

    // Data download endpoints
    Route::get('/downloads/{id}', [DownloadController::class, 'download'])
        ->where(['id' => '[0-9]+']);
});

// Admin-only routes (same token, but checks user role)
Route::middleware(['auth:sanctum', 'auth.admin.api'])->prefix('admin')->group(function () {
    // System statistics
    Route::get('/stats', function (Request $request) {
        return response()->json([
            'total_users' => \App\Models\User::count(),
            'total_datasets' => \App\Models\Dataset::count(),
            'total_entitlements' => \App\Models\Entitlement::count(),
            'total_buildings' => \App\Models\Building::count(),
        ]);
    });

    // User management
    Route::apiResource('users', UserController::class);
    Route::get('/users/{id}/entitlements', [UserController::class, 'entitlements']);
    Route::post('/users/{userId}/entitlements/{entitlementId}', [UserController::class, 'assignEntitlement']);
    Route::delete('/users/{userId}/entitlements/{entitlementId}', [UserController::class, 'removeEntitlement']);
    Route::post('/users/{id}/verify-email', [UserController::class, 'verifyEmail']);

    // Entitlement management - specific routes first
    Route::get('/entitlements/all-aois', [EntitlementController::class, 'allAois']);
    Route::get('/entitlements/datasets', [EntitlementController::class, 'datasets']);
    Route::get('/entitlements/stats', [EntitlementController::class, 'stats']);
    Route::apiResource('entitlements', EntitlementController::class);

    // Dataset management - specific routes first
    Route::get('/datasets/stats', [DatasetController::class, 'stats']);
    Route::get('/datasets/data-types', [DatasetController::class, 'dataTypes']);
    Route::apiResource('datasets', DatasetController::class);

    // Audit log management - specific routes first
    Route::get('/audit-logs/stats', [AuditLogController::class, 'stats']);
    Route::get('/audit-logs/actions', [AuditLogController::class, 'actions']);
    Route::get('/audit-logs/target-types', [AuditLogController::class, 'targetTypes']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/{id}', [AuditLogController::class, 'show']);

    // Buildings management (read-only for admin)
    Route::get('/buildings/within/bounds', [\App\Http\Controllers\Admin\BuildingController::class, 'withinBounds']);
    Route::get('/buildings/{gid}/find-page', [\App\Http\Controllers\Admin\BuildingController::class, 'findPage']);
    Route::get('/buildings', [\App\Http\Controllers\Admin\BuildingController::class, 'index']);
    Route::post('/buildings/with-priority', [\App\Http\Controllers\Admin\BuildingController::class, 'withPriority']);
    Route::get('/buildings/{gid}', [\App\Http\Controllers\Admin\BuildingController::class, 'show']);
    Route::get('/buildings/export', [\App\Http\Controllers\Admin\BuildingController::class, 'export']);

    // Admin Analysis Jobs Management  
    Route::get('/analysis-jobs/stats', [AnalysisJobController::class, 'stats']);
    Route::apiResource('analysis-jobs', AnalysisJobController::class);
});

// Public webhook endpoints (no authentication required)
Route::prefix('webhooks')->group(function () {
    Route::post('/analysis-complete', [WebhookController::class, 'analysisComplete']);
    Route::get('/health', [WebhookController::class, 'healthCheck']);
    Route::post('/test', [WebhookController::class, 'test']);
});

// Protected tile endpoints with entitlement filtering
Route::middleware(['auth:sanctum', 'auth.api', 'check.entitlements'])->prefix('tiles')->group(function () {
    Route::get('/layers', [TilesController::class, 'getLayers']);
    Route::get('/{layer}/bounds', [TilesController::class, 'getBounds']);
    Route::get('/{layer}/{z}/{x}/{y}.png', [TilesController::class, 'serveTile']);
});

// Admin tile endpoints (unrestricted access for administrative oversight)
Route::middleware(['auth:sanctum', 'auth.admin.api'])->prefix('admin/tiles')->group(function () {
    Route::get('/layers', [AdminTilesController::class, 'getLayers']);
    Route::get('/{layer}/bounds', [AdminTilesController::class, 'getBounds']);
    Route::get('/{layer}/{z}/{x}/{y}.png', [AdminTilesController::class, 'serveTile']);
});
