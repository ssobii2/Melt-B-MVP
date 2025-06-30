<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Building extends Model
{
    use HasSpatial;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'gid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'gid',
        'geometry',
        'average_heatloss',
        'reference_heatloss',
        'heatloss_difference',
        'abs_heatloss_difference',
        'threshold',
        'is_anomaly',
        'confidence',
        'building_type_classification',
        'co2_savings_estimate',
        'address',
        'owner_operator_details',
        'cadastral_reference',
        'dataset_id',
        'last_analyzed_at',
        'before_renovation_tli',
        'after_renovation_tli',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'geometry' => Polygon::class,
        'average_heatloss' => 'decimal:4',
        'reference_heatloss' => 'decimal:4',
        'heatloss_difference' => 'decimal:4',
        'abs_heatloss_difference' => 'decimal:4',
        'threshold' => 'decimal:4',
        'is_anomaly' => 'boolean',
        'confidence' => 'decimal:4',
        'co2_savings_estimate' => 'decimal:2',
        'last_analyzed_at' => 'datetime',
        'before_renovation_tli' => 'integer',
        'after_renovation_tli' => 'integer',
    ];

    /**
     * Get the dataset that owns the building.
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * Get the building color based on anomaly status.
     * This replaces the old TLI-based coloring system.
     */
    public function getTliColorAttribute(): string
    {
        // For backward compatibility, check if we still have TLI data
        if ($this->thermal_loss_index_tli !== null) {
            $tli = $this->thermal_loss_index_tli;
            if ($tli >= 80) return '#ff0000'; // Red - High loss
            if ($tli >= 60) return '#ff8000'; // Orange
            if ($tli >= 40) return '#ffff00'; // Yellow
            if ($tli >= 20) return '#80ff00'; // Light green
            return '#00ff00'; // Green - Low loss
        }

        // New anomaly-based coloring
        if ($this->is_anomaly === null) {
            return '#808080'; // Gray - No data
        }
        
        return $this->is_anomaly ? '#ff0000' : '#3b82f6'; // Red for anomalies, Blue for normal
    }

    /**
     * Get the anomaly color code based on the anomaly status.
     */
    public function getAnomalyColorAttribute(): string
    {
        if ($this->is_anomaly === null) {
            return '#808080'; // Gray - No data
        }
        
        return $this->is_anomaly ? '#ff0000' : '#3b82f6'; // Red for anomalies, Blue for normal
    }

    /**
     * Get anomaly severity based on heat loss difference.
     */
    public function getAnomalySeverityAttribute(): ?string
    {
        if (!$this->is_anomaly || $this->abs_heatloss_difference === null) {
            return null;
        }

        $absDiff = $this->abs_heatloss_difference;
        if ($absDiff >= 50) return 'critical';
        if ($absDiff >= 25) return 'high';
        if ($absDiff >= 10) return 'medium';
        return 'low';
    }

    /**
     * Check if building is a high-confidence anomaly.
     */
    public function isHighConfidenceAnomaly(): bool
    {
        return $this->is_anomaly && $this->confidence !== null && $this->confidence >= 0.8;
    }

    /**
     * Calculate improvement potential if after renovation TLI is available.
     */
    public function getImprovementPotentialAttribute(): ?int
    {
        if ($this->before_renovation_tli && $this->after_renovation_tli) {
            return $this->before_renovation_tli - $this->after_renovation_tli;
        }

        return null;
    }

    /**
     * Apply entitlement filters to the query based on user's access rights.
     */
    public function scopeApplyEntitlementFilters($query, $user)
    {
        // If user is passed, get the entitlement filters from UserEntitlementService
        if ($user instanceof \App\Models\User) {
            $entitlementService = new \App\Services\UserEntitlementService();
            $userEntitlements = $entitlementService->getUserEntitlements($user);
            $entitlementFilters = $entitlementService->generateEntitlementFilters($userEntitlements);
        } else {
            // Assume it's already an array of filters
            $entitlementFilters = $user;
        }

        // Start with a query that will return no results by default
        $query->where(function ($mainQuery) use ($entitlementFilters) {
            $hasAnyEntitlement = false;

        // If user has DS-ALL access to any dataset, they can see buildings from those datasets
        if (!empty($entitlementFilters['ds_all_datasets'])) {
                $mainQuery->orWhereIn('dataset_id', $entitlementFilters['ds_all_datasets']);
                $hasAnyEntitlement = true;
        }

        // Apply DS-AOI spatial filters
        if (!empty($entitlementFilters['ds_aoi_polygons'])) {
                $mainQuery->orWhere(function ($subQuery) use ($entitlementFilters) {
                foreach ($entitlementFilters['ds_aoi_polygons'] as $aoiFilter) {
                    $subQuery->orWhere(function ($aoiQuery) use ($aoiFilter) {
                        $aoiQuery->where('dataset_id', $aoiFilter['dataset_id'])
                            ->whereRaw('ST_Intersects(geometry, ST_GeomFromText(?, 4326))', [
                                $aoiFilter['geometry']->toWkt()
                            ]);
                    });
                }
            });
                $hasAnyEntitlement = true;
        }

        // Apply DS-BLD building-specific filters
        if (!empty($entitlementFilters['ds_building_gids'])) {
                $mainQuery->orWhereIn('gid', $entitlementFilters['ds_building_gids']);
                $hasAnyEntitlement = true;
            }

            // If no entitlements, return no results
            if (!$hasAnyEntitlement) {
                $mainQuery->whereRaw('1 = 0'); // Always false condition
            }
        });

        return $query;
    }

    /**
     * Apply spatial intersection filter for a specific geometry (used for tiles).
     */
    public function scopeWithinGeometry($query, $geometry)
    {
        return $query->whereRaw('ST_Intersects(geometry, ST_GeomFromText(?, 4326))', [
            $geometry
        ]);
    }

    /**
     * Filter buildings by dataset.
     */
    public function scopeForDataset($query, int $datasetId)
    {
        return $query->where('dataset_id', $datasetId);
    }

    /**
     * Filter buildings by TLI range.
     */
    public function scopeWithTliRange($query, int $minTli = null, int $maxTli = null)
    {
        if ($minTli !== null) {
            $query->where('thermal_loss_index_tli', '>=', $minTli);
        }

        if ($maxTli !== null) {
            $query->where('thermal_loss_index_tli', '<=', $maxTli);
        }

        return $query;
    }

    /**
     * Search buildings by address or cadastral reference.
     */
    public function scopeSearch($query, string $searchTerm)
    {
        return $query->where(function ($subQuery) use ($searchTerm) {
            $subQuery->where('address', 'ILIKE', "%{$searchTerm}%")
                ->orWhere('cadastral_reference', 'ILIKE', "%{$searchTerm}%");
        });
    }

    /**
     * Filter buildings by type/classification.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('building_type_classification', $type);
    }

    /**
     * Filter buildings by TLI range (alias for withTliRange).
     */
    public function scopeByTliRange($query, int $minTli = null, int $maxTli = null)
    {
        return $this->scopeWithTliRange($query, $minTli, $maxTli);
    }

    /**
     * Filter buildings that are anomalies.
     */
    public function scopeAnomaliesOnly($query)
    {
        return $query->where('is_anomaly', true);
    }

    /**
     * Filter buildings that are not anomalies.
     */
    public function scopeNormalOnly($query)
    {
        return $query->where('is_anomaly', false);
    }

    /**
     * Filter buildings by heat loss difference range.
     */
    public function scopeWithHeatlossRange($query, float $minDiff = null, float $maxDiff = null)
    {
        if ($minDiff !== null) {
            $query->where('heatloss_difference', '>=', $minDiff);
        }

        if ($maxDiff !== null) {
            $query->where('heatloss_difference', '<=', $maxDiff);
        }

        return $query;
    }

    /**
     * Filter buildings by confidence threshold.
     */
    public function scopeWithMinConfidence($query, float $confidence)
    {
        return $query->where('confidence', '>=', $confidence);
    }

    /**
     * Filter high-confidence anomalies.
     */
    public function scopeHighConfidenceAnomalies($query)
    {
        return $query->where('is_anomaly', true)
                    ->where('confidence', '>=', 0.8);
    }
}
