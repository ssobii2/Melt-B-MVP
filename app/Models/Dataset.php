<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'description',
        'data_type',
        'storage_location',
        'version',
    ];

    /**
     * Get the entitlements for the dataset.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    /**
     * Get the buildings for the dataset.
     */
    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }
}
