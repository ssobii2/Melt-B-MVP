<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\RequestBody;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;
use Dedoc\Scramble\Attributes\Parameters;

#[Tag('Buildings')]
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
    #[OperationId('getBuildings')]
    #[Summary('List buildings')]
    #[Description('Retrieve a paginated list of buildings filtered by user entitlements and optional query parameters.')]
    #[Parameters([
        'dataset_id' => 'integer|optional|Dataset ID to filter buildings',
        'anomaly_filter' => 'string|optional|Filter by anomaly status (true/false)',
        'type' => 'string|optional|Filter by building type',
        'search' => 'string|optional|Search term for buildings',
        'sort_by' => 'string|optional|Sort field (gid, is_anomaly, confidence, average_heatloss, co2_savings_estimate, building_type_classification)',
        'sort_order' => 'string|optional|Sort order (asc/desc)',
        'per_page' => 'integer|optional|Items per page (max 100)'
    ])]
    #[Response(200, 'Buildings retrieved successfully', [
        'data' => [
            [
                'gid' => 'B001',
                'is_anomaly' => true,
                'confidence' => 0.85,
                'average_heatloss' => 120.5,
                'co2_savings_estimate' => 2.3,
                'building_type_classification' => 'residential',
                'dataset' => [
                    'id' => 1,
                    'name' => 'Sample Dataset',
                    'data_type' => 'thermal'
                ]
            ]
        ],
        'meta' => [
            'total' => 100,
            'per_page' => 15,
            'current_page' => 1
        ]
    ])]
    #[Response(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
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
                'data' => [],
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

        // Anomaly filter
        $anomalyFilter = $request->input('anomaly_filter');
        if ($anomalyFilter !== null && $anomalyFilter !== '') {
            if ($anomalyFilter === 'true') {
                $query->where('is_anomaly', true);
            } elseif ($anomalyFilter === 'false') {
                $query->where('is_anomaly', false);
            }
        }

        // Building type filter (as specified in DATA.md)
        $type = $request->input('type');
        if ($type) {
            $query->byType($type);
        }

        // Search filter
        $search = $request->input('search');
        if ($search) {
            $query->search($search);
        }

        // Apply sorting (sort_by and sort_order as specified in DATA.md)
        $sortBy = $request->input('sort_by', 'gid');
        $sortOrder = $request->input('sort_order', 'asc');

        if (in_array($sortBy, ['gid', 'is_anomaly', 'confidence', 'average_heatloss', 'co2_savings_estimate', 'building_type_classification'])) {
            $query->orderBy($sortBy, $sortOrder);
            if ($sortBy !== 'gid') {
                $query->orderBy('gid', 'asc'); // Add secondary sort for stability
            }
        }

        // Get paginated results
        $perPage = min($request->input('per_page', 15), 100); // Max 100 per page
        $buildings = $query->paginate($perPage);

        // Transform using BuildingResource for clean JSON format
        return BuildingResource::collection($buildings)->response();
    }

    /**
     * Get details of a specific building.
     */
    #[OperationId('getBuilding')]
    #[Summary('Get building details')]
    #[Description('Retrieve detailed information about a specific building by its GID.')]
    #[Response(200, 'Building details retrieved', [
        'data' => [
            'gid' => 'B001',
            'is_anomaly' => true,
            'confidence' => 0.85,
            'average_heatloss' => 120.5,
            'co2_savings_estimate' => 2.3,
            'building_type_classification' => 'residential',
            'dataset' => [
                'id' => 1,
                'name' => 'Sample Dataset',
                'data_type' => 'thermal'
            ]
        ]
    ])]
    #[Response(404, 'Building not found', [
        'message' => 'Building not found or access denied'
    ])]
    #[Response(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
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

        // Return using BuildingResource for clean JSON format
        return response()->json([
            'data' => new BuildingResource($building)
        ]);
    }

    /**
     * Get buildings within a specific geographic area (bounding box).
     */
    #[OperationId('getBuildingsWithinBounds')]
    #[Summary('Get buildings within bounds')]
    #[Description('Retrieve buildings within a specified geographic bounding box.')]
    #[Parameters([
        'north' => 'number|required|Northern boundary latitude',
        'south' => 'number|required|Southern boundary latitude',
        'east' => 'number|required|Eastern boundary longitude',
        'west' => 'number|required|Western boundary longitude'
    ])]
    #[Response(200, 'Buildings within bounds retrieved', [
        'data' => [
            [
                'gid' => 'B001',
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'is_anomaly' => true,
                'confidence' => 0.85
            ]
        ]
    ])]
    #[Response(422, 'Invalid bounds parameters', [
        'message' => 'The given data was invalid.',
        'errors' => [
            'north' => ['The north field is required.']
        ]
    ])]
    #[Response(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
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

        // Validate coordinate relationships
        if ($north <= $south) {
            return response()->json([
                'message' => 'Invalid bounding box: north coordinate must be greater than south coordinate'
            ], 422);
        }

        if ($east <= $west) {
            return response()->json([
                'message' => 'Invalid bounding box: east coordinate must be greater than west coordinate'
            ], 422);
        }

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

        return response()->json([
            'data' => BuildingResource::collection($buildings),
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
     * Find the page number where a specific building appears in the filtered results.
     */
    #[OperationId('findBuildingPage')]
    #[Summary('Find building page')]
    #[Description('Find the page number where a specific building appears in filtered results.')]
    #[Parameters([
        'dataset_id' => 'integer|optional|Dataset ID to filter buildings',
        'anomaly_filter' => 'string|optional|Filter by anomaly status (true/false)',
        'type' => 'string|optional|Filter by building type',
        'search' => 'string|optional|Search term for buildings',
        'sort_by' => 'string|optional|Sort field',
        'sort_order' => 'string|optional|Sort order (asc/desc)',
        'per_page' => 'integer|optional|Items per page'
    ])]
    #[Response(200, 'Page number found', [
        'page' => 3,
        'per_page' => 15,
        'total' => 100
    ])]
    #[Response(404, 'Building not found', [
        'message' => 'Building not found in filtered results'
    ])]
    #[Response(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
    public function findPage(Request $request, string $gid): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        // Get entitlement filters from middleware
        $entitlementFilters = $request->input('entitlement_filters', []);

        // Start building query with same filters as index method
        $query = Building::query();

        // Apply entitlement filters
        $query->where(function ($filterQuery) use ($entitlementFilters) {
            $filterQuery->applyEntitlementFilters($entitlementFilters);
        });

        // Apply the same filters as the main listing
        $datasetId = $request->input('dataset_id');
        if ($datasetId) {
            $query->forDataset($datasetId);
        }

        $anomalyFilter = $request->input('anomaly_filter');
        if ($anomalyFilter !== null && $anomalyFilter !== '') {
            if ($anomalyFilter === 'true') {
                $query->where('is_anomaly', true);
            } elseif ($anomalyFilter === 'false') {
                $query->where('is_anomaly', false);
            }
        }

        $type = $request->input('type');
        if ($type) {
            $query->byType($type);
        }

        $search = $request->input('search');
        if ($search) {
            $query->search($search);
        }

        // Apply same sorting
        $sortBy = $request->input('sort_by', 'gid');
        $sortOrder = $request->input('sort_order', 'asc');

        if (in_array($sortBy, ['gid', 'is_anomaly', 'confidence', 'average_heatloss', 'co2_savings_estimate', 'building_type_classification'])) {
            $query->orderBy($sortBy, $sortOrder);
            if ($sortBy !== 'gid') {
                $query->orderBy('gid', 'asc'); // Add secondary sort for stability
            }
        }

        // Count buildings before the target building
        $perPage = min($request->input('per_page', 15), 100);
        
        // Create a subquery to get the position of the building
        $positionQuery = clone $query;
        $positionQuery->selectRaw('ROW_NUMBER() OVER (ORDER BY ' . $sortBy . ' ' . $sortOrder . ', gid asc) as position, gid');
        
        // Find the position of our target building
        $result = DB::table(DB::raw('(' . $positionQuery->toSql() . ') as ranked_buildings'))
            ->mergeBindings($positionQuery->getQuery())
            ->where('gid', $gid)
            ->first();

        if (!$result) {
            return response()->json(['message' => 'Building not found in current filter set'], 404);
        }

        $page = ceil($result->position / $perPage);

        return response()->json([
            'page' => $page,
            'position' => $result->position,
            'per_page' => $perPage
        ]);
    }

    /**
     * Get building statistics based on user's entitlements.
     */
    #[OperationId('getBuildingStats')]
    #[Summary('Get building statistics')]
    #[Description('Retrieve statistical information about buildings based on user entitlements.')]
    #[Parameters([
        'dataset_id' => 'integer|optional|Dataset ID to filter statistics'
    ])]
    #[Response(200, 'Building statistics retrieved', [
        'total_buildings' => 1500,
        'anomaly_buildings' => 150,
        'anomaly_percentage' => 10.0,
        'average_confidence' => 0.75,
        'building_types' => [
            'residential' => 800,
            'commercial' => 500,
            'industrial' => 200
        ],
        'datasets' => [
            [
                'id' => 1,
                'name' => 'Dataset 1',
                'building_count' => 750
            ]
        ]
    ])]
    #[Response(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
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
            'anomaly_buildings' => $query->clone()->where('is_anomaly', true)->count(),
            'normal_buildings' => $query->clone()->where('is_anomaly', false)->count(),
            'avg_confidence' => round($query->avg('confidence'), 2),
            'avg_co2_savings' => round($query->avg('co2_savings_estimate'), 2),
            'by_classification' => $query->clone()
                ->selectRaw('building_type_classification, COUNT(*) as count')
                ->groupBy('building_type_classification')
                ->pluck('count', 'building_type_classification')
        ];

        return response()->json($stats);
    }

    /**
     * Get building statistics within a specific geographic area (bounding box).
     */
    #[OperationId('getBuildingStatsWithinBounds')]
    #[Summary('Get building statistics within bounds')]
    #[Description('Retrieve statistical information about buildings within a specified geographic bounding box based on user entitlements.')]
    #[Parameters([
        'north' => 'number|required|Northern boundary latitude',
        'south' => 'number|required|Southern boundary latitude',
        'east' => 'number|required|Eastern boundary longitude',
        'west' => 'number|required|Western boundary longitude',
        'dataset_id' => 'integer|optional|Dataset ID to filter statistics'
    ])]
    #[Response(200, 'Building statistics within bounds retrieved', [
        'total_buildings' => 150,
        'anomaly_buildings' => 15,
        'normal_buildings' => 135,
        'avg_confidence' => 0.75,
        'total_co2_savings' => 2500.5,
        'avg_co2_savings' => 16.67,
        'by_classification' => [
            'residential' => 80,
            'commercial' => 50,
            'industrial' => 20
        ],
        'bbox' => [
            'north' => 40.8,
            'south' => 40.7,
            'east' => -73.9,
            'west' => -74.0
        ]
    ])]
    #[Response(422, 'Invalid bounds parameters', [
        'message' => 'The given data was invalid.',
        'errors' => [
            'north' => ['The north field is required.']
        ]
    ])]
    #[Response(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
    public function statsWithinBounds(Request $request): JsonResponse
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

        // Validate coordinate relationships
        if ($north <= $south) {
            return response()->json([
                'message' => 'Invalid bounding box: north coordinate must be greater than south coordinate'
            ], 422);
        }

        if ($east <= $west) {
            return response()->json([
                'message' => 'Invalid bounding box: east coordinate must be greater than west coordinate'
            ], 422);
        }

        // Create bounding box polygon
        $bbox = "POLYGON(({$west} {$south}, {$east} {$south}, {$east} {$north}, {$west} {$north}, {$west} {$south}))";

        // Get entitlement filters from middleware
        $entitlementFilters = $request->input('entitlement_filters', []);

        // Start building query
        $query = Building::query();

        // Apply entitlement filters
        $query->where(function ($filterQuery) use ($entitlementFilters) {
            $filterQuery->applyEntitlementFilters($entitlementFilters);
        });

        // Apply bounding box filter
        $query->withinGeometry($bbox);

        // Apply optional dataset filter
        $datasetId = $request->input('dataset_id');
        if ($datasetId) {
            $query->forDataset($datasetId);
        }

        // Calculate statistics
        $totalBuildings = $query->count();
        $anomalyBuildings = $query->clone()->where('is_anomaly', true)->count();
        $normalBuildings = $query->clone()->where('is_anomaly', false)->count();
        $avgConfidence = round($query->avg('confidence') ?? 0, 2);
        $totalCo2Savings = round($query->sum('co2_savings_estimate') ?? 0, 2);
        $avgCo2Savings = $totalBuildings > 0 ? round($totalCo2Savings / $totalBuildings, 2) : 0;
        
        $byClassification = $query->clone()
            ->selectRaw('building_type_classification, COUNT(*) as count')
            ->groupBy('building_type_classification')
            ->pluck('count', 'building_type_classification');

        $stats = [
            'total_buildings' => $totalBuildings,
            'anomaly_buildings' => $anomalyBuildings,
            'normal_buildings' => $normalBuildings,
            'avg_confidence' => $avgConfidence,
            'total_co2_savings' => $totalCo2Savings,
            'avg_co2_savings' => $avgCo2Savings,
            'by_classification' => $byClassification,
            'bbox' => [
                'north' => $north,
                'south' => $south,
                'east' => $east,
                'west' => $west
            ]
        ];

        return response()->json($stats);
    }

    /**
     * Get detailed heat loss analytics for buildings.
     */
    #[OperationId('getHeatLossAnalytics')]
    #[Summary('Get heat loss analytics')]
    #[Description('Retrieve detailed statistical analysis of heat loss data including distribution, percentiles, and comparison metrics.')]
    #[Parameters([
        'dataset_id' => 'integer|optional|Dataset ID to filter analytics',
        'building_type' => 'string|optional|Filter by building type for comparison',
        'building_gid' => 'string|optional|Specific building GID to highlight in analysis'
    ])]
    #[Response(200, 'Heat loss analytics retrieved', [
        'heat_loss_statistics' => [
            'mean' => 85.5,
            'median' => 82.3,
            'std_deviation' => 15.2,
            'min' => 45.1,
            'max' => 150.8,
            'percentiles' => [
                'p25' => 75.2,
                'p75' => 95.8,
                'p90' => 110.5,
                'p95' => 125.3
            ]
        ],
        'distribution' => [
            ['range' => '40-60', 'count' => 120],
            ['range' => '60-80', 'count' => 350],
            ['range' => '80-100', 'count' => 450]
        ],
        'building_comparison' => [
            'current_building' => [
                'gid' => 'B001',
                'heat_loss' => 95.2,
                'percentile_rank' => 75.5
            ]
        ]
    ])]
    #[Response(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
    public function heatLossAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        // Get entitlement filters from middleware
        $entitlementFilters = $request->input('entitlement_filters', []);

        // Start building query
        $query = Building::query()->whereNotNull('average_heatloss');

        // Apply entitlement filters
        $query->where(function ($filterQuery) use ($entitlementFilters) {
            $filterQuery->applyEntitlementFilters($entitlementFilters);
        });

        // Apply optional filters
        $datasetId = $request->input('dataset_id');
        if ($datasetId) {
            $query->forDataset($datasetId);
        }

        $buildingType = $request->input('building_type');
        if ($buildingType) {
            $query->byType($buildingType);
        }

        // Get statistical data using raw SQL for better performance
        $statsQuery = clone $query;
        $rawStats = $statsQuery->selectRaw('
            COUNT(*) as total_count,
            AVG(average_heatloss) as mean_heat_loss,
            STDDEV(average_heatloss) as std_deviation,
            MIN(average_heatloss) as min_heat_loss,
            MAX(average_heatloss) as max_heat_loss,
            PERCENTILE_CONT(0.25) WITHIN GROUP (ORDER BY average_heatloss) as p25,
            PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY average_heatloss) as median_heat_loss,
            PERCENTILE_CONT(0.75) WITHIN GROUP (ORDER BY average_heatloss) as p75,
            PERCENTILE_CONT(0.9) WITHIN GROUP (ORDER BY average_heatloss) as p90,
            PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY average_heatloss) as p95
        ')->first();

        // Create distribution bins
        $distributionQuery = clone $query;
        $minValue = floor($rawStats->min_heat_loss / 10) * 10;
        $maxValue = ceil($rawStats->max_heat_loss / 10) * 10;
        $binSize = max(10, ($maxValue - $minValue) / 10); // Create ~10 bins

        $distribution = [];
        for ($i = $minValue; $i < $maxValue; $i += $binSize) {
            $rangeEnd = $i + $binSize;
            $count = $distributionQuery->clone()
                ->whereBetween('average_heatloss', [$i, $rangeEnd])
                ->count();
            
            if ($count > 0) {
                $distribution[] = [
                    'range' => round($i, 1) . '-' . round($rangeEnd, 1),
                    'range_start' => round($i, 1),
                    'range_end' => round($rangeEnd, 1),
                    'count' => $count,
                    'percentage' => round(($count / $rawStats->total_count) * 100, 1)
                ];
            }
        }

        $analytics = [
            'heat_loss_statistics' => [
                'total_buildings' => (int) $rawStats->total_count,
                'mean' => round($rawStats->mean_heat_loss, 2),
                'median' => round($rawStats->median_heat_loss, 2),
                'std_deviation' => round($rawStats->std_deviation, 2),
                'min' => round($rawStats->min_heat_loss, 2),
                'max' => round($rawStats->max_heat_loss, 2),
                'percentiles' => [
                    'p25' => round($rawStats->p25, 2),
                    'p75' => round($rawStats->p75, 2),
                    'p90' => round($rawStats->p90, 2),
                    'p95' => round($rawStats->p95, 2)
                ]
            ],
            'distribution' => $distribution
        ];

        // Add specific building comparison if requested
        $buildingGid = $request->input('building_gid');
        if ($buildingGid) {
            $specificBuilding = $query->clone()->where('gid', $buildingGid)->first();
            if ($specificBuilding) {
                // Calculate percentile rank for the specific building
                $lowerCount = $query->clone()
                    ->where('average_heatloss', '<', $specificBuilding->average_heatloss)
                    ->count();
                
                $percentileRank = ($lowerCount / $rawStats->total_count) * 100;

                $analytics['building_comparison'] = [
                    'current_building' => [
                        'gid' => $specificBuilding->gid,
                        'heat_loss' => round($specificBuilding->average_heatloss, 2),
                        'percentile_rank' => round($percentileRank, 1),
                        'is_anomaly' => $specificBuilding->is_anomaly,
                        'confidence' => round($specificBuilding->confidence, 2),
                        'building_type' => $specificBuilding->building_type_classification
                    ],
                    'comparison_stats' => [
                        'better_than_percent' => round($percentileRank, 1),
                        'worse_than_percent' => round(100 - $percentileRank, 1),
                        'deviation_from_mean' => round($specificBuilding->average_heatloss - $rawStats->mean_heat_loss, 2),
                        'z_score' => round(($specificBuilding->average_heatloss - $rawStats->mean_heat_loss) / $rawStats->std_deviation, 2)
                    ]
                ];
            }
        }

        return response()->json($analytics);
    }
}
