<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;

#[Tag('Admin - Tiles')]
class TilesController extends Controller
{
    /**
     * Get all available tile layers (for admin use in entitlement management)
     */
    #[OperationId('admin.tiles.layers')]
    #[Summary('Get all available tile layers')]
    #[Description('Get a list of all available tile layers for admin entitlement management.')]
    #[Response(200, 'All tile layers retrieved', [
        'layers' => [
            [
                'name' => 'thermal_layer_1',
                'display_name' => 'Thermal Layer 1',
                'path' => 'thermal_layer_1'
            ],
            [
                'name' => 'heat_map_2024',
                'display_name' => 'Heat Map 2024',
                'path' => 'heat_map_2024'
            ]
        ]
    ])]
    public function getAllLayers(): JsonResponse
    {
        $tilesPath = storage_path('data/tiles');
        
        if (!File::exists($tilesPath)) {
            return response()->json(['layers' => []]);
        }

        $layers = [];
        $directories = File::directories($tilesPath);
        
        foreach ($directories as $directory) {
            $layerName = basename($directory);
            
            // Check if this directory contains tile structure
            $hasTiles = false;
            $zoomLevels = File::directories($directory);
            
            foreach ($zoomLevels as $zoomDir) {
                $zoomLevel = basename($zoomDir);
                if (is_numeric($zoomLevel)) {
                    $hasTiles = true;
                    break;
                }
            }
            
            if ($hasTiles) {
                $layers[] = [
                    'name' => $layerName,
                    'display_name' => ucwords(str_replace('_', ' ', $layerName)),
                    'path' => $layerName
                ];
            }
        }

        return response()->json(['layers' => $layers]);
    }
} 