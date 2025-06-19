<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Models\Entitlement;
use App\Services\UserEntitlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;

class TileController extends Controller
{
    protected $entitlementService;

    public function __construct(UserEntitlementService $entitlementService)
    {
        $this->entitlementService = $entitlementService;
    }

    /**
     * Serve map tiles with ABAC entitlement checking
     * 
     * @param Request $request
     * @param string $datasetId
     * @param int $z Zoom level
     * @param int $x Tile column
     * @param int $y Tile row
     * @return Response
     */
    public function serveTile(Request $request, string $datasetId, int $z, int $x, int $y)
    {
        try {
            // Get authenticated user
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            // Validate dataset exists
            $dataset = Dataset::find($datasetId);
            if (!$dataset) {
                return response()->json(['message' => 'Dataset not found'], 404);
            }

            // Validate tile coordinates (basic sanity check)
            if ($z < 0 || $z > 20 || $x < 0 || $y < 0) {
                return response()->json(['message' => 'Invalid tile coordinates'], 400);
            }

            // Calculate tile bounding box (geographic coordinates)
            $tileBbox = $this->calculateTileBoundingBox($z, $x, $y);

            // Check TILES entitlements for this user and dataset
            $hasAccess = $this->checkTileAccess($user, $datasetId, $tileBbox);

            if (!$hasAccess) {
                Log::info("Tile access denied for user {$user->id} on dataset {$datasetId} tile {$z}/{$x}/{$y}");
                return response()->json([
                    'message' => 'Access denied: No valid TILES entitlement for this area',
                    'error' => 'insufficient_entitlements'
                ], 403);
            }

            // Retrieve tile from storage (or generate mock tile for testing)
            $tileContent = $this->retrieveTile($dataset, $z, $x, $y);

            if ($tileContent === null) {
                // Return transparent 256x256 PNG for missing tiles
                return $this->generateTransparentTile();
            }

            // Return tile with proper headers
            return response($tileContent)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('Expires', now()->addHour()->toRfc7231String());
        } catch (\Exception $e) {
            Log::error("Tile serving error: " . $e->getMessage(), [
                'dataset_id' => $datasetId,
                'z' => $z,
                'x' => $x,
                'y' => $y,
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'message' => 'Error serving tile',
                'error' => 'tile_serving_error'
            ], 500);
        }
    }

    /**
     * Calculate geographic bounding box for a tile
     * 
     * @param int $z Zoom level
     * @param int $x Tile column  
     * @param int $y Tile row
     * @return Polygon PostGIS polygon representing tile bounds
     */
    protected function calculateTileBoundingBox(int $z, int $x, int $y): Polygon
    {
        // Web Mercator tile calculation
        $n = pow(2, $z);

        // Calculate longitude bounds
        $lonMin = ($x / $n) * 360.0 - 180.0;
        $lonMax = (($x + 1) / $n) * 360.0 - 180.0;

        // Calculate latitude bounds (more complex due to Web Mercator projection)
        $latRadMin = atan(sinh(pi() * (1 - 2 * ($y + 1) / $n)));
        $latRadMax = atan(sinh(pi() * (1 - 2 * $y / $n)));

        $latMin = rad2deg($latRadMin);
        $latMax = rad2deg($latRadMax);

        // Create polygon representing tile bounding box (counter-clockwise)
        // Note: Point constructor expects (longitude, latitude) order
        $points = [
            new Point($lonMin, $latMin), // SW
            new Point($lonMax, $latMin), // SE  
            new Point($lonMax, $latMax), // NE
            new Point($lonMin, $latMax), // NW
            new Point($lonMin, $latMin)  // Close polygon
        ];

        return new Polygon([new LineString($points)]);
    }

    /**
     * Check if user has TILES entitlement that intersects with tile bounding box
     * 
     * @param \App\Models\User $user
     * @param string $datasetId
     * @param Polygon $tileBbox
     * @return bool
     */
    protected function checkTileAccess($user, string $datasetId, Polygon $tileBbox): bool
    {
        // Get user's active TILES entitlements
        $tilesEntitlements = $user->entitlements()
            ->where('type', 'TILES')
            ->where('dataset_id', $datasetId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        // If no TILES entitlements, deny access
        if ($tilesEntitlements->isEmpty()) {
            return false;
        }

        // Check if any TILES entitlement AOI intersects with tile bounding box
        foreach ($tilesEntitlements as $entitlement) {
            if ($entitlement->aoi_geom) {
                // Use PostGIS spatial intersection check
                $intersects = Entitlement::where('id', $entitlement->id)
                    ->whereRaw('ST_Intersects(aoi_geom, ST_GeomFromText(?, 4326))', [$tileBbox->toWkt()])
                    ->exists();

                if ($intersects) {
                    Log::info("Tile access granted via entitlement {$entitlement->id} for user {$user->id}");
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retrieve tile from object storage or generate mock tile
     * 
     * @param Dataset $dataset
     * @param int $z
     * @param int $x  
     * @param int $y
     * @return string|null Tile content or null if not found
     */
    protected function retrieveTile(Dataset $dataset, int $z, int $x, int $y): ?string
    {
        // Construct tile path based on dataset storage location
        $tilePath = "thermal_rasters/{$dataset->name}/{$z}/{$x}/{$y}.png";

        // Check if tile exists in storage
        if (Storage::exists($tilePath)) {
            return Storage::get($tilePath);
        }

        // For testing purposes, generate a mock thermal tile if within our test area
        return $this->generateMockTile($dataset, $z, $x, $y);
    }

    /**
     * Generate a mock thermal tile for testing purposes
     * 
     * @param Dataset $dataset
     * @param int $z
     * @param int $x
     * @param int $y
     * @return string|null
     */
    protected function generateMockTile(Dataset $dataset, int $z, int $x, int $y): ?string
    {
        // Only generate mock tiles for reasonable zoom levels and our test area (Copenhagen)
        if ($z < 10 || $z > 18) {
            return null;
        }

        // Calculate tile center to check if it's in our test area (Copenhagen: ~55.7°N, 12.55°E)
        $tileBbox = $this->calculateTileBoundingBox($z, $x, $y);

        // Extract bounds from WKT 
        // Note: The WKT output format appears to be (lat lon) but we need (lon lat)
        $wkt = $tileBbox->toWkt();
        preg_match('/POLYGON\(\(([^)]+)\)\)/', $wkt, $matches);
        $coords = explode(',', $matches[1]);
        $point1 = explode(' ', trim($coords[0]));
        $point3 = explode(' ', trim($coords[2]));

        // Check if this is (lat, lon) format and swap if needed
        $coord1_1 = floatval($point1[0]);
        $coord1_2 = floatval($point1[1]);
        $coord3_1 = floatval($point3[0]);
        $coord3_2 = floatval($point3[1]);

        // If first coordinate is > 180 or < -180, it's likely latitude, so swap
        if (
            abs($coord1_1) > 180 || abs($coord3_1) > 180 ||
            ($coord1_1 > 40 && $coord1_1 < 50 && $coord1_2 > 15 && $coord1_2 < 30)
        ) {
            // Coordinates appear to be (lat, lon), so swap them
            $centerLon = ($coord1_2 + $coord3_2) / 2;
            $centerLat = ($coord1_1 + $coord3_1) / 2;
        } else {
            // Coordinates are (lon, lat)
            $centerLon = ($coord1_1 + $coord3_1) / 2;
            $centerLat = ($coord1_2 + $coord3_2) / 2;
        }

        // Check if tile center is near Copenhagen (our test data area) 
        // Copenhagen coordinates: ~55.7°N, 12.55°E
        if ($centerLat < 55.5 || $centerLat > 55.9 || $centerLon < 12.0 || $centerLon > 13.0) {
            return null; // Outside our test area
        }

        // Create a simple thermal-colored tile (256x256 PNG)
        $image = imagecreate(256, 256);

        // Create thermal color palette
        $bgColor = imagecolorallocate($image, 0, 0, 0); // Black background (transparent will be made later)
        $coldColor = imagecolorallocate($image, 0, 0, 255);    // Blue (cold)
        $warmColor = imagecolorallocate($image, 255, 255, 0);  // Yellow (warm)
        $hotColor = imagecolorallocate($image, 255, 0, 0);     // Red (hot)

        // Make background transparent
        imagecolortransparent($image, $bgColor);

        // Add some thermal patterns based on tile coordinates (pseudo-random thermal data)
        for ($i = 0; $i < 256; $i += 16) {
            for ($j = 0; $j < 256; $j += 16) {
                $thermal = (sin($i / 32) + cos($j / 32) + sin(($x + $y) / 4)) / 3;

                if ($thermal > 0.3) {
                    $color = $hotColor;
                } elseif ($thermal > -0.3) {
                    $color = $warmColor;
                } else {
                    $color = $coldColor;
                }

                // Add some randomness
                if (rand(0, 100) < 30) {
                    imagefilledrectangle($image, $i, $j, $i + 15, $j + 15, $color);
                }
            }
        }

        // Generate PNG content
        ob_start();
        imagepng($image);
        $content = ob_get_contents();
        ob_end_clean();

        imagedestroy($image);

        return $content;
    }

    /**
     * Generate a transparent 256x256 PNG tile
     * 
     * @return Response
     */
    protected function generateTransparentTile(): Response
    {
        $image = imagecreate(256, 256);
        $transparent = imagecolorallocate($image, 0, 0, 0);
        imagecolortransparent($image, $transparent);

        ob_start();
        imagepng($image);
        $content = ob_get_contents();
        ob_end_clean();

        imagedestroy($image);

        return response($content)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
