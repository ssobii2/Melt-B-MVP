<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Dataset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BuildingController extends Controller
{
    /**
     * Display a listing of buildings for admin viewing (web view).
     */
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

        // Apply same sorting as findPage method
        $sortBy = $request->input('sort_by', 'is_anomaly');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['is_anomaly', 'confidence', 'average_heatloss', 'co2_savings_estimate', 'building_type_classification'])) {
            $query->orderBy($sortBy, $sortOrder)->orderBy('gid', 'asc'); // Add secondary sort for stability
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);
        $buildings = $query->paginate($perPage);

        return response()->json([
            'data' => $buildings->items(),
            'meta' => [
                'current_page' => $buildings->currentPage(),
                'last_page' => $buildings->lastPage(),
                'per_page' => $buildings->perPage(),
                'total' => $buildings->total(),
            ]
        ]);
    }

    /**
     * Show a specific building (API endpoint).
     */
    public function show(Request $request, string $gid): JsonResponse
    {
        $building = Building::with('dataset:id,name,data_type')
            ->where('gid', $gid)
            ->first();

        if (!$building) {
            return response()->json(['message' => 'Building not found'], 404);
        }

        return response()->json($building);
    }

    /**
     * Find the page number where a specific building appears in the filtered results.
     */
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

        // Apply same sorting as main listing
        $sortBy = $request->input('sort_by', 'is_anomaly');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['is_anomaly', 'confidence', 'average_heatloss', 'co2_savings_estimate', 'building_type_classification'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min($request->input('per_page', 10), 100);
        
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
            'data' => $buildings,
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
    public function export(Request $request): JsonResponse
    {
        // This is a placeholder - actual export functionality would be implemented in Phase 2
        return response()->json([
            'message' => 'Export functionality will be implemented in Phase 2',
            'note' => 'This endpoint is ready for CSV/Excel export implementation'
        ]);
    }
}
