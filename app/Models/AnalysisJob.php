<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalysisJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'input_source_links',
        'output_csv_url',
        'external_job_id',
        'metadata',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'input_source_links' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Scope for filtering by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if the job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the job has failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the job is still running
     */
    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'running']);
    }
}
