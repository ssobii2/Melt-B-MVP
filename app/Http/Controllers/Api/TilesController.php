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

        // Validate zoom level to prevent integer overflow
        $z = (int) $z;
        if ($z < 0 || $z > 24) {
            return response()->json(['error' => 'Invalid zoom level. Must be between 0 and 24.'], 400);
        }

        // Validate tile coordinates
        $x = (int) $x;
        $y = (int) $y;
        $maxTile = (1 << $z) - 1;
        if ($x < 0 || $x > $maxTile || $y < 0 || $y > $maxTile) {
            return response()->json(['error' => 'Tile coordinates out of bounds for zoom level.'], 400);
        }

        // Sanitize layer parameter to prevent directory traversal attacks
        $layer = $this->sanitizeLayerName($layer);
        if ($layer === null) {
            return response()->json(['error' => 'Invalid layer name'], 400);
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
        // Sanitize layer parameter to prevent directory traversal attacks
        $layer = $this->sanitizeLayerName($layer);
        if ($layer === null) {
            return response()->json(['error' => 'Invalid layer name'], 400);
        }

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
            // Extract and validate coordinate values
            $minx = (float) $matches[1];
            $miny = (float) $matches[2];
            $maxx = (float) $matches[3];
            $maxy = (float) $matches[4];
            
            // Validate coordinate bounds to ensure they are within reasonable geographic limits
            if (!$this->validateCoordinateBounds($minx, $miny, $maxx, $maxy)) {
                return response()->json(['error' => 'Invalid coordinate bounds in XML file'], 400);
            }
            
            return response()->json([
                'minx' => $minx,
                'miny' => $miny,
                'maxx' => $maxx,
                'maxy' => $maxy
            ]);
        }

        return response()->json(['error' => 'Could not parse bounds from XML'], 404);
    }

    /**
     * Sanitize layer name to prevent directory traversal attacks
     * 
     * @param string $layerName
     * @return string|null Returns sanitized name or null if invalid
     */
    private function sanitizeLayerName($layerName)
    {
        // Remove any directory traversal sequences
        $sanitized = str_replace(['../', '..\\', './', '.\\'], '', $layerName);
        
        // Remove any null bytes
        $sanitized = str_replace("\0", '', $sanitized);
        
        // Only allow alphanumeric characters, hyphens, and underscores
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $sanitized)) {
            return null;
        }
        
        // Prevent empty or too long names
        if (empty($sanitized) || strlen($sanitized) > 100) {
            return null;
        }
        
        return $sanitized;
    }

    /**
     * Validate coordinate bounds to ensure they are within reasonable geographic limits
     * 
     * @param float $minx
     * @param float $miny
     * @param float $maxx
     * @param float $maxy
     * @return bool Returns true if coordinates are valid, false otherwise
     */
    private function validateCoordinateBounds($minx, $miny, $maxx, $maxy)
    {
        // Check if coordinates are finite numbers (not NaN or infinite)
        if (!is_finite($minx) || !is_finite($miny) || !is_finite($maxx) || !is_finite($maxy)) {
            return false;
        }
        
        // Validate longitude bounds (EPSG:4326 / WGS84)
        // Longitude ranges from -180 to +180 degrees
        if ($minx < -180 || $maxx > 180 || $minx > $maxx) {
            return false;
        }
        
        // Validate latitude bounds (EPSG:4326 / WGS84)
        // Latitude ranges from -90 to +90 degrees
        if ($miny < -90 || $maxy > 90 || $miny > $maxy) {
            return false;
        }
        
        // Check for reasonable bounds size (prevent extremely large or small areas)
        $lonDiff = $maxx - $minx;
        $latDiff = $maxy - $miny;
        
        // Bounds should not be empty or extremely small
        if ($lonDiff <= 0 || $latDiff <= 0) {
            return false;
        }
        
        // Bounds should not be unreasonably large (more than 360 degrees longitude or 180 degrees latitude)
        if ($lonDiff > 360 || $latDiff > 180) {
            return false;
        }
        
        return true;
    }
} 