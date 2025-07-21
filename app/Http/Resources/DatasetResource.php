<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatasetResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'data_type' => $this->data_type,
            'storage_location' => $this->storage_location,
            'version' => $this->version,
            'metadata' => $this->metadata,
            
            // Relationships
            'entitlements' => $this->whenLoaded('entitlements', function () {
                return EntitlementResource::collection($this->entitlements);
            }),
            
            'buildings' => $this->whenLoaded('buildings', function () {
                return BuildingResource::collection($this->buildings);
            }),
            
            'entitlements_count' => $this->whenCounted('entitlements'),
            'buildings_count' => $this->whenCounted('buildings'),
            'users_with_access' => $this->whenLoaded('entitlements', function () {
                return $this->entitlements->pluck('users')->flatten()->unique('id')->count();
            }),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}