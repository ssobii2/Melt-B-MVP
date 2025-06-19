<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DatasetController extends Controller
{
    /**
     * Get a paginated list of all datasets.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $dataType = $request->input('data_type');

        $query = Dataset::query()->withCount('entitlements');

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Apply data type filter
        if ($dataType) {
            $query->where('data_type', $dataType);
        }

        $datasets = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($datasets);
    }

    /**
     * Get details of a specific dataset.
     */
    public function show(string $id): JsonResponse
    {
        $dataset = Dataset::with(['entitlements.users'])->find($id);

        if (!$dataset) {
            return response()->json(['message' => 'Dataset not found'], 404);
        }

        return response()->json([
            'dataset' => $dataset,
            'entitlements_count' => $dataset->entitlements()->count(),
            'users_with_access' => $dataset->entitlements()
                ->with('users:id,name,email')
                ->get()
                ->pluck('users')
                ->flatten()
                ->unique('id')
                ->count()
        ]);
    }

    /**
     * Create a new dataset.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:datasets'],
            'data_type' => ['required', 'string', 'in:thermal_raster,building_data,thermal_analysis,heat_map'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'storage_location' => ['required', 'string', 'max:500'],
            'version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'format' => ['sometimes', 'nullable', 'string', 'max:100'],
            'size_mb' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'spatial_resolution' => ['sometimes', 'nullable', 'string', 'max:100'],
            'temporal_coverage' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Build metadata array from individual fields
        $metadata = [];
        if ($request->source) $metadata['source'] = $request->source;
        if ($request->format) $metadata['format'] = $request->format;
        if ($request->size_mb) $metadata['size_mb'] = $request->size_mb;
        if ($request->spatial_resolution) $metadata['spatial_resolution'] = $request->spatial_resolution;
        if ($request->temporal_coverage) $metadata['temporal_coverage'] = $request->temporal_coverage;

        $dataset = Dataset::create([
            'name' => $request->name,
            'data_type' => $request->data_type,
            'description' => $request->description,
            'storage_location' => $request->storage_location,
            'version' => $request->version,
            'metadata' => !empty($metadata) ? $metadata : null,
        ]);

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_dataset_created',
            targetType: 'dataset',
            targetId: $dataset->id,
            newValues: [
                'name' => $dataset->name,
                'data_type' => $dataset->data_type,
                'description' => $dataset->description
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Dataset created successfully',
            'dataset' => $dataset
        ], 201);
    }

    /**
     * Update an existing dataset.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $dataset = Dataset::find($id);

        if (!$dataset) {
            return response()->json(['message' => 'Dataset not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255', 'unique:datasets,name,' . $id],
            'data_type' => ['sometimes', 'string', 'in:thermal_raster,building_data,thermal_analysis,heat_map'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'storage_location' => ['sometimes', 'string', 'max:500'],
            'version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'format' => ['sometimes', 'nullable', 'string', 'max:100'],
            'size_mb' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'spatial_resolution' => ['sometimes', 'nullable', 'string', 'max:100'],
            'temporal_coverage' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $dataset->only(['name', 'data_type', 'description', 'storage_location', 'version', 'metadata']);
        $updateData = $request->only(['name', 'data_type', 'description', 'storage_location', 'version']);

        // Build metadata array from individual fields if any metadata fields are provided
        if ($request->hasAny(['source', 'format', 'size_mb', 'spatial_resolution', 'temporal_coverage'])) {
            $metadata = [];
            if ($request->has('source')) $metadata['source'] = $request->source;
            if ($request->has('format')) $metadata['format'] = $request->format;
            if ($request->has('size_mb')) $metadata['size_mb'] = $request->size_mb;
            if ($request->has('spatial_resolution')) $metadata['spatial_resolution'] = $request->spatial_resolution;
            if ($request->has('temporal_coverage')) $metadata['temporal_coverage'] = $request->temporal_coverage;

            $updateData['metadata'] = !empty($metadata) ? $metadata : null;
        }

        $dataset->update($updateData);

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_dataset_updated',
            targetType: 'dataset',
            targetId: $dataset->id,
            oldValues: $oldValues,
            newValues: $dataset->only(['name', 'data_type', 'description', 'storage_location', 'version', 'metadata']),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Dataset updated successfully',
            'dataset' => $dataset->fresh()
        ]);
    }

    /**
     * Delete a dataset.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $dataset = Dataset::find($id);

        if (!$dataset) {
            return response()->json(['message' => 'Dataset not found'], 404);
        }

        // Check if dataset has entitlements
        $entitlementsCount = $dataset->entitlements()->count();
        if ($entitlementsCount > 0) {
            return response()->json([
                'message' => "Cannot delete dataset. It has {$entitlementsCount} associated entitlements. Please remove all entitlements first."
            ], 422);
        }

        $datasetData = $dataset->only(['name', 'data_type', 'description']);

        // Delete the dataset
        $dataset->delete();

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_dataset_deleted',
            targetType: 'dataset',
            targetId: $id,
            oldValues: $datasetData,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Dataset deleted successfully'
        ]);
    }

    /**
     * Get dataset statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_datasets' => Dataset::count(),
            'by_data_type' => Dataset::selectRaw('data_type, COUNT(*) as count')
                ->groupBy('data_type')
                ->pluck('count', 'data_type'),
            'datasets_with_entitlements' => Dataset::whereHas('entitlements')->count(),
            'datasets_without_entitlements' => Dataset::whereDoesntHave('entitlements')->count(),
            'recent_datasets' => Dataset::orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'data_type', 'created_at']),
            'most_used_datasets' => Dataset::withCount('entitlements')
                ->orderBy('entitlements_count', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'data_type', 'entitlements_count'])
        ];

        return response()->json($stats);
    }

    /**
     * Get available data types for filter dropdown.
     */
    public function dataTypes(): JsonResponse
    {
        $dataTypes = [
            'thermal_raster' => 'Thermal Raster',
            'building_data' => 'Building Data',
            'thermal_analysis' => 'Thermal Analysis',
            'heat_map' => 'Heat Map'
        ];

        return response()->json([
            'data_types' => $dataTypes
        ]);
    }
}
