<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisJob;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnalysisJobController extends Controller
{
    /**
     * Display a listing of analysis jobs.
     */
    public function index(Request $request)
    {
        $query = AnalysisJob::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Order by latest first
        $jobs = $query->orderBy('created_at', 'desc')
                     ->paginate($request->get('per_page', 15));

        return response()->json([
            'analysis_jobs' => $jobs->items(),
            'pagination' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ]
        ]);
    }

    /**
     * Store a new analysis job.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'input_source_links' => 'required|array|min:1',
            'input_source_links.*' => 'required|string|url',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $job = AnalysisJob::create([
            'status' => 'pending',
            'input_source_links' => $request->input_source_links,
            'metadata' => $request->metadata ?? [],
        ]);

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_analysis_job_created',
            targetType: 'analysis_job',
            targetId: $job->id,
            newValues: [
                'status' => $job->status,
                'input_source_links' => $job->input_source_links,
                'metadata' => $job->metadata
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        // TODO: In the future, this is where we would call the science team's API
        // For now, we just create the database record
        
        return response()->json([
            'message' => 'Analysis job created successfully',
            'analysis_job' => $job
        ], 201);
    }

    /**
     * Display the specified analysis job.
     */
    public function show(string $id)
    {
        $job = AnalysisJob::findOrFail($id);
        
        return response()->json([
            'analysis_job' => $job
        ]);
    }

    /**
     * Update the specified analysis job.
     */
    public function update(Request $request, string $id)
    {
        $job = AnalysisJob::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,running,completed,failed',
            'output_csv_url' => 'sometimes|nullable|url',
            'external_job_id' => 'sometimes|nullable|string',
            'error_message' => 'sometimes|nullable|string',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldValues = $job->only(['status', 'output_csv_url', 'external_job_id', 'error_message', 'metadata']);
        
        $job->update($request->only([
            'status', 'output_csv_url', 'external_job_id', 
            'error_message', 'metadata'
        ]));

        // Update timestamps based on status
        if ($request->status === 'running' && !$job->started_at) {
            $job->started_at = now();
            $job->save();
        } elseif ($request->status === 'completed' && !$job->completed_at) {
            $job->completed_at = now();
            $job->save();
        }

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_analysis_job_updated',
            targetType: 'analysis_job',
            targetId: $job->id,
            oldValues: $oldValues,
            newValues: $job->only(['status', 'output_csv_url', 'external_job_id', 'error_message', 'metadata']),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Analysis job updated successfully',
            'analysis_job' => $job
        ]);
    }

    /**
     * Remove the specified analysis job.
     */
    public function destroy(Request $request, string $id)
    {
        $job = AnalysisJob::findOrFail($id);
        
        // Only allow deletion of completed or failed jobs
        if ($job->isRunning()) {
            return response()->json([
                'message' => 'Cannot delete running or pending jobs'
            ], 422);
        }

        $jobData = $job->only(['status', 'input_source_links', 'output_csv_url', 'external_job_id']);
        
        $job->delete();

        // Log the admin action
        AuditLog::createEntry(
            userId: $request->user()->id,
            action: 'admin_analysis_job_deleted',
            targetType: 'analysis_job',
            targetId: $id,
            oldValues: $jobData,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Analysis job deleted successfully'
        ]);
    }

    /**
     * Get statistics about analysis jobs.
     */
    public function stats()
    {
        $stats = [
            'total_jobs' => AnalysisJob::count(),
            'pending_jobs' => AnalysisJob::where('status', 'pending')->count(),
            'running_jobs' => AnalysisJob::where('status', 'running')->count(),
            'completed_jobs' => AnalysisJob::where('status', 'completed')->count(),
            'failed_jobs' => AnalysisJob::where('status', 'failed')->count(),
            'recent_jobs' => AnalysisJob::latest()->take(5)->get(),
        ];

        return response()->json($stats);
    }
}
