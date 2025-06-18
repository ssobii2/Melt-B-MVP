<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class Entitlement extends Model
{
    use HasSpatial;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'type',
        'dataset_id',
        'aoi_geom',
        'building_gids',
        'download_formats',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'aoi_geom' => Polygon::class,
        'building_gids' => 'array',
        'download_formats' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the dataset that owns the entitlement.
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    /**
     * Get the users that have this entitlement.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_entitlements')
            ->withPivot('created_at');
    }

    /**
     * Check if the entitlement is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the entitlement is active (not expired).
     */
    public function isActive(): bool
    {
        return !$this->isExpired();
    }
}
