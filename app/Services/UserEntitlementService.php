<?php

namespace App\Services;

use App\Models\User;
use App\Models\Entitlement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class UserEntitlementService
{
    /**
     * Cache TTL in minutes (55 minutes to refresh before token expiry)
     */
    const CACHE_TTL = 55;

    /**
     * Get all active entitlements for a user with caching
     */
    public function getUserEntitlements(User $user): Collection
    {
        $cacheKey = "user_entitlements_{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($user) {
            return $user->entitlements()
                ->with('dataset:id,name,data_type')
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->get();
        });
    }

    /**
     * Generate entitlement filters for different data access types
     */
    public function generateEntitlementFilters(Collection $entitlements): array
    {
        $filters = [
            'ds_all_datasets' => [],
            'ds_aoi_polygons' => [],
            'ds_building_gids' => [],
            'tiles_aoi_polygons' => [],
            'allowed_download_formats' => []
        ];

        foreach ($entitlements as $entitlement) {
            // Skip expired entitlements
            if ($entitlement->isExpired()) {
                continue;
            }

            switch ($entitlement->type) {
                case 'DS-ALL':
                    $filters['ds_all_datasets'][] = $entitlement->dataset_id;
                    break;

                case 'DS-AOI':
                    if ($entitlement->aoi_geom) {
                        $filters['ds_aoi_polygons'][] = [
                            'dataset_id' => $entitlement->dataset_id,
                            'geometry' => $entitlement->aoi_geom
                        ];
                    }
                    break;

                case 'DS-BLD':
                    if ($entitlement->building_gids && is_array($entitlement->building_gids)) {
                        $filters['ds_building_gids'] = array_merge(
                            $filters['ds_building_gids'],
                            $entitlement->building_gids
                        );
                    }
                    break;

                case 'TILES':
                    if ($entitlement->aoi_geom) {
                        $filters['tiles_aoi_polygons'][] = [
                            'dataset_id' => $entitlement->dataset_id,
                            'geometry' => $entitlement->aoi_geom
                        ];
                    }
                    break;
            }

            // Collect allowed download formats
            if ($entitlement->download_formats && is_array($entitlement->download_formats)) {
                $filters['allowed_download_formats'] = array_merge(
                    $filters['allowed_download_formats'],
                    $entitlement->download_formats
                );
            }
        }

        // Remove duplicates
        $filters['ds_all_datasets'] = array_unique($filters['ds_all_datasets']);
        $filters['ds_building_gids'] = array_unique($filters['ds_building_gids']);
        $filters['allowed_download_formats'] = array_unique($filters['allowed_download_formats']);

        return $filters;
    }

    /**
     * Check if user has access to a specific dataset
     */
    public function hasDatasetAccess(User $user, int $datasetId): bool
    {
        $entitlements = $this->getUserEntitlements($user);
        $filters = $this->generateEntitlementFilters($entitlements);

        // Check if user has DS-ALL access to this dataset
        return in_array($datasetId, $filters['ds_all_datasets']) ||
            !empty($filters['ds_aoi_polygons']) ||
            !empty($filters['ds_building_gids']);
    }

    /**
     * Check if user can download in a specific format
     */
    public function canDownloadFormat(User $user, string $format): bool
    {
        $entitlements = $this->getUserEntitlements($user);
        $filters = $this->generateEntitlementFilters($entitlements);

        return in_array($format, $filters['allowed_download_formats']);
    }

    /**
     * Generate entitlement filters specifically for downloads
     * Only includes entitlements that have download formats assigned
     */
    public function generateDownloadEntitlementFilters(Collection $entitlements): array
    {
        $filters = [
            'ds_all_datasets' => [],
            'ds_aoi_polygons' => [],
            'ds_building_gids' => [],
            'allowed_download_formats' => []
        ];

        foreach ($entitlements as $entitlement) {
            // Skip expired entitlements
            if ($entitlement->isExpired()) {
                continue;
            }

            // Only include entitlements that have download formats assigned
            if (!$entitlement->download_formats || !is_array($entitlement->download_formats) || empty($entitlement->download_formats)) {
                continue;
            }

            switch ($entitlement->type) {
                case 'DS-ALL':
                    $filters['ds_all_datasets'][] = $entitlement->dataset_id;
                    break;

                case 'DS-AOI':
                    if ($entitlement->aoi_geom) {
                        $filters['ds_aoi_polygons'][] = [
                            'dataset_id' => $entitlement->dataset_id,
                            'geometry' => $entitlement->aoi_geom
                        ];
                    }
                    break;

                case 'DS-BLD':
                    if ($entitlement->building_gids && is_array($entitlement->building_gids)) {
                        $filters['ds_building_gids'] = array_merge(
                            $filters['ds_building_gids'],
                            $entitlement->building_gids
                        );
                    }
                    break;
            }

            // Collect allowed download formats
            $filters['allowed_download_formats'] = array_merge(
                $filters['allowed_download_formats'],
                $entitlement->download_formats
            );
        }

        // Remove duplicates
        $filters['ds_all_datasets'] = array_unique($filters['ds_all_datasets']);
        $filters['ds_building_gids'] = array_unique($filters['ds_building_gids']);
        $filters['allowed_download_formats'] = array_unique($filters['allowed_download_formats']);

        return $filters;
    }

    /**
     * Check if user has access to a specific tile layer
     */
    public function hasTileAccess(User $user, string $tileLayer): bool
    {
        $entitlements = $this->getUserEntitlements($user);
        
        foreach ($entitlements as $entitlement) {
            // Skip expired entitlements
            if ($entitlement->isExpired()) {
                continue;
            }

            // Check if this is a TILES entitlement
            if ($entitlement->type === 'TILES') {
                // Check if the tile layer is in the allowed tile layers
                if ($entitlement->tile_layers && is_array($entitlement->tile_layers)) {
                    if (in_array($tileLayer, $entitlement->tile_layers)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get all available tile layers for a user
     */
    public function getAvailableTileLayers(User $user): array
    {
        $entitlements = $this->getUserEntitlements($user);
        $availableLayers = [];
        
        foreach ($entitlements as $entitlement) {
            // Skip expired entitlements
            if ($entitlement->isExpired()) {
                continue;
            }

            // Check if this is a TILES entitlement
            if ($entitlement->type === 'TILES') {
                // Add tile layers to available list
                if ($entitlement->tile_layers && is_array($entitlement->tile_layers)) {
                    $availableLayers = array_merge($availableLayers, $entitlement->tile_layers);
                }
            }
        }

        // Remove duplicates and return unique layers
        return array_unique($availableLayers);
    }

    /**
     * Clear cached entitlements for a user
     */
    public function clearUserEntitlementsCache(User $user): void
    {
        $cacheKey = "user_entitlements_{$user->id}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear cached entitlements for all users (when entitlements are modified)
     */
    public function clearAllEntitlementsCache(): void
    {
        // In a production environment, you might want to use Redis tags
        // For now, we'll clear specific keys when we know they're affected
        Cache::flush();
    }
}
