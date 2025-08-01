<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Dataset;
use App\Models\AuditLog;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;
use Dedoc\Scramble\Attributes\Parameters;

#[Tag('Data Downloads')]
class DownloadController extends Controller
{
    private UserEntitlementService $entitlementService;

    public function __construct(UserEntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    /**
     * Download building data in specified format
     *
     * @param Request $request
     * @param int $id Dataset ID
     * @return StreamedResponse|BinaryFileResponse
     */
    #[OperationId('downloadDataset')]
    #[Summary('Download dataset')]
    #[Description('Download building data from a dataset in CSV or GeoJSON format. Supports downloading entire datasets or individual buildings based on user entitlements.')]
    #[Parameters([
        'format' => 'string|optional|Download format (csv, geojson) - defaults to csv',
        'building_gid' => 'string|optional|Specific building GID to download (downloads single building instead of entire dataset)'
    ])]
    #[ScrambleResponse(200, 'File download started', [
        'Content-Type' => 'text/csv or application/geo+json',
        'Content-Disposition' => 'attachment; filename="buildings_dataset_2024-01-01.csv"'
    ])]
    #[ScrambleResponse(400, 'Invalid format or parameters', [
        'message' => 'Invalid format. Supported formats: csv, geojson'
    ])]
    #[ScrambleResponse(403, 'Access denied', [
        'message' => 'You do not have permission to download data in this format'
    ])]
    #[ScrambleResponse(404, 'Dataset or building not found', [
        'message' => 'Dataset not found'
    ])]
    #[ScrambleResponse(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
    public function download(Request $request, int $id)
    {
        // 1. Authentication & Initial Middleware - already handled by auth:sanctum
        $user = $request->user();

        // 2. Entitlement & Format Check
        $format = $request->query('format', 'csv');
        $buildingGid = $request->query('building_gid'); // Optional parameter for single building download

        // Validate format
        if (!in_array($format, ['csv', 'geojson'])) {
            abort(400, 'Invalid format. Supported formats: csv, geojson');
        }

        // Check if user has access to download this dataset in this format
        if (!$this->entitlementService->canDownloadFormat($user, $format)) {
            abort(403, 'You do not have permission to download data in this format');
        }

        // Verify dataset exists
        $dataset = Dataset::find($id);
        if (!$dataset) {
            abort(404, 'Dataset not found');
        }

        // Check if user has access to this specific dataset
        $entitlements = $this->entitlementService->getUserEntitlements($user);
        $userEntitlements = $this->entitlementService->generateEntitlementFilters($entitlements);

        // If user has no DS-ALL access to this dataset, check other entitlements
        if (!in_array($id, $userEntitlements['ds_all_datasets'])) {
            // User must have either DS-AOI or DS-BLD access to access this dataset
            if (empty($userEntitlements['ds_aoi_polygons']) && empty($userEntitlements['ds_building_gids'])) {
                abort(403, 'You do not have permission to access this dataset');
            }
        }

        // 3. Retrieve Data for Download
        $query = Building::query()->where('dataset_id', $id);

        // If building_gid is specified, filter for that specific building
        if ($buildingGid) {
            $query = $query->where('gid', $buildingGid);
            
            // Verify the building exists
            if (!$query->exists()) {
                abort(404, 'Building not found');
            }
        }

        // Apply download-specific ABAC logic - only include entitlements with download formats
        $entitlements = $this->entitlementService->getUserEntitlements($user);
        $downloadFilters = $this->entitlementService->generateDownloadEntitlementFilters($entitlements);
        $query = $query->applyEntitlementFilters($downloadFilters);

        // Log the download action
        AuditLog::createEntry(
            userId: $user->id,
            action: 'data_download',
            targetType: 'dataset',
            targetId: $dataset->id,
            newValues: [
                'dataset_name' => $dataset->name,
                'format' => $format,
                'building_gid' => $buildingGid,
                'download_type' => $buildingGid ? 'single_building' : 'dataset'
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // 4. Generate & Stream File
        switch ($format) {
            case 'csv':
                return $this->downloadCsv($query, $dataset, $buildingGid);
            case 'geojson':
                return $this->downloadGeoJson($query, $dataset, $buildingGid);

            default:
                abort(400, 'Unsupported format');
        }
    }

    /**
     * Admin download - download complete dataset without entitlement filtering
     *
     * @param Request $request
     * @param int $id Dataset ID
     * @return StreamedResponse|BinaryFileResponse
     */
    #[OperationId('adminDownloadDataset')]
    #[Summary('Admin download dataset')]
    #[Description('Admin endpoint to download complete building data from a dataset in CSV or GeoJSON format. Downloads entire dataset without entitlement filtering.')]
    #[Parameters([
        'format' => 'string|optional|Download format (csv, geojson) - defaults to csv',
        'building_gid' => 'string|optional|Specific building GID to download (downloads single building instead of entire dataset)'
    ])]
    #[ScrambleResponse(200, 'File download started', [
        'Content-Type' => 'text/csv or application/geo+json',
        'Content-Disposition' => 'attachment; filename="buildings_dataset_2024-01-01.csv"'
    ])]
    #[ScrambleResponse(400, 'Invalid format or parameters', [
        'message' => 'Invalid format. Supported formats: csv, geojson'
    ])]
    #[ScrambleResponse(403, 'Access denied', [
        'message' => 'Admin privileges required'
    ])]
    #[ScrambleResponse(404, 'Dataset or building not found', [
        'message' => 'Dataset not found'
    ])]
    #[ScrambleResponse(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
    public function adminDownload(Request $request, int $id)
    {
        // 1. Authentication & Admin Check - already handled by auth:sanctum and auth.admin.api
        $user = $request->user();

        // Verify user is admin
        if (!$user->isAdmin()) {
            abort(403, 'Admin privileges required');
        }

        // 2. Format Check
        $format = $request->query('format', 'csv');
        $buildingGid = $request->query('building_gid'); // Optional parameter for single building download

        // Validate format
        if (!in_array($format, ['csv', 'geojson'])) {
            abort(400, 'Invalid format. Supported formats: csv, geojson');
        }

        // Verify dataset exists
        $dataset = Dataset::find($id);
        if (!$dataset) {
            abort(404, 'Dataset not found');
        }

        // 3. Retrieve Data for Download (no entitlement filtering for admin)
        $query = Building::query()->where('dataset_id', $id);

        // If building_gid is specified, filter for that specific building
        if ($buildingGid) {
            $query = $query->where('gid', $buildingGid);
            
            // Verify the building exists
            if (!$query->exists()) {
                abort(404, 'Building not found');
            }
        }

        // Log the admin download action
        AuditLog::createEntry(
            userId: $user->id,
            action: 'admin_data_download',
            targetType: 'dataset',
            targetId: $dataset->id,
            newValues: [
                'dataset_name' => $dataset->name,
                'format' => $format,
                'building_gid' => $buildingGid,
                'download_type' => $buildingGid ? 'single_building' : 'complete_dataset'
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // 4. Generate & Stream File
        switch ($format) {
            case 'csv':
                return $this->downloadCsv($query, $dataset, $buildingGid);
            case 'geojson':
                return $this->downloadGeoJson($query, $dataset, $buildingGid);

            default:
                abort(400, 'Unsupported format');
        }
    }

    /**
     * Get all datasets for admin downloads
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[OperationId('adminGetDatasets')]
    #[Summary('Get all datasets for admin downloads')]
    #[Description('Get all available datasets for admin download functionality.')]
    #[ScrambleResponse(200, 'List of datasets', [
        'datasets' => [
            [
                'id' => 1,
                'name' => 'Thermal Dataset 2024',
                'data_type' => 'building_anomalies',
                'description' => 'Thermal imaging data for buildings',
                'version' => '1.0'
            ]
        ]
    ])]
    #[ScrambleResponse(403, 'Access denied', [
        'message' => 'Admin privileges required'
    ])]
    #[ScrambleResponse(401, 'Authentication required', [
        'message' => 'Authentication required'
    ])]
    public function adminDatasets(Request $request): JsonResponse
    {
        // Verify user is admin
        $user = $request->user();
        if (!$user->isAdmin()) {
            abort(403, 'Admin privileges required');
        }

        // Get all datasets
        $datasets = Dataset::select('id', 'name', 'data_type', 'description', 'version')
            ->orderBy('name')
            ->get();

        return response()->json([
            'datasets' => $datasets
        ]);
    }

    /**
     * Generate CSV download using PostgreSQL COPY command for performance
     */
    private function downloadCsv($query, Dataset $dataset, $buildingGid = null): StreamedResponse
    {
        if ($buildingGid) {
            $filename = "building_{$buildingGid}_data.csv";
        } else {
            $filename = "buildings_{$dataset->name}_{$dataset->version}_" . date('Y-m-d') . ".csv";
        }

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Write CSV header
            fputcsv($handle, [
                'gid',
                'thermal_loss_index_tli',
                'building_type_classification',
                'co2_savings_estimate',
                'address',
                'owner_operator_details',
                'cadastral_reference',
                'geometry_wkt',
                'dataset_id',
                'created_at',
                'updated_at'
            ]);

            // Stream data in chunks for memory efficiency
            $query->chunk(1000, function ($buildings) use ($handle) {
                foreach ($buildings as $building) {
                    fputcsv($handle, [
                        $building->gid,
                        $building->thermal_loss_index_tli,
                        $building->building_type_classification,
                        $building->co2_savings_estimate,
                        $building->address,
                        $building->owner_operator_details,
                        $building->cadastral_reference,
                        $building->geometry ? $building->geometry->toWkt() : null,
                        $building->dataset_id,
                        $building->created_at,
                        $building->updated_at
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    /**
     * Generate GeoJSON download
     */
    private function downloadGeoJson($query, Dataset $dataset, $buildingGid = null): StreamedResponse
    {
        if ($buildingGid) {
            $filename = "building_{$buildingGid}_data.geojson";
        } else {
            $filename = "buildings_{$dataset->name}_{$dataset->version}_" . date('Y-m-d') . ".geojson";
        }

        return response()->stream(function () use ($query) {
            echo '{"type":"FeatureCollection","features":[';

            $first = true;
            $query->chunk(1000, function ($buildings) use (&$first) {
                foreach ($buildings as $building) {
                    if (!$first) {
                        echo ',';
                    }
                    $first = false;

                    $feature = [
                        'type' => 'Feature',
                        'geometry' => $building->geometry ? json_decode($building->geometry->toJson()) : null,
                        'properties' => [
                            'gid' => $building->gid,
                            'thermal_loss_index_tli' => $building->thermal_loss_index_tli,
                            'building_type_classification' => $building->building_type_classification,
                            'co2_savings_estimate' => $building->co2_savings_estimate,
                            'address' => $building->address,
                            'owner_operator_details' => $building->owner_operator_details,
                            'cadastral_reference' => $building->cadastral_reference,
                            'dataset_id' => $building->dataset_id,
                            'created_at' => $building->created_at?->toISOString(),
                            'updated_at' => $building->updated_at?->toISOString()
                        ]
                    ];

                    echo json_encode($feature, JSON_UNESCAPED_UNICODE);
                }
            });

            echo ']}';
        }, 200, [
            'Content-Type' => 'application/geo+json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

}
