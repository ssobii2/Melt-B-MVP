<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnalysisJobResource;
use App\Models\AnalysisJob;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\RequestBody;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;
use Dedoc\Scramble\Attributes\Parameters;

#[Tag('Admin - Analysis Jobs')]
#[Response(401, 'Unauthorized')]
#[Response(403, 'Forbidden')]
class AnalysisJobController extends Controller
{
    /**
     * Display a listing of analysis jobs.
     */
    #[OperationId('admin.analysis-jobs.index')]
    #[Summary('List analysis jobs')]
    #[Description('Get a paginated list of analysis jobs with optional status filtering.')]
    #[Parameters([
        'status' => 'Filter by job status (pending, running, completed, failed)',
        'per_page' => 'Number of items per page (default: 15)'
    ])]
    #[Response(200, 'Analysis jobs list', [
        'analysis_jobs' => [
            [
                'id' => 1,
                'status' => 'completed',
                'input_source_links' => ['https://example.com/data1.csv'],
                'output_csv_url' => 'https://example.com/output1.csv',
                'external_job_id' => 'ext_job_123',
                'error_message' => null,
                'metadata' => ['key' => 'value'],
                'started_at' => '2024-01-15T10:30:00Z',
                'completed_at' => '2024-01-15T11:00:00Z',
                'created_at' => '2024-01-15T10:00:00Z',
                'updated_at' => '2024-01-15T11:00:00Z'
            ]
        ],
        'pagination' => [
            'current_page' => 1,
            'last_page' => 3,
            'per_page' => 15,
            'total' => 42
        ]
    ])]
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
            'analysis_jobs' => AnalysisJobResource::collection($jobs->items()),
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
    #[OperationId('admin.analysis-jobs.store')]
    #[Summary('Create analysis job')]
    #[Description('Create a new analysis job with input source links and optional metadata.')]
    #[RequestBody([
        'input_source_links' => [
            'type' => 'array',
            'description' => 'Array of URLs to input data sources',
            'items' => ['type' => 'string', 'format' => 'url'],
            'minItems' => 1
        ],
        'metadata' => [
            'type' => 'object',
            'description' => 'Optional metadata for the analysis job'
        ]
    ])]
    #[Response(201, 'Analysis job created', [
        'message' => 'Analysis job created successfully',
        'analysis_job' => [
            'id' => 1,
            'status' => 'pending',
            'input_source_links' => ['https://example.com/data1.csv'],
            'output_csv_url' => null,
            'external_job_id' => null,
            'error_message' => null,
            'metadata' => ['key' => 'value'],
            'started_at' => null,
            'completed_at' => null,
            'created_at' => '2024-01-15T10:00:00Z',
            'updated_at' => '2024-01-15T10:00:00Z'
        ]
    ])]
    #[Response(422, 'Validation failed')]
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
            'analysis_job' => new AnalysisJobResource($job)
        ], 201);
    }

    /**
     * Display the specified analysis job.
     */
    #[OperationId('admin.analysis-jobs.show')]
    #[Summary('Get analysis job details')]
    #[Description('Get detailed information about a specific analysis job.')]
    #[Response(200, 'Analysis job details', [
        'analysis_job' => [
            'id' => 1,
            'status' => 'completed',
            'input_source_links' => ['https://example.com/data1.csv'],
            'output_csv_url' => 'https://example.com/output1.csv',
            'external_job_id' => 'ext_job_123',
            'error_message' => null,
            'metadata' => ['key' => 'value'],
            'started_at' => '2024-01-15T10:30:00Z',
            'completed_at' => '2024-01-15T11:00:00Z',
            'created_at' => '2024-01-15T10:00:00Z',
            'updated_at' => '2024-01-15T11:00:00Z'
        ]
    ])]
    #[Response(404, 'Analysis job not found')]
    public function show(string $id)
    {
        $job = AnalysisJob::findOrFail($id);
        
        return response()->json([
            'analysis_job' => new AnalysisJobResource($job)
        ]);
    }

    /**
     * Update the specified analysis job.
     */
    #[OperationId('admin.analysis-jobs.update')]
    #[Summary('Update analysis job')]
    #[Description('Update an existing analysis job status, output URL, external job ID, error message, or metadata.')]
    #[RequestBody([
        'status' => [
            'type' => 'string',
            'enum' => ['pending', 'running', 'completed', 'failed'],
            'description' => 'Job status'
        ],
        'output_csv_url' => [
            'type' => 'string',
            'format' => 'url',
            'nullable' => true,
            'description' => 'URL to the output CSV file'
        ],
        'external_job_id' => [
            'type' => 'string',
            'nullable' => true,
            'description' => 'External system job identifier'
        ],
        'error_message' => [
            'type' => 'string',
            'nullable' => true,
            'description' => 'Error message if job failed'
        ],
        'metadata' => [
            'type' => 'object',
            'description' => 'Job metadata'
        ]
    ])]
    #[Response(200, 'Analysis job updated', [
        'message' => 'Analysis job updated successfully',
        'analysis_job' => [
            'id' => 1,
            'status' => 'completed',
            'input_source_links' => ['https://example.com/data1.csv'],
            'output_csv_url' => 'https://example.com/output1.csv',
            'external_job_id' => 'ext_job_123',
            'error_message' => null,
            'metadata' => ['key' => 'value'],
            'started_at' => '2024-01-15T10:30:00Z',
            'completed_at' => '2024-01-15T11:00:00Z',
            'created_at' => '2024-01-15T10:00:00Z',
            'updated_at' => '2024-01-15T11:00:00Z'
        ]
    ])]
    #[Response(404, 'Analysis job not found')]
    #[Response(422, 'Validation failed')]
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
            'analysis_job' => new AnalysisJobResource($job)
        ]);
    }

    /**
     * Remove the specified analysis job.
     */
    #[OperationId('admin.analysis-jobs.destroy')]
    #[Summary('Delete analysis job')]
    #[Description('Delete an analysis job. Only completed or failed jobs can be deleted.')]
    #[Response(200, 'Analysis job deleted', [
        'message' => 'Analysis job deleted successfully'
    ])]
    #[Response(404, 'Analysis job not found')]
    #[Response(422, 'Cannot delete running or pending jobs', [
        'message' => 'Cannot delete running or pending jobs'
    ])]
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
    #[OperationId('admin.analysis-jobs.stats')]
    #[Summary('Get analysis job statistics')]
    #[Description('Get comprehensive statistics about analysis jobs including counts by status and recent jobs.')]
    #[Response(200, 'Analysis job statistics', [
        'total_jobs' => 45,
        'pending_jobs' => 5,
        'running_jobs' => 2,
        'completed_jobs' => 35,
        'failed_jobs' => 3,
        'recent_jobs' => [
            [
                'id' => 1,
                'status' => 'completed',
                'input_source_links' => ['https://example.com/data1.csv'],
                'output_csv_url' => 'https://example.com/output1.csv',
                'external_job_id' => 'ext_job_123',
                'error_message' => null,
                'metadata' => ['key' => 'value'],
                'started_at' => '2024-01-15T10:30:00Z',
                'completed_at' => '2024-01-15T11:00:00Z',
                'created_at' => '2024-01-15T10:00:00Z',
                'updated_at' => '2024-01-15T11:00:00Z'
            ]
        ]
    ])]
    public function stats()
    {
        $stats = [
            'total_jobs' => AnalysisJob::count(),
            'pending_jobs' => AnalysisJob::where('status', 'pending')->count(),
            'running_jobs' => AnalysisJob::where('status', 'running')->count(),
            'completed_jobs' => AnalysisJob::where('status', 'completed')->count(),
            'failed_jobs' => AnalysisJob::where('status', 'failed')->count(),
            'recent_jobs' => AnalysisJobResource::collection(AnalysisJob::latest()->take(5)->get()),
        ];

        return response()->json($stats);
    }
}
