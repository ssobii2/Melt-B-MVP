<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitlementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'dataset_id' => $this->dataset_id,
            'aoi_geom' => $this->when(
                $request->has('include_geometry'),
                function () {
                    return $this->aoi_geom?->toArray();
                }
            ),
            'building_gids' => $this->building_gids,
            'tile_layers' => $this->tile_layers,
            'download_formats' => $this->download_formats,
            'expires_at' => $this->expires_at?->toISOString(),
            
            // Computed attributes
            'is_expired' => $this->isExpired(),
            'is_active' => $this->isActive(),
            
            // Relationships
            'dataset' => $this->whenLoaded('dataset', function () {
                return new DatasetResource($this->dataset);
            }),
            
            'users' => $this->whenLoaded('users', function () {
                return UserResource::collection($this->users);
            }),
            
            'users_count' => $this->whenCounted('users'),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}