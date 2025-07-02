<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisJob;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\RequestBody;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;

#[Tag('Webhooks')]
class WebhookController extends Controller
{
    /**
     * Handle analysis job completion webhook from external analysis service.
     */
    #[OperationId('webhookAnalysisComplete')]
    #[Summary('Analysis completion webhook')]
    #[Description('Webhook endpoint for external analysis services to notify completion of analysis jobs.')]
    #[RequestBody([
        'external_job_id' => 'string|required|External job identifier',
        'status' => 'string|required|Job status (completed, failed)',
        'output_csv_url' => 'string|required_if:status,completed|URL to output CSV file',
        'error_message' => 'string|optional|Error message if job failed',
        'metadata' => 'object|optional|Additional job metadata'
    ])]
    #[Response(200, 'Webhook processed successfully', [
        'message' => 'Webhook processed successfully',
        'analysis_job_id' => 123,
        'status' => 'completed'
    ])]
    #[Response(404, 'Analysis job not found', [
        'message' => 'Analysis job not found'
    ])]
    #[Response(422, 'Invalid webhook payload', [
        'message' => 'Invalid webhook payload',
        'errors' => [
            'external_job_id' => ['The external job id field is required.']
        ]
    ])]
    #[Response(500, 'Internal server error', [
        'message' => 'Internal server error'
    ])]
    public function analysisComplete(Request $request)
    {
        // Log the incoming webhook for debugging
        Log::info('Analysis completion webhook received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        // Validate the webhook payload
        $validator = Validator::make($request->all(), [
            'external_job_id' => 'required|string',
            'status' => 'required|in:completed,failed',
            'output_csv_url' => 'required_if:status,completed|url',
            'error_message' => 'sometimes|string',
            'metadata' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            Log::error('Invalid webhook payload', [
                'errors' => $validator->errors(),
                'payload' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'Invalid webhook payload',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find the analysis job by external job ID
            $analysisJob = AnalysisJob::where('external_job_id', $request->external_job_id)->first();
            
            if (!$analysisJob) {
                Log::warning('Analysis job not found for external ID', [
                    'external_job_id' => $request->external_job_id
                ]);
                
                return response()->json([
                    'message' => 'Analysis job not found'
                ], 404);
            }

            // Update the analysis job status
            $updateData = [
                'status' => $request->status,
                'completed_at' => now(),
            ];

            if ($request->status === 'completed') {
                $updateData['output_csv_url'] = $request->output_csv_url;
                
                // Merge additional metadata if provided
                if ($request->has('metadata')) {
                    $updateData['metadata'] = array_merge(
                        $analysisJob->metadata ?? [],
                        $request->metadata
                    );
                }
                
                Log::info('Analysis job completed', [
                    'job_id' => $analysisJob->id,
                    'external_job_id' => $request->external_job_id,
                    'output_csv_url' => $request->output_csv_url
                ]);
                
            } else {
                // Handle failed status
                $updateData['error_message'] = $request->error_message ?? 'Analysis failed';
                
                Log::error('Analysis job failed', [
                    'job_id' => $analysisJob->id,
                    'external_job_id' => $request->external_job_id,
                    'error_message' => $updateData['error_message']
                ]);
            }

            $analysisJob->update($updateData);

            // Log the webhook processing for audit purposes
            AuditLog::createEntry(
                userId: null, // Webhook calls don't have a user context
                action: 'webhook_analysis_job_' . $request->status,
                targetType: 'analysis_job',
                targetId: $analysisJob->id,
                newValues: [
                    'external_job_id' => $request->external_job_id,
                    'status' => $request->status,
                    'output_csv_url' => $request->output_csv_url ?? null,
                    'error_message' => $request->error_message ?? null
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            // If completed, trigger the CSV import process
            if ($request->status === 'completed') {
                $this->triggerCsvImport($analysisJob);
            }

            return response()->json([
                'message' => 'Webhook processed successfully',
                'analysis_job_id' => $analysisJob->id,
                'status' => $request->status
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing analysis completion webhook', [
                'external_job_id' => $request->external_job_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Trigger the CSV import process for completed analysis job.
     */
    private function triggerCsvImport(AnalysisJob $analysisJob): void
    {
        try {
            Log::info('Triggering CSV import for analysis job', [
                'job_id' => $analysisJob->id,
                'csv_url' => $analysisJob->output_csv_url
            ]);

            // Run the import command asynchronously
            Artisan::call('import:buildings-from-csv', [
                'job_id' => $analysisJob->id,
                '--file' => $analysisJob->output_csv_url,
                '--dataset_id' => $analysisJob->metadata['dataset_id'] ?? 1,
            ]);

            Log::info('CSV import command executed', [
                'job_id' => $analysisJob->id,
                'output' => Artisan::output()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to trigger CSV import', [
                'job_id' => $analysisJob->id,
                'error' => $e->getMessage()
            ]);

            // Update job status to indicate import failure
            $analysisJob->update([
                'status' => 'failed',
                'error_message' => 'CSV import failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Health check endpoint for webhook service.
     */
    #[OperationId('webhookHealthCheck')]
    #[Summary('Webhook health check')]
    #[Description('Health check endpoint to verify webhook service availability.')]
    #[Response(200, 'Service is healthy', [
        'status' => 'ok',
        'timestamp' => '2024-01-01T12:00:00.000000Z',
        'service' => 'webhook-handler'
    ])]
    public function healthCheck()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'service' => 'webhook-handler'
        ]);
    }

    /**
     * Test endpoint for webhook development/debugging.
     */
    #[OperationId('webhookTest')]
    #[Summary('Webhook test endpoint')]
    #[Description('Test endpoint for webhook development and debugging purposes.')]
    #[Response(200, 'Test webhook received', [
        'message' => 'Test webhook received',
        'received_data' => [],
        'timestamp' => '2024-01-01T12:00:00.000000Z'
    ])]
    public function test(Request $request)
    {
        Log::info('Webhook test endpoint called', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'message' => 'Test webhook received',
            'received_data' => $request->all(),
            'timestamp' => now()->toISOString()
        ]);
    }
}
