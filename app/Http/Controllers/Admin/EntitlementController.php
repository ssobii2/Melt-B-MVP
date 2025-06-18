<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\Dataset;
use App\Models\AuditLog;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\Point;

class EntitlementController extends Controller
{
    protected UserEntitlementService $entitlementService;

    public function __construct(UserEntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    /**
     * Get a paginated list of all entitlements.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $type = $request->input('type');
        $datasetId = $request->input('dataset_id');

        $query = Entitlement::query()
            ->with(['dataset:id,name,data_type', 'users:id,name,email']);

        // Apply type filter
        if ($type) {
            $query->where('type', $type);
        }

        // Apply dataset filter
        if ($datasetId) {
            $query->where('dataset_id', $datasetId);
        }

        $entitlements = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($entitlements);
    }

    /**
     * Get details of a specific entitlement.
     */
    public function show(int $id): JsonResponse
    {
        $entitlement = Entitlement::with(['dataset', 'users'])->find($id);

        if (!$entitlement) {
            return response()->json(['message' => 'Entitlement not found'], 404);
        }

        return response()->json([
            'entitlement' => $entitlement,
            'is_expired' => $entitlement->isExpired(),
            'users_count' => $entitlement->users()->count()
        ]);
    }

    /**
     * Create a new entitlement.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:DS-ALL,DS-AOI,DS-BLD,TILES'],
            'dataset_id' => ['required', 'integer', 'exists:datasets,id'],
            'aoi_coordinates' => ['sometimes', 'array', 'min:3'],
            'aoi_coordinates.*' => ['array', 'size:2'],
            'aoi_coordinates.*.*' => ['numeric'],
            'building_gids' => ['sometimes', 'array'],
            'building_gids.*' => ['string'],
            'download_formats' => ['sometimes', 'array'],
            'download_formats.*' => ['string', 'in:csv,json,geojson,excel,pdf'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate dataset exists
        $dataset = Dataset::find($request->dataset_id);
        if (!$dataset) {
            return response()->json(['message' => 'Dataset not found'], 404);
        }

        $entitlementData = [
            'type' => $request->type,
            'dataset_id' => $request->dataset_id,
            'building_gids' => $request->building_gids,
            'download_formats' => $request->download_formats,
            'expires_at' => $request->expires_at,
        ];

        // Handle AOI geometry for DS-AOI and TILES types
        if (in_array($request->type, ['DS-AOI', 'TILES']) && $request->has('aoi_coordinates')) {
            try {
                $coordinates = $request->aoi_coordinates;

                // Ensure the polygon is closed
                if (end($coordinates) !== $coordinates[0]) {
                    $coordinates[] = $coordinates[0];
                }

                $points = array_map(function ($coord) {
                    return new Point($coord[1], $coord[0]); // Note: Point expects (lat, lng)
                }, $coordinates);

                $polygon = new Polygon([$points]);

                $entitlementData['aoi_geom'] = $polygon;
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Invalid AOI coordinates format',
                    'error' => $e->getMessage()
                ], 422);
            }
        }

        $entitlement = Entitlement::create($entitlementData);

        // Clear all entitlements cache since new entitlement affects access
        $this->entitlementService->clearAllEntitlementsCache();

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_entitlement_created',
            targetType: 'entitlement',
            targetId: $entitlement->id,
            newValues: [
                'type' => $entitlement->type,
                'dataset_id' => $entitlement->dataset_id,
                'expires_at' => $entitlement->expires_at?->toISOString()
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Entitlement created successfully',
            'entitlement' => $entitlement->load('dataset')
        ], 201);
    }

    /**
     * Update an existing entitlement.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $entitlement = Entitlement::find($id);

        if (!$entitlement) {
            return response()->json(['message' => 'Entitlement not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => ['sometimes', 'string', 'in:DS-ALL,DS-AOI,DS-BLD,TILES'],
            'dataset_id' => ['sometimes', 'integer', 'exists:datasets,id'],
            'aoi_coordinates' => ['sometimes', 'array', 'min:3'],
            'aoi_coordinates.*' => ['array', 'size:2'],
            'aoi_coordinates.*.*' => ['numeric'],
            'building_gids' => ['sometimes', 'array'],
            'building_gids.*' => ['string'],
            'download_formats' => ['sometimes', 'array'],
            'download_formats.*' => ['string', 'in:csv,json,geojson,excel,pdf'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $entitlement->only(['type', 'dataset_id', 'building_gids', 'download_formats', 'expires_at']);
        $updateData = $request->only(['type', 'dataset_id', 'building_gids', 'download_formats', 'expires_at']);

        // Handle AOI geometry update
        if ($request->has('aoi_coordinates')) {
            try {
                $coordinates = $request->aoi_coordinates;

                // Ensure the polygon is closed
                if (end($coordinates) !== $coordinates[0]) {
                    $coordinates[] = $coordinates[0];
                }

                $points = array_map(function ($coord) {
                    return new Point($coord[1], $coord[0]); // Note: Point expects (lat, lng)
                }, $coordinates);

                $polygon = new Polygon([$points]);

                $updateData['aoi_geom'] = $polygon;
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Invalid AOI coordinates format',
                    'error' => $e->getMessage()
                ], 422);
            }
        }

        $entitlement->update($updateData);

        // Clear all entitlements cache since entitlement changed
        $this->entitlementService->clearAllEntitlementsCache();

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_entitlement_updated',
            targetType: 'entitlement',
            targetId: $entitlement->id,
            oldValues: $oldValues,
            newValues: $entitlement->only(['type', 'dataset_id', 'building_gids', 'download_formats', 'expires_at']),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Entitlement updated successfully',
            'entitlement' => $entitlement->fresh(['dataset'])
        ]);
    }

    /**
     * Delete an entitlement.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $entitlement = Entitlement::find($id);

        if (!$entitlement) {
            return response()->json(['message' => 'Entitlement not found'], 404);
        }

        $entitlementData = $entitlement->only(['type', 'dataset_id', 'expires_at']);

        // Clear all entitlements cache since entitlement is being deleted
        $this->entitlementService->clearAllEntitlementsCache();

        // Delete the entitlement (this will also delete related user_entitlement pivot records)
        $entitlement->delete();

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_entitlement_deleted',
            targetType: 'entitlement',
            targetId: $id,
            oldValues: $entitlementData,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Entitlement deleted successfully'
        ]);
    }

    /**
     * Get all available datasets for entitlement creation.
     */
    public function datasets(): JsonResponse
    {
        $datasets = Dataset::select('id', 'name', 'data_type', 'description')->get();

        return response()->json([
            'datasets' => $datasets
        ]);
    }

    /**
     * Get entitlement statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_entitlements' => Entitlement::count(),
            'active_entitlements' => Entitlement::where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->count(),
            'expired_entitlements' => Entitlement::where('expires_at', '<=', now())->count(),
            'by_type' => Entitlement::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'by_dataset' => Entitlement::with('dataset:id,name')
                ->selectRaw('dataset_id, COUNT(*) as count')
                ->groupBy('dataset_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'dataset_name' => $item->dataset->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                })
        ];

        return response()->json($stats);
    }
}
