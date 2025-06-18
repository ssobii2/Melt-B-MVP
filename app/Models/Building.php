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
        'thermal_loss_index_tli',
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
        'thermal_loss_index_tli' => 'integer',
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
     * Get the TLI color code based on the thermal loss index.
     */
    public function getTliColorAttribute(): string
    {
        $tli = $this->thermal_loss_index_tli;

        if ($tli >= 80) return '#ff0000'; // Red - High loss
        if ($tli >= 60) return '#ff8000'; // Orange
        if ($tli >= 40) return '#ffff00'; // Yellow
        if ($tli >= 20) return '#80ff00'; // Light green
        return '#00ff00'; // Green - Low loss
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
    public function scopeApplyEntitlementFilters($query, array $entitlementFilters)
    {
        // If user has DS-ALL access to any dataset, they can see buildings from those datasets
        if (!empty($entitlementFilters['ds_all_datasets'])) {
            $query->orWhereIn('dataset_id', $entitlementFilters['ds_all_datasets']);
        }

        // Apply DS-AOI spatial filters
        if (!empty($entitlementFilters['ds_aoi_polygons'])) {
            $query->orWhere(function ($subQuery) use ($entitlementFilters) {
                foreach ($entitlementFilters['ds_aoi_polygons'] as $aoiFilter) {
                    $subQuery->orWhere(function ($aoiQuery) use ($aoiFilter) {
                        $aoiQuery->where('dataset_id', $aoiFilter['dataset_id'])
                            ->whereRaw('ST_Intersects(geometry, ST_GeomFromText(?, 4326))', [
                                $aoiFilter['geometry']->toWkt()
                            ]);
                    });
                }
            });
        }

        // Apply DS-BLD building-specific filters
        if (!empty($entitlementFilters['ds_building_gids'])) {
            $query->orWhereIn('gid', $entitlementFilters['ds_building_gids']);
        }

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
}
