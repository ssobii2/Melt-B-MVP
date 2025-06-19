<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Dataset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
     */
    private function getBuildings(Request $request): JsonResponse
    {
        $query = Building::query()->with('dataset:id,name,data_type');

        // Apply search filter
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        // Apply dataset filter
        if ($datasetId = $request->input('dataset_id')) {
            $query->forDataset($datasetId);
        }

        // Apply TLI range filter
        if ($tliRange = $request->input('tli_range')) {
            [$min, $max] = explode('-', $tliRange);
            $query->withTliRange((int)$min, (int)$max);
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 50);
        $buildings = $query->paginate($perPage);

        return response()->json([
            'data' => $buildings->items(),
            'current_page' => $buildings->currentPage(),
            'last_page' => $buildings->lastPage(),
            'per_page' => $buildings->perPage(),
            'total' => $buildings->total(),
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
