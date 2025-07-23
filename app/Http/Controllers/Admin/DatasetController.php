<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetResource;
use App\Models\Dataset;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\RequestBody;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;
use Dedoc\Scramble\Attributes\Parameters;

#[Tag('Admin - Dataset Management')]
class DatasetController extends Controller
{
    /**
     * Get a paginated list of all datasets.
     */
    #[OperationId('admin.datasets.index')]
    #[Summary('List all datasets')]
    #[Description('Get a paginated list of all datasets with optional search and data type filtering.')]
    #[Parameters([
        'per_page' => 'Number of datasets per page (default: 15)',
        'search' => 'Search term to filter by name or description',
        'data_type' => 'Filter by data type'
    ])]
    #[Response(200, 'Paginated list of datasets', [
        'data' => [
            [
                'id' => 1,
                'name' => 'Thermal Dataset 2024',
                'data_type' => 'building_anomalies',
                'description' => 'Thermal imaging data for buildings',
                'storage_location' => '/data/thermal/2024',
                'version' => '1.0',
                'metadata' => ['source' => 'satellite'],
                'entitlements_count' => 5,
                'created_at' => '2024-01-01T00:00:00Z'
            ]
        ],
        'current_page' => 1,
        'total' => 25
    ])]
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

        return response()->json([
            'data' => DatasetResource::collection($datasets->items()),
            'current_page' => $datasets->currentPage(),
            'per_page' => $datasets->perPage(),
            'total' => $datasets->total()
        ]);
    }

    /**
     * Get details of a specific dataset.
     */
    #[OperationId('admin.datasets.show')]
    #[Summary('Get dataset details')]
    #[Description('Get detailed information about a specific dataset including entitlements and user access.')]
    #[Response(200, 'Dataset details', [
        'dataset' => [
            'id' => 1,
            'name' => 'Thermal Dataset 2024',
            'data_type' => 'thermal_raster',
            'description' => 'Thermal imaging data for buildings',
            'storage_location' => '/data/thermal/2024',
            'version' => '1.0',
            'metadata' => ['source' => 'satellite'],
            'entitlements' => []
        ],
        'entitlements_count' => 5,
        'users_with_access' => 12
    ])]
    #[Response(404, 'Dataset not found', ['message' => 'Dataset not found'])]
    public function show(string $id): JsonResponse
    {
        // Optimize query to prevent N+1 issues and limit data loading
        $dataset = Dataset::with([
                'entitlements' => function ($query) {
                    // Only load essential entitlement fields
                    $query->select('id', 'type', 'dataset_id', 'expires_at')
                          ->with(['users:id,name,email']) // Only load essential user fields
                          ->limit(100); // Prevent loading too many entitlements at once
                }
            ])
            ->withCount('entitlements')
            ->find($id);

        if (!$dataset) {
            return response()->json(['message' => 'Dataset not found'], 404);
        }

        return response()->json([
            'dataset' => new DatasetResource($dataset)
        ]);
    }

    /**
     * Create a new dataset.
     */
    #[OperationId('admin.datasets.store')]
    #[Summary('Create a new dataset')]
    #[Description('Create a new dataset with metadata information.')]
    #[RequestBody([
        'name' => 'string|required|Dataset name (must be unique)',
        'data_type' => 'string|required|Type of data',
        'description' => 'string|optional|Dataset description',
        'storage_location' => 'string|required|Storage path or location',
        'version' => 'string|optional|Dataset version',
        'source' => 'string|optional|Data source',
        'format' => 'string|optional|Data format',
        'size_mb' => 'number|optional|Size in megabytes',
        'spatial_resolution' => 'string|optional|Spatial resolution',
        'temporal_coverage' => 'string|optional|Temporal coverage'
    ])]
    #[Response(201, 'Dataset created successfully', [
        'message' => 'Dataset created successfully',
        'dataset' => [
            'id' => 1,
            'name' => 'Thermal Dataset 2024',
            'data_type' => 'thermal_raster',
            'description' => 'Thermal imaging data for buildings',
            'storage_location' => '/data/thermal/2024',
            'version' => '1.0',
            'metadata' => ['source' => 'satellite']
        ]
    ])]
    #[Response(422, 'Validation failed', [
        'message' => 'Validation failed',
        'errors' => ['name' => ['The name has already been taken.']]
    ])]
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:datasets'],
            'data_type' => ['required', 'string', 'max:100'],
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
    #[OperationId('admin.datasets.update')]
    #[Summary('Update dataset')]
    #[Description('Update an existing dataset. All fields are optional.')]
    #[RequestBody([
        'name' => 'string|optional|Dataset name',
        'data_type' => 'string|optional|Type of data',
        'description' => 'string|optional|Dataset description',
        'storage_location' => 'string|optional|Storage path or location',
        'version' => 'string|optional|Dataset version',
        'source' => 'string|optional|Data source',
        'format' => 'string|optional|Data format',
        'size_mb' => 'number|optional|Size in megabytes',
        'spatial_resolution' => 'string|optional|Spatial resolution',
        'temporal_coverage' => 'string|optional|Temporal coverage'
    ])]
    #[Response(200, 'Dataset updated successfully', [
        'message' => 'Dataset updated successfully',
        'dataset' => [
            'id' => 1,
            'name' => 'Updated Thermal Dataset',
            'data_type' => 'thermal_raster',
            'description' => 'Updated description'
        ]
    ])]
    #[Response(404, 'Dataset not found', ['message' => 'Dataset not found'])]
    #[Response(422, 'Validation failed', [
        'message' => 'Validation failed',
        'errors' => ['name' => ['The name has already been taken.']]
    ])]
    public function update(Request $request, string $id): JsonResponse
    {
        $dataset = Dataset::find($id);

        if (!$dataset) {
            return response()->json(['message' => 'Dataset not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255', 'unique:datasets,name,' . $id],
            'data_type' => ['sometimes', 'string', 'max:100'],
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
    #[OperationId('admin.datasets.destroy')]
    #[Summary('Delete dataset')]
    #[Description('Delete a dataset. Cannot delete datasets with associated entitlements.')]
    #[Response(200, 'Dataset deleted successfully', ['message' => 'Dataset deleted successfully'])]
    #[Response(404, 'Dataset not found', ['message' => 'Dataset not found'])]
    #[Response(422, 'Dataset has entitlements', [
        'message' => 'Cannot delete dataset. It has 5 associated entitlements. Please remove all entitlements first.'
    ])]
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
    #[OperationId('admin.datasets.stats')]
    #[Summary('Get dataset statistics')]
    #[Description('Get comprehensive statistics about datasets including counts, types, and usage.')]
    #[Response(200, 'Dataset statistics', [
        'total_datasets' => 25,
        'by_data_type' => [
            'building_anomalies' => 15
        ],
        'datasets_with_entitlements' => 18,
        'datasets_without_entitlements' => 7,
        'recent_datasets' => [
            [
                'id' => 1,
                'name' => 'Latest Dataset',
                'data_type' => 'thermal_raster',
                'created_at' => '2024-01-01T00:00:00Z'
            ]
        ],
        'most_used_datasets' => [
            [
                'id' => 2,
                'name' => 'Popular Dataset',
                'data_type' => 'building_anomalies',
                'entitlements_count' => 15
            ]
        ]
    ])]
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
    #[OperationId('admin.datasets.dataTypes')]
    #[Summary('Get available data types')]
    #[Description('Get a list of all available data types for filtering datasets.')]
    #[Response(200, 'Available data types', [
        'data_types' => [
            'building_anomalies' => 'Building Anomalies'
        ]
    ])]
    public function dataTypes(): JsonResponse
    {
        // Get distinct data types from database
        $distinctDataTypes = Dataset::distinct()->pluck('data_type')->filter()->sort()->values();
        
        // Create formatted array with display names
        $dataTypes = [];
        foreach ($distinctDataTypes as $dataType) {
            $dataTypes[$dataType] = $this->formatDataTypeLabel($dataType);
        }
        
        return response()->json([
            'data_types' => $dataTypes
        ]);
    }
    
    /**
     * Format data type for display.
     */
    private function formatDataTypeLabel(string $dataType): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $dataType));
    }
}
