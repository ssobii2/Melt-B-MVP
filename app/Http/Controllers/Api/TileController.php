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
            // Manually authenticate user – bearer token or ?token query param
            $user = null;

            // 1. Bearer token header
            $bearerToken = $request->bearerToken();
            if ($bearerToken) {
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
                if ($personalAccessToken && (!$personalAccessToken->expires_at || !$personalAccessToken->expires_at->isPast())) {
                    $user = $personalAccessToken->tokenable;
                }
            }

            // 2. Query-string token (?token=...)
            if (!$user && $request->has('token')) {
                $token = $request->input('token');
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($personalAccessToken && (!$personalAccessToken->expires_at || !$personalAccessToken->expires_at->isPast())) {
                    $user = $personalAccessToken->tokenable;
                }
            }

            // 3. If request originated from authenticated session (unlikely for SPA) fallback
            if (!$user) {
                $user = $request->user();
            }
            
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
        // The Point constructor expects (latitude, longitude) order.
        $points = [
            new Point($latMin, $lonMin), // SW
            new Point($latMin, $lonMax), // SE  
            new Point($latMax, $lonMax), // NE
            new Point($latMax, $lonMin), // NW
            new Point($latMin, $lonMin)  // Close polygon
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
        // Retrieve all active entitlements for this dataset relevant to tiles
        $entitlements = $user->entitlements()
            ->where(function ($query) use ($datasetId) {
                $query->where(function ($q) use ($datasetId) {
                    $q->where('type', 'TILES')
                      ->where('dataset_id', $datasetId);
                })
                ->orWhere(function ($q) use ($datasetId) {
                    // DS-ALL also gives full dataset access including tiles
                    $q->where('type', 'DS-ALL')
                      ->where('dataset_id', $datasetId);
                });
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($entitlements->isEmpty()) {
            return false; // No relevant entitlements at all
        }

        foreach ($entitlements as $entitlement) {
            // DS-ALL has no AOI restriction -> automatic access
            if ($entitlement->type === 'DS-ALL') {
                return true;
            }

            // TILES with null aoi_geom means full dataset coverage
            if ($entitlement->type === 'TILES' && is_null($entitlement->aoi_geom)) {
                return true;
            }

            // Otherwise need spatial intersection check
            if ($entitlement->aoi_geom) {
                $intersects = Entitlement::where('id', $entitlement->id)
                    ->whereRaw('ST_Intersects(aoi_geom, ST_GeomFromText(?, 4326))', [$tileBbox->toWkt()])
                    ->exists();

                if ($intersects) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retrieve tile from object storage - no mock tile generation
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

        // No mock tiles - return null for missing tiles
        return null;
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
