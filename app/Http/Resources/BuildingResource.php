<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gid' => $this->gid,
            
            // Legacy TLI field (for backward compatibility)
            'thermal_loss_index_tli' => $this->thermal_loss_index_tli,
            
            // New anomaly detection fields
            'average_heatloss' => $this->average_heatloss,
            'reference_heatloss' => $this->reference_heatloss,
            'heatloss_difference' => $this->heatloss_difference,
            'abs_heatloss_difference' => $this->abs_heatloss_difference,
            'threshold' => $this->threshold,
            'is_anomaly' => $this->is_anomaly,
            'confidence' => $this->confidence,
            
            // Common building attributes
            'building_type_classification' => $this->building_type_classification,
            'co2_savings_estimate' => $this->co2_savings_estimate,
            'address' => $this->address,
            'owner_operator_details' => $this->owner_operator_details,
            'cadastral_reference' => $this->cadastral_reference,
            'dataset_id' => $this->dataset_id,
            'last_analyzed_at' => $this->last_analyzed_at?->toISOString(),
            'before_renovation_tli' => $this->before_renovation_tli,
            'after_renovation_tli' => $this->after_renovation_tli,

            // Calculated attributes
            'tli_color' => $this->tli_color, // Now supports both TLI and anomaly coloring
            'anomaly_color' => $this->anomaly_color,
            'anomaly_severity' => $this->anomaly_severity,
            'improvement_potential' => $this->improvement_potential,
            'is_high_confidence_anomaly' => $this->isHighConfidenceAnomaly(),

            // Dataset information when loaded
            'dataset' => $this->whenLoaded('dataset', function () {
                return [
                    'id' => $this->dataset->id,
                    'name' => $this->dataset->name,
                    'data_type' => $this->dataset->data_type,
                ];
            }),

            // Geometry only for specific endpoints (optional)
            'geometry' => $this->when(
                $request->has('include_geometry'),
                function () {
                    return $this->geometry?->toArray();
                }
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
