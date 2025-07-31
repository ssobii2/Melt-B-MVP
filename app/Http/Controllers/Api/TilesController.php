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

    /**
     * Get tile layer bounds from tilemapresource.xml
     */
    public function getBounds(Request $request, $layer)
    {
        $tilesPath = storage_path("data/tiles/{$layer}");
        
        if (!File::exists($tilesPath)) {
            return response()->json(['error' => 'Layer not found'], 404);
        }

        // Look for any .xml file in the layer directory
        $xmlFiles = File::glob("{$tilesPath}/*.xml");
        
        if (empty($xmlFiles)) {
            return response()->json(['error' => 'No tilemapresource.xml found'], 404);
        }

        // Use the first .xml file found
        $xmlFile = $xmlFiles[0];
        $xmlContent = File::get($xmlFile);
        
        // Simple XML parsing to extract bounds
        if (preg_match('/<BoundingBox\s+minx="([^"]+)"\s+miny="([^"]+)"\s+maxx="([^"]+)"\s+maxy="([^"]+)"/', $xmlContent, $matches)) {
            return response()->json([
                'minx' => (float) $matches[1],
                'miny' => (float) $matches[2],
                'maxx' => (float) $matches[3],
                'maxy' => (float) $matches[4]
            ]);
        }

        return response()->json(['error' => 'Could not parse bounds from XML'], 404);
    }
} 