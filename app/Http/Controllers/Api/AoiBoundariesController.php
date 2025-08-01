<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Services\UserEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;

#[Tag('AOI Boundaries')]
class AoiBoundariesController extends Controller
{
    protected UserEntitlementService $entitlementService;

    public function __construct(UserEntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    /**
     * Get AOI boundaries accessible to the authenticated user.
     */
    #[OperationId('aoi-boundaries.getBoundaries')]
    #[Summary('Get user AOI boundaries')]
    #[Description('Get all AOI boundaries that the authenticated user has access to through their entitlements. Returns AOI geometries from DS-AOI and TILES entitlements, as well as all AOI geometries from datasets where user has DS-ALL access.')]
    #[ScrambleResponse(200, 'AOI boundaries as GeoJSON', [
        'type' => 'FeatureCollection',
        'features' => [
            [
                'type' => 'Feature',
                'properties' => [
                    'entitlement_id' => 1,
                    'type' => 'DS-AOI',
                    'dataset_name' => 'Thermal Dataset 2024'
                ],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[[-1.0, 51.0], [-1.0, 52.0], [0.0, 52.0], [0.0, 51.0], [-1.0, 51.0]]]
                ]
            ]
        ]
    ])]
    public function getBoundaries(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Get user entitlements
        $entitlements = $this->entitlementService->getUserEntitlements($user);
        
        // Get datasets where user has DS-ALL access
        $dsAllDatasets = $entitlements->where('type', 'DS-ALL')->pluck('dataset_id')->toArray();
        
        // Get AOI entitlements that user has direct access to
        $userAoiEntitlements = $entitlements->whereIn('type', ['DS-AOI', 'TILES'])
            ->whereNotNull('aoi_geom');
        
        // Get all AOI entitlements from DS-ALL datasets
        $dsAllAoiEntitlements = collect();
        if (!empty($dsAllDatasets)) {
            $dsAllAoiEntitlements = Entitlement::with('dataset:id,name')
                ->whereIn('type', ['DS-AOI', 'TILES'])
                ->whereIn('dataset_id', $dsAllDatasets)
                ->whereNotNull('aoi_geom')
                ->get();
        }
        
        // Merge and deduplicate entitlements
        $allAoiEntitlements = $userAoiEntitlements->merge($dsAllAoiEntitlements)
            ->unique('id');
        
        // Convert to GeoJSON features
        $features = $allAoiEntitlements->map(function ($entitlement) {
            return [
                'type' => 'Feature',
                'properties' => [
                    'entitlement_id' => $entitlement->id,
                    'type' => $entitlement->type,
                    'dataset_name' => $entitlement->dataset->name ?? 'Unknown Dataset'
                ],
                'geometry' => $entitlement->aoi_geom ? $entitlement->aoi_geom->toArray() : null
            ];
        })->filter(function ($feature) {
            return $feature['geometry'] !== null;
        })->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features
        ]);
    }
} 