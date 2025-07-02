<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'role' => $this->role,
            'contact_info' => $this->contact_info,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            
            // Relationships
            'entitlements' => $this->whenLoaded('entitlements', function () {
                return EntitlementResource::collection($this->entitlements);
            }),
            
            'entitlements_count' => $this->whenCounted('entitlements'),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}