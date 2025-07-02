<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisJobResource extends JsonResource
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
            'status' => $this->status,
            'input_source_links' => $this->input_source_links,
            'output_csv_url' => $this->output_csv_url,
            'external_job_id' => $this->external_job_id,
            'metadata' => $this->metadata,
            'error_message' => $this->error_message,
            
            // Computed attributes
            'is_completed' => $this->isCompleted(),
            'has_failed' => $this->hasFailed(),
            'is_running' => $this->isRunning(),
            
            // Timestamps
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}