<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class TilesController extends Controller
{
    /**
     * Serve tile files from storage/data/tiles directory
     */
    public function serveTile(Request $request, $layer, $z, $x, $y)
    {
        // Validate parameters
        if (!is_numeric($z) || !is_numeric($x) || !is_numeric($y)) {
            return response()->json(['error' => 'Invalid tile coordinates'], 400);
        }

        // Convert XYZ coordinates to TMS coordinates (flip Y axis)
        $tmsY = (1 << $z) - 1 - $y;

        // Construct the tile path with TMS coordinates
        $tilePath = "data/tiles/{$layer}/{$z}/{$x}/{$tmsY}.png";
        $fullPath = storage_path($tilePath);
        
        // Check if tile exists using direct file system access
        if (!File::exists($fullPath)) {
            // Return 204 No Content for missing tiles - MapLibre will handle this gracefully
            return response('', 204);
        }

        // Get tile content using direct file access
        $tileContent = File::get($fullPath);
        
        // Return tile with proper headers
        return response($tileContent)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400'); // Cache for 24 hours
    }

    /**
     * Get available tile layers
     */
    public function getLayers()
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