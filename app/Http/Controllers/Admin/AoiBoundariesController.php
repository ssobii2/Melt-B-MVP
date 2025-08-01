<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use Illuminate\Http\JsonResponse;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;

#[Tag('Admin - AOI Boundaries')]
#[Response(401, 'Unauthorized')]
#[Response(403, 'Forbidden')]
class AoiBoundariesController extends Controller
{
    /**
     * Get all AOI geometries for map display.
     */
    #[OperationId('admin.aoi-boundaries.all')]
    #[Summary('Get all AOI geometries')]
    #[Description('Get all existing AOI geometries as GeoJSON FeatureCollection for displaying on the map. Includes AOI geometries from DS-AOI and TILES entitlements. Admin users have unrestricted access to all AOI boundaries.')]
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
    public function all(): JsonResponse
    {
        // Get all entitlements with AOI geometries (DS-AOI and TILES types)
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