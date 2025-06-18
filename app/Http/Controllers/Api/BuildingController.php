<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BuildingController extends Controller
{
    protected UserEntitlementService $entitlementService;

    public function __construct(UserEntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    /**
     * Get filtered buildings based on user's entitlements.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        // Get entitlement filters from middleware
        $entitlementFilters = $request->input('entitlement_filters', []);

        // Check if user has any data access
        if (
            empty($entitlementFilters['ds_all_datasets']) &&
            empty($entitlementFilters['ds_aoi_polygons']) &&
            empty($entitlementFilters['ds_building_gids'])
        ) {
            return response()->json([
                'message' => 'No data access authorized',
                'buildings' => [],
                'meta' => [
                    'total' => 0,
                    'per_page' => $request->input('per_page', 15),
                    'current_page' => 1
                ]
            ]);
        }

        // Start building query
        $query = Building::query()->with('dataset:id,name,data_type');

        // Apply entitlement filters
        $query->where(function ($filterQuery) use ($entitlementFilters) {
            $filterQuery->applyEntitlementFilters($entitlementFilters);
        });

        // Apply additional filters
        $datasetId = $request->input('dataset_id');
        if ($datasetId) {
            $query->forDataset($datasetId);
        }

        $minTli = $request->input('min_tli');
        $maxTli = $request->input('max_tli');
        if ($minTli !== null || $maxTli !== null) {
            $query->withTliRange($minTli, $maxTli);
        }

        $search = $request->input('search');
        if ($search) {
            $query->search($search);
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'thermal_loss_index_tli');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['thermal_loss_index_tli', 'co2_savings_estimate', 'building_type_classification'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Get paginated results
        $perPage = min($request->input('per_page', 15), 100); // Max 100 per page
        $buildings = $query->paginate($perPage);

        // Add calculated attributes
        $buildings->getCollection()->transform(function ($building) {
            $building->tli_color = $building->tli_color;
            $building->improvement_potential = $building->improvement_potential;
            return $building;
        });

        return response()->json($buildings);
    }

    /**
     * Get details of a specific building.
     */
    public function show(Request $request, string $gid): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        // Get entitlement filters from middleware
        $entitlementFilters = $request->input('entitlement_filters', []);

        // Start building query with entitlement filters
        $query = Building::query()->with('dataset');

        // Apply entitlement filters
        $query->where(function ($filterQuery) use ($entitlementFilters) {
            $filterQuery->applyEntitlementFilters($entitlementFilters);
        });

        // Find the specific building
        $building = $query->where('gid', $gid)->first();

        if (!$building) {
            return response()->json(['message' => 'Building not found or access denied'], 404);
        }

        // Add calculated attributes
        $building->tli_color = $building->tli_color;
        $building->improvement_potential = $building->improvement_potential;

        return response()->json([
            'building' => $building
        ]);
    }

    /**
     * Get buildings within a specific geographic area (bounding box).
     */
    public function withinBounds(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        // Validate bounding box parameters
        $request->validate([
            'north' => 'required|numeric|between:-90,90',
            'south' => 'required|numeric|between:-90,90',
            'east' => 'required|numeric|between:-180,180',
            'west' => 'required|numeric|between:-180,180',
        ]);

        $north = $request->input('north');
        $south = $request->input('south');
        $east = $request->input('east');
        $west = $request->input('west');

        // Create bounding box polygon
        $bbox = "POLYGON(({$west} {$south}, {$east} {$south}, {$east} {$north}, {$west} {$north}, {$west} {$south}))";

        // Get entitlement filters from middleware
        $entitlementFilters = $request->input('entitlement_filters', []);

        // Start building query
        $query = Building::query()->with('dataset:id,name,data_type');

        // Apply entitlement filters
        $query->where(function ($filterQuery) use ($entitlementFilters) {
            $filterQuery->applyEntitlementFilters($entitlementFilters);
        });

        // Apply bounding box filter
        $query->withinGeometry($bbox);

        // Limit results for performance
        $limit = min($request->input('limit', 1000), 5000);
        $buildings = $query->limit($limit)->get();

        // Add calculated attributes
        $buildings->transform(function ($building) {
            $building->tli_color = $building->tli_color;
            return $building;
        });

        return response()->json([
            'buildings' => $buildings,
            'count' => $buildings->count(),
            'bbox' => [
                'north' => $north,
                'south' => $south,
                'east' => $east,
                'west' => $west
            ]
        ]);
    }

    /**
     * Get building statistics based on user's entitlements.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        // Get entitlement filters from middleware
        $entitlementFilters = $request->input('entitlement_filters', []);

        // Start building query
        $query = Building::query();

        // Apply entitlement filters
        $query->where(function ($filterQuery) use ($entitlementFilters) {
            $filterQuery->applyEntitlementFilters($entitlementFilters);
        });

        $stats = [
            'total_buildings' => $query->count(),
            'avg_tli' => round($query->avg('thermal_loss_index_tli'), 2),
            'avg_co2_savings' => round($query->avg('co2_savings_estimate'), 2),
            'tli_distribution' => [
                'high' => $query->clone()->withTliRange(80, 100)->count(),
                'medium_high' => $query->clone()->withTliRange(60, 79)->count(),
                'medium' => $query->clone()->withTliRange(40, 59)->count(),
                'medium_low' => $query->clone()->withTliRange(20, 39)->count(),
                'low' => $query->clone()->withTliRange(0, 19)->count(),
            ],
            'by_classification' => $query->clone()
                ->selectRaw('building_type_classification, COUNT(*) as count')
                ->groupBy('building_type_classification')
                ->pluck('count', 'building_type_classification')
        ];

        return response()->json($stats);
    }
}
