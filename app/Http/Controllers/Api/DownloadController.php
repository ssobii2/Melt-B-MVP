<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Dataset;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
    public function download(Request $request, int $id)
    {
        // 1. Authentication & Initial Middleware - already handled by auth:sanctum
        $user = $request->user();

        // 2. Entitlement & Format Check
        $format = $request->query('format', 'csv');

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

        // Apply the same ABAC logic from GET /api/buildings
        $query = $query->applyEntitlementFilters($user);

        // 4. Generate & Stream File
        switch ($format) {
            case 'csv':
                return $this->downloadCsv($query, $dataset);
            case 'geojson':
                return $this->downloadGeoJson($query, $dataset);

            default:
                abort(400, 'Unsupported format');
        }
    }

    /**
     * Generate CSV download using PostgreSQL COPY command for performance
     */
    private function downloadCsv($query, Dataset $dataset): StreamedResponse
    {
        $filename = "buildings_{$dataset->name}_{$dataset->version}_" . date('Y-m-d') . ".csv";

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
    private function downloadGeoJson($query, Dataset $dataset): StreamedResponse
    {
        $filename = "buildings_{$dataset->name}_{$dataset->version}_" . date('Y-m-d') . ".geojson";

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
