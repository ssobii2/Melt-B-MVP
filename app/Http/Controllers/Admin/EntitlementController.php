<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntitlementResource;
use App\Models\Entitlement;
use App\Models\Dataset;
use App\Models\AuditLog;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\RequestBody;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;
use Dedoc\Scramble\Attributes\Parameters;

#[Tag('Admin - Entitlements')]
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
    #[OperationId('admin.entitlements.index')]
    #[Summary('List entitlements')]
    #[Description('Get a paginated list of entitlements with optional filtering by type and dataset.')]
    #[Parameters([
        'per_page' => 'integer|optional|Number of items per page (default: 15)',
        'type' => 'string|optional|Filter by entitlement type (DS-ALL, DS-AOI, DS-BLD, TILES)',
        'dataset_id' => 'integer|optional|Filter by dataset ID'
    ])]
    #[Response(200, 'Paginated entitlements', [
        'data' => [
            [
                'id' => 1,
                'type' => 'DS-AOI',
                'dataset_id' => 5,
                'building_gids' => null,
                'download_formats' => ['csv', 'geojson'],
                'expires_at' => '2024-12-31T23:59:59Z',
                'created_at' => '2024-01-01T00:00:00Z',
                'dataset' => [
                    'id' => 5,
                    'name' => 'Thermal Dataset',
                    'data_type' => 'thermal_raster'
                ],
                'users' => [
                    [
                        'id' => 1,
                        'name' => 'John Doe',
                        'email' => 'john@example.com'
                    ]
                ]
            ]
        ],
        'current_page' => 1,
        'per_page' => 15,
        'total' => 50
    ])]
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

        return response()->json([
            'data' => EntitlementResource::collection($entitlements->items()),
            'current_page' => $entitlements->currentPage(),
            'per_page' => $entitlements->perPage(),
            'total' => $entitlements->total()
        ]);
    }

    /**
     * Get details of a specific entitlement.
     */
    #[OperationId('admin.entitlements.show')]
    #[Summary('Get entitlement details')]
    #[Description('Get detailed information about a specific entitlement including AOI coordinates if applicable.')]
    #[Response(200, 'Entitlement details', [
        'entitlement' => [
            'id' => 1,
            'type' => 'DS-AOI',
            'dataset_id' => 5,
            'aoi_geom' => [
                'type' => 'Polygon',
                'coordinates' => [[[-1.0, 51.0], [-1.0, 52.0], [0.0, 52.0], [0.0, 51.0], [-1.0, 51.0]]]
            ],
            'aoi_coordinates' => [[-1.0, 51.0], [-1.0, 52.0], [0.0, 52.0], [0.0, 51.0], [-1.0, 51.0]],
            'building_gids' => null,
            'download_formats' => ['csv', 'geojson'],
            'expires_at' => '2024-12-31T23:59:59Z',
            'dataset' => [
                'id' => 5,
                'name' => 'Thermal Dataset',
                'data_type' => 'thermal_raster'
            ],
            'users' => []
        ],
        'is_expired' => false,
        'users_count' => 0
    ])]
    #[Response(404, 'Entitlement not found', ['message' => 'Entitlement not found'])]
    public function show(string $id): JsonResponse
    {
        $entitlement = Entitlement::with(['dataset', 'users'])->find($id);

        if (!$entitlement) {
            return response()->json(['message' => 'Entitlement not found'], 404);
        }

        return response()->json([
            'entitlement' => new EntitlementResource($entitlement)
        ]);
    }

    /**
     * Create a new entitlement.
     */
    #[OperationId('admin.entitlements.store')]
    #[Summary('Create a new entitlement')]
    #[Description('Create a new entitlement with specified type, dataset, and optional AOI or building restrictions.')]
    #[RequestBody([
        'type' => 'string|required|Entitlement type (DS-ALL, DS-AOI, DS-BLD, TILES)',
        'dataset_id' => 'integer|required|Dataset ID',
        'aoi_coordinates' => 'array|optional|AOI coordinates for DS-AOI and TILES types (array of [lng, lat] pairs)',
        'building_gids' => 'array|optional|Building GIDs for DS-BLD type',
        'download_formats' => 'array|optional|Allowed download formats (csv, geojson)',
        'expires_at' => 'string|optional|Expiration date (ISO 8601 format)'
    ])]
    #[Response(201, 'Entitlement created successfully', [
        'message' => 'Entitlement created successfully',
        'entitlement' => [
            'id' => 1,
            'type' => 'DS-AOI',
            'dataset_id' => 5,
            'download_formats' => ['csv', 'geojson'],
            'expires_at' => '2024-12-31T23:59:59Z',
            'dataset' => [
                'id' => 5,
                'name' => 'Thermal Dataset',
                'data_type' => 'thermal_raster'
            ],
            'users' => []
        ]
    ])]
    #[Response(404, 'Dataset not found', ['message' => 'Dataset not found'])]
    #[Response(422, 'Validation failed', [
        'message' => 'Validation failed',
        'errors' => [
            'type' => ['The selected type is invalid.'],
            'aoi_coordinates' => ['Invalid AOI coordinates format.']
        ]
    ])]
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
            'download_formats.*' => ['string', 'in:csv,geojson'],
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
        
        // Validate type-dependent fields for consistency
        if ($request->type === 'DS-ALL') {
            // DS-ALL should not have AOI coordinates or building GIDs
            if ($request->has('aoi_coordinates') || $request->has('building_gids')) {
                return response()->json([
                    'message' => 'DS-ALL entitlements cannot have AOI coordinates or building restrictions.',
                    'errors' => [
                        'aoi_coordinates' => ['Not allowed for DS-ALL type'],
                        'building_gids' => ['Not allowed for DS-ALL type']
                    ]
                ], 422);
            }
        } elseif ($request->type === 'DS-AOI') {
            // DS-AOI should not have building GIDs
            if ($request->has('building_gids')) {
                return response()->json([
                    'message' => 'DS-AOI entitlements cannot have building restrictions.',
                    'errors' => ['building_gids' => ['Not allowed for DS-AOI type']]
                ], 422);
            }
            // DS-AOI should have AOI coordinates
            if (!$request->has('aoi_coordinates')) {
                return response()->json([
                    'message' => 'DS-AOI entitlements require AOI coordinates.',
                    'errors' => ['aoi_coordinates' => ['Required for DS-AOI type']]
                ], 422);
            }
        } elseif ($request->type === 'DS-BLD') {
            // DS-BLD should not have AOI coordinates
            if ($request->has('aoi_coordinates')) {
                return response()->json([
                    'message' => 'DS-BLD entitlements cannot have AOI coordinates.',
                    'errors' => ['aoi_coordinates' => ['Not allowed for DS-BLD type']]
                ], 422);
            }
            // DS-BLD should have building GIDs
            if (!$request->has('building_gids') || empty($request->building_gids)) {
                return response()->json([
                    'message' => 'DS-BLD entitlements require building GIDs.',
                    'errors' => ['building_gids' => ['Required for DS-BLD type']]
                ], 422);
            }
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

                // Create a LineString from the points, then a Polygon from the LineString
                $lineString = new LineString($points);
                $polygon = new Polygon([$lineString]);

                $entitlementData['aoi_geom'] = $polygon;
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'aoi_coordinates' => ['Invalid AOI coordinates format. Please check your coordinate values.']
                    ]
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
            'entitlement' => $entitlement->load(['dataset', 'users'])
        ], 201);
    }

    /**
     * Update an existing entitlement.
     */
    #[OperationId('admin.entitlements.update')]
    #[Summary('Update entitlement')]
    #[Description('Update an existing entitlement. All fields are optional.')]
    #[RequestBody([
        'type' => 'string|optional|Entitlement type (DS-ALL, DS-AOI, DS-BLD, TILES)',
        'dataset_id' => 'integer|optional|Dataset ID',
        'aoi_coordinates' => 'array|optional|AOI coordinates for DS-AOI and TILES types',
        'building_gids' => 'array|optional|Building GIDs for DS-BLD type',
        'download_formats' => 'array|optional|Allowed download formats (csv, geojson)',
        'expires_at' => 'string|optional|Expiration date (ISO 8601 format)'
    ])]
    #[Response(200, 'Entitlement updated successfully', [
        'message' => 'Entitlement updated successfully',
        'entitlement' => [
            'id' => 1,
            'type' => 'DS-ALL',
            'dataset_id' => 5,
            'download_formats' => ['csv'],
            'expires_at' => '2025-01-31T23:59:59Z'
        ]
    ])]
    #[Response(404, 'Entitlement not found', ['message' => 'Entitlement not found'])]
    #[Response(422, 'Validation failed', [
        'message' => 'Validation failed',
        'errors' => ['aoi_coordinates' => ['Invalid AOI coordinates format.']]
    ])]
    public function update(Request $request, string $id): JsonResponse
    {
        $entitlement = Entitlement::find($id);

        if (!$entitlement) {
            return response()->json(['message' => 'Entitlement not found'], 404);
        }

        // Comprehensive type change prevention
        // Check if type is being modified through any means
        $requestType = $request->input('type', $entitlement->type);
        if ($requestType !== $entitlement->type) {
            return response()->json([
                'message' => 'Entitlement type cannot be changed. Please delete and create a new entitlement to change the type.',
                'errors' => ['type' => ['Entitlement type cannot be modified']]
            ], 422);
        }
        
        // Validate type-dependent fields to prevent inconsistent state
        if ($entitlement->type === 'DS-ALL') {
            // DS-ALL should not have AOI coordinates or building GIDs
            if ($request->has('aoi_coordinates') || $request->has('building_gids')) {
                return response()->json([
                    'message' => 'DS-ALL entitlements cannot have AOI coordinates or building restrictions.',
                    'errors' => [
                        'aoi_coordinates' => ['Not allowed for DS-ALL type'],
                        'building_gids' => ['Not allowed for DS-ALL type']
                    ]
                ], 422);
            }
        } elseif ($entitlement->type === 'DS-AOI') {
            // DS-AOI should not have building GIDs
            if ($request->has('building_gids')) {
                return response()->json([
                    'message' => 'DS-AOI entitlements cannot have building restrictions.',
                    'errors' => ['building_gids' => ['Not allowed for DS-AOI type']]
                ], 422);
            }
        } elseif ($entitlement->type === 'DS-BLD') {
            // DS-BLD should not have AOI coordinates
            if ($request->has('aoi_coordinates')) {
                return response()->json([
                    'message' => 'DS-BLD entitlements cannot have AOI coordinates.',
                    'errors' => ['aoi_coordinates' => ['Not allowed for DS-BLD type']]
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'dataset_id' => ['sometimes', 'integer', 'exists:datasets,id'],
            'aoi_coordinates' => ['sometimes', 'array', 'min:3'],
            'aoi_coordinates.*' => ['array', 'size:2'],
            'aoi_coordinates.*.*' => ['numeric'],
            'building_gids' => ['sometimes', 'array'],
            'building_gids.*' => ['string'],
            'download_formats' => ['sometimes', 'array'],
            'download_formats.*' => ['string', 'in:csv,geojson'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $entitlement->only(['type', 'dataset_id', 'building_gids', 'download_formats', 'expires_at']);
        $updateData = $request->only(['dataset_id', 'building_gids', 'download_formats', 'expires_at']);

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

                // Create a LineString from the points, then a Polygon from the LineString
                $lineString = new LineString($points);
                $polygon = new Polygon([$lineString]);

                $updateData['aoi_geom'] = $polygon;
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => [
                        'aoi_coordinates' => ['Invalid AOI coordinates format. Please check your coordinate values.']
                    ]
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
            'entitlement' => $entitlement->fresh(['dataset', 'users'])
        ]);
    }

    /**
     * Delete an entitlement.
     */
    #[OperationId('admin.entitlements.destroy')]
    #[Summary('Delete entitlement')]
    #[Description('Delete an entitlement. Cannot delete entitlements with assigned users.')]
    #[Response(200, 'Entitlement deleted successfully', ['message' => 'Entitlement deleted successfully'])]
    #[Response(404, 'Entitlement not found', ['message' => 'Entitlement not found'])]
    #[Response(422, 'Entitlement has users', [
        'message' => 'Cannot delete entitlement. It has 3 user(s) assigned. Please remove all users first.'
    ])]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $entitlement = Entitlement::find($id);

        if (!$entitlement) {
            return response()->json(['message' => 'Entitlement not found'], 404);
        }

        // Check if entitlement has users assigned
        $userCount = $entitlement->users()->count();
        if ($userCount > 0) {
            return response()->json([
                'message' => "Cannot delete entitlement. It has {$userCount} user(s) assigned. Please remove all users first."
            ], 422);
        }

        $entitlementData = $entitlement->only(['type', 'dataset_id', 'expires_at']);

        // Clear all entitlements cache since entitlement is being deleted
        $this->entitlementService->clearAllEntitlementsCache();

        // Delete the entitlement (no user assignments to worry about now)
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
    #[OperationId('admin.entitlements.datasets')]
    #[Summary('Get available datasets')]
    #[Description('Get a list of all available datasets for creating entitlements.')]
    #[Response(200, 'Available datasets', [
        'datasets' => [
            [
                'id' => 1,
                'name' => 'Thermal Dataset 2024',
                'data_type' => 'building_anomalies',
                'description' => 'Thermal imaging data for buildings'
            ],
            [
                'id' => 2,
                'name' => 'Building Data',
                'data_type' => 'building_anomalies',
                'description' => 'Comprehensive building information'
            ]
        ]
    ])]
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
    #[OperationId('admin.entitlements.stats')]
    #[Summary('Get entitlement statistics')]
    #[Description('Get comprehensive statistics about entitlements including counts by type, dataset, and expiration status.')]
    #[Response(200, 'Entitlement statistics', [
        'total_entitlements' => 45,
        'active_entitlements' => 38,
        'expired_entitlements' => 7,
        'by_type' => [
            'DS-ALL' => 15,
            'DS-AOI' => 12,
            'DS-BLD' => 10,
            'TILES' => 8
        ],
        'by_dataset' => [
            [
                'dataset_name' => 'Thermal Dataset 2024',
                'count' => 20
            ],
            [
                'dataset_name' => 'Building Data',
                'count' => 15
            ],
            [
                'dataset_name' => 'Heat Map Data',
                'count' => 10
            ]
        ]
    ])]
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

    /**
     * Get all AOI geometries for map display.
     */
    #[OperationId('admin.entitlements.allAois')]
    #[Summary('Get all AOI geometries')]
    #[Description('Get all existing AOI geometries as GeoJSON FeatureCollection for displaying on the map.')]
    #[Response(200, 'AOI geometries as GeoJSON', [
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
    public function allAois(): JsonResponse
    {
        $entitlements = Entitlement::with('dataset:id,name')
            ->whereIn('type', ['DS-AOI', 'TILES'])
            ->whereNotNull('aoi_geom')
            ->get();

        $features = $entitlements->map(function ($entitlement) {
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
