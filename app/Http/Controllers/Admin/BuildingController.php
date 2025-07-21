<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use App\Models\Dataset;
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

#[Tag('Admin - Buildings')]
#[Response(401, 'Unauthorized')]
#[Response(403, 'Forbidden')]
class BuildingController extends Controller
{
    /**
     * Display a listing of buildings for admin viewing (web view).
     */
    #[OperationId('admin.buildings.index')]
    #[Summary('List buildings')]
    #[Description('Get a paginated list of buildings with filtering options. Admin users have unrestricted access to all buildings.')]
    #[Parameters([
        'search' => 'Search term for building properties',
        'dataset_id' => 'Filter by dataset ID',
        'anomaly_filter' => 'Filter by anomaly status (true/false)',
        'type' => 'Filter by building type',
        'sort_by' => 'Sort field (gid, is_anomaly, confidence, average_heatloss, co2_savings_estimate, building_type_classification)',
        'sort_order' => 'Sort order (asc/desc)',
        'per_page' => 'Number of items per page (max: 100)',
        'priority_gids' => 'Array of GIDs to prioritize in sorting (optional)'
    ])]
    #[Response(200, 'Buildings list', [
        'data' => [
            [
                'gid' => 'BLD_001',
                'is_anomaly' => true,
                'confidence' => 0.85,
                'average_heatloss' => 125.5,
                'co2_savings_estimate' => 2.3,
                'building_type_classification' => 'residential',
                'dataset' => [
                    'id' => 1,
                    'name' => 'Thermal Dataset 2024',
                    'data_type' => 'thermal_raster'
                ]
            ]
        ],
        'meta' => [
            'current_page' => 1,
            'last_page' => 10,
            'per_page' => 15,
            'total' => 150
        ]
    ])]
    public function index(Request $request)
    {
        // If this is an API request, return JSON data
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->getBuildings($request);
        }

        // Otherwise return the web view
        return view('admin.buildings');
    }

    /**
     * Get buildings data for API (used by admin dashboard).
     * Note: Admin users have unrestricted access to all buildings.
     */
    private function getBuildings(Request $request): JsonResponse
    {
        $query = Building::query()->with('dataset:id,name,data_type');

        // Admin users see all buildings without entitlement filtering
        // This is intentional for administrative oversight

        // Apply search filter
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        // Apply dataset filter
        if ($datasetId = $request->input('dataset_id')) {
            $query->forDataset($datasetId);
        }

        // Apply anomaly filter
        $anomalyFilter = $request->input('anomaly_filter');
        if ($anomalyFilter !== null && $anomalyFilter !== '') {
            if ($anomalyFilter === 'true') {
                $query->where('is_anomaly', true);
            } elseif ($anomalyFilter === 'false') {
                $query->where('is_anomaly', false);
            }
        }

        // Apply building type filter
        if ($type = $request->input('type')) {
            $query->byType($type);
        }

        // Check if priority GIDs are provided for custom sorting
        $priorityGids = $request->input('priority_gids');
        
        // Also check for priority_gids in JSON body (for POST requests)
        if (!$priorityGids && $request->isMethod('POST')) {
            $priorityGids = $request->json('priority_gids');
        }
        
        if ($priorityGids && is_array($priorityGids) && !empty($priorityGids)) {
            // Convert GIDs to placeholders for the IN clause
            $placeholders = str_repeat('?,', count($priorityGids) - 1) . '?';
            
            // Order by priority GIDs first, then by the regular sort field
            $query->orderByRaw("CASE WHEN gid IN ($placeholders) THEN 0 ELSE 1 END", $priorityGids);
            
            // Apply the regular sorting as secondary
            $sortBy = $request->input('sort_by', 'gid');
            $sortOrder = $request->input('sort_order', 'asc');
            
            if (in_array($sortBy, ['gid', 'is_anomaly', 'confidence', 'average_heatloss', 'co2_savings_estimate', 'building_type_classification'])) {
                $query->orderBy($sortBy, $sortOrder);
                if ($sortBy !== 'gid') {
                    $query->orderBy('gid', 'asc'); // Add secondary sort for stability
                }
            }
        } else {
            // Apply same sorting as findPage method (default behavior)
            $sortBy = $request->input('sort_by', 'gid');
            $sortOrder = $request->input('sort_order', 'asc');

            if (in_array($sortBy, ['gid', 'is_anomaly', 'confidence', 'average_heatloss', 'co2_savings_estimate', 'building_type_classification'])) {
                $query->orderBy($sortBy, $sortOrder);
                if ($sortBy !== 'gid') {
                    $query->orderBy('gid', 'asc'); // Add secondary sort for stability
                }
            }
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $buildings = $query->paginate($perPage);

        return response()->json([
            'data' => BuildingResource::collection($buildings->items()),
            'meta' => [
                'current_page' => $buildings->currentPage(),
                'last_page' => $buildings->lastPage(),
                'per_page' => $buildings->perPage(),
                'total' => $buildings->total(),
            ]
        ]);
    }

    /**
     * Get buildings with priority sorting (POST endpoint for large priority lists).
     */
    #[OperationId('admin.buildings.with-priority')]
    #[Summary('Get buildings with priority sorting')]
    #[Description('Get a paginated list of buildings with priority GIDs sorted first. Use this endpoint when you have many priority GIDs to avoid URL length limits.')]
    #[Response(200, 'Buildings list with priority sorting', [
        'data' => [
            [
                'gid' => 'BLD_001',
                'is_anomaly' => true,
                'confidence' => 0.85,
                'average_heatloss' => 125.5,
                'co2_savings_estimate' => 2.3,
                'building_type_classification' => 'residential',
                'dataset' => [
                    'id' => 1,
                    'name' => 'Thermal Dataset 2024',
                    'data_type' => 'thermal_raster'
                ]
            ]
        ],
        'meta' => [
            'current_page' => 1,
            'last_page' => 10,
            'per_page' => 15,
            'total' => 150
        ]
    ])]
    public function withPriority(Request $request): JsonResponse
    {
        return $this->getBuildings($request);
    }

    /**
     * Show a specific building (API endpoint).
     */
    #[OperationId('admin.buildings.show')]
    #[Summary('Get building details')]
    #[Description('Get detailed information about a specific building by its GID.')]
    #[Response(200, 'Building details', [
        'gid' => 'BLD_001',
        'is_anomaly' => true,
        'confidence' => 0.85,
        'average_heatloss' => 125.5,
        'co2_savings_estimate' => 2.3,
        'building_type_classification' => 'residential',
        'geometry' => 'POINT(-73.935242 40.730610)',
        'dataset' => [
            'id' => 1,
            'name' => 'Thermal Dataset 2024',
            'data_type' => 'thermal_raster'
        ],
        'created_at' => '2024-01-15T10:00:00Z',
        'updated_at' => '2024-01-15T10:00:00Z'
    ])]
    #[Response(404, 'Building not found')]
    public function show(Request $request, string $gid): JsonResponse
    {
        $building = Building::with('dataset:id,name,data_type')
            ->where('gid', $gid)
            ->first();

        if (!$building) {
            return response()->json(['message' => 'Building not found'], 404);
        }

        return response()->json(new BuildingResource($building));
    }

    /**
     * Find the page number where a specific building appears in the filtered results.
     */
    #[OperationId('admin.buildings.find-page')]
    #[Summary('Find building page')]
    #[Description('Find the page number where a specific building appears in the filtered and sorted results.')]
    #[Parameters([
        'search' => 'Search term for building properties',
        'dataset_id' => 'Filter by dataset ID',
        'anomaly_filter' => 'Filter by anomaly status (true/false)',
        'type' => 'Filter by building type',
        'sort_by' => 'Sort field (gid, is_anomaly, confidence, average_heatloss, co2_savings_estimate, building_type_classification)',
        'sort_order' => 'Sort order (asc/desc)',
        'per_page' => 'Number of items per page (max: 100)',
        'priority_gids' => 'Array of GIDs to prioritize in sorting (optional)'
    ])]
    #[Response(200, 'Building page information', [
        'page' => 3,
        'position' => 42,
        'per_page' => 15
    ])]
    #[Response(404, 'Building not found in current filter set')]
    public function findPage(Request $request, string $gid): JsonResponse
    {
        $query = Building::query();

        // Apply search filter
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        // Apply dataset filter
        if ($datasetId = $request->input('dataset_id')) {
            $query->forDataset($datasetId);
        }

        // Apply anomaly filter
        $anomalyFilter = $request->input('anomaly_filter');
        if ($anomalyFilter !== null && $anomalyFilter !== '') {
            if ($anomalyFilter === 'true') {
                $query->where('is_anomaly', true);
            } elseif ($anomalyFilter === 'false') {
                $query->where('is_anomaly', false);
            }
        }

        // Apply building type filter (to match frontend filtering)
        if ($type = $request->input('type')) {
            $query->byType($type);
        }

        // Check if priority GIDs are provided for custom sorting (same as main listing)
        $priorityGids = $request->input('priority_gids');
        if ($priorityGids && is_array($priorityGids) && !empty($priorityGids)) {
            // Convert GIDs to placeholders for the IN clause
            $placeholders = str_repeat('?,', count($priorityGids) - 1) . '?';
            
            // Order by priority GIDs first, then by the regular sort field
            $query->orderByRaw("CASE WHEN gid IN ($placeholders) THEN 0 ELSE 1 END", $priorityGids);
            
            // Apply the regular sorting as secondary
            $sortBy = $request->input('sort_by', 'gid');
            $sortOrder = $request->input('sort_order', 'asc');
            
            if (in_array($sortBy, ['gid', 'is_anomaly', 'confidence', 'average_heatloss', 'co2_savings_estimate', 'building_type_classification'])) {
                $query->orderBy($sortBy, $sortOrder);
                if ($sortBy !== 'gid') {
                    $query->orderBy('gid', 'asc'); // Add secondary sort for stability
                }
            }
        } else {
            // Apply same sorting as main listing (default behavior)
            $sortBy = $request->input('sort_by', 'gid');
            $sortOrder = $request->input('sort_order', 'asc');

            if (in_array($sortBy, ['gid', 'is_anomaly', 'confidence', 'average_heatloss', 'co2_savings_estimate', 'building_type_classification'])) {
                $query->orderBy($sortBy, $sortOrder);
                if ($sortBy !== 'gid') {
                    $query->orderBy('gid', 'asc'); // Add secondary sort for stability
                }
            }
        }

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
     * Get buildings within a specific geographic area for admin users.
     * Admin users see all buildings without entitlement filtering.
     */
    #[OperationId('admin.buildings.within-bounds')]
    #[Summary('Get buildings within bounds')]
    #[Description('Get buildings within a specific geographic bounding box. Admin users see all buildings without entitlement filtering.')]
    #[Parameters([
        'north' => 'Northern latitude boundary (-90 to 90)',
        'south' => 'Southern latitude boundary (-90 to 90)',
        'east' => 'Eastern longitude boundary (-180 to 180)',
        'west' => 'Western longitude boundary (-180 to 180)',
        'limit' => 'Maximum number of buildings to return (max: 5000, default: 1000)'
    ])]
    #[Response(200, 'Buildings within bounds', [
        'data' => [
            [
                'gid' => 'BLD_001',
                'is_anomaly' => true,
                'confidence' => 0.85,
                'average_heatloss' => 125.5,
                'co2_savings_estimate' => 2.3,
                'building_type_classification' => 'residential',
                'geometry' => 'POINT(-73.935242 40.730610)',
                'dataset' => [
                    'id' => 1,
                    'name' => 'Thermal Dataset 2024',
                    'data_type' => 'thermal_raster'
                ]
            ]
        ],
        'count' => 150,
        'bbox' => [
            'north' => 40.8,
            'south' => 40.7,
            'east' => -73.9,
            'west' => -74.0
        ]
    ])]
    #[Response(422, 'Validation failed')]
    public function withinBounds(Request $request): JsonResponse
    {
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

        // Start building query - admin users see all buildings
        $query = Building::query()->with('dataset:id,name,data_type');

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
     * Export buildings data (placeholder for future implementation).
     */
    #[OperationId('admin.buildings.export')]
    #[Summary('Export buildings data')]
    #[Description('Export buildings data to CSV/Excel format. This is a placeholder for future implementation in Phase 2.')]
    #[Response(200, 'Export information', [
        'message' => 'Export functionality will be implemented in Phase 2',
        'note' => 'This endpoint is ready for CSV/Excel export implementation'
    ])]
    public function export(Request $request): JsonResponse
    {
        // This is a placeholder - actual export functionality would be implemented in Phase 2
        return response()->json([
            'message' => 'Export functionality will be implemented in Phase 2',
            'note' => 'This endpoint is ready for CSV/Excel export implementation'
        ]);
    }
}
