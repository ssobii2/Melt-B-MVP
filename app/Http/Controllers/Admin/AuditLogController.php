<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Dedoc\Scramble\Attributes\Tag;
use Dedoc\Scramble\Attributes\Response;
use Dedoc\Scramble\Attributes\OperationId;
use Dedoc\Scramble\Attributes\Summary;
use Dedoc\Scramble\Attributes\Description;
use Dedoc\Scramble\Attributes\Parameters;

#[Tag('Admin - Audit Logs')]
class AuditLogController extends Controller
{
    /**
     * Get a paginated list of audit log entries.
     */
    #[OperationId('admin.auditLogs.index')]
    #[Summary('List audit log entries')]
    #[Description('Get a paginated list of audit log entries with optional filtering by action, user, target type, and date range.')]
    #[Parameters([
        'per_page' => 'integer|optional|Number of items per page (default: 20)',
        'action' => 'string|optional|Filter by action type',
        'user_id' => 'integer|optional|Filter by user ID',
        'target_type' => 'string|optional|Filter by target type',
        'date_from' => 'string|optional|Filter from date (YYYY-MM-DD)',
        'date_to' => 'string|optional|Filter to date (YYYY-MM-DD)'
    ])]
    #[Response(200, 'Paginated audit log entries', [
        'data' => [
            [
                'id' => 1,
                'user_id' => 1,
                'action' => 'login',
                'target_type' => 'User',
                'target_id' => 1,
                'details' => ['ip' => '192.168.1.1'],
                'created_at' => '2024-01-01T00:00:00Z',
                'user' => [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john@example.com'
                ]
            ]
        ],
        'current_page' => 1,
        'per_page' => 20,
        'total' => 100
    ])]
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $action = $request->input('action');
        $userId = $request->input('user_id');
        $targetType = $request->input('target_type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = AuditLog::query()->with('user:id,name,email');

        // Apply action filter
        if ($action) {
            $query->where('action', $action);
        }

        // Apply user filter
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Apply target type filter
        if ($targetType) {
            $query->where('target_type', $targetType);
        }

        // Apply date range filter
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $auditLogs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => AuditLogResource::collection($auditLogs->items()),
            'current_page' => $auditLogs->currentPage(),
            'per_page' => $auditLogs->perPage(),
            'total' => $auditLogs->total()
        ]);
    }

    /**
     * Get details of a specific audit log entry.
     */
    #[OperationId('admin.auditLogs.show')]
    #[Summary('Get audit log entry details')]
    #[Description('Get detailed information about a specific audit log entry.')]
    #[Response(200, 'Audit log entry details', [
        'audit_log' => [
            'id' => 1,
            'user_id' => 1,
            'action' => 'dataset_created',
            'target_type' => 'Dataset',
            'target_id' => 5,
            'details' => [
                'dataset_name' => 'New Thermal Dataset',
                'data_type' => 'thermal_raster'
            ],
            'created_at' => '2024-01-01T00:00:00Z',
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ]
        ]
    ])]
    #[Response(404, 'Audit log entry not found', ['message' => 'Audit log entry not found'])]
    public function show(string $id): JsonResponse
    {
        $auditLog = AuditLog::with('user:id,name,email')->find($id);

        if (!$auditLog) {
            return response()->json(['message' => 'Audit log entry not found'], 404);
        }

        return response()->json([
            'audit_log' => new AuditLogResource($auditLog)
        ]);
    }

    /**
     * Get audit log statistics.
     */
    #[OperationId('admin.auditLogs.stats')]
    #[Summary('Get audit log statistics')]
    #[Description('Get comprehensive statistics about audit log entries including counts, top actions, and recent activities.')]
    #[Response(200, 'Audit log statistics', [
        'total_entries' => 1250,
        'recent_entries' => 45,
        'by_action' => [
            'login' => 320,
            'dataset_created' => 85,
            'user_created' => 42,
            'entitlement_assigned' => 38
        ],
        'by_user' => [
            [
                'user_name' => 'John Doe',
                'count' => 125
            ],
            [
                'user_name' => 'Jane Smith',
                'count' => 98
            ]
        ],
        'recent_activities' => [
            [
                'id' => 1250,
                'action' => 'login',
                'created_at' => '2024-01-01T12:00:00Z',
                'user' => [
                    'id' => 1,
                    'name' => 'John Doe'
                ]
            ]
        ]
    ])]
    public function stats(): JsonResponse
    {
        $stats = [
            'total_entries' => AuditLog::count(),
            'recent_entries' => AuditLog::where('created_at', '>=', now()->subDay())->count(),
            'by_action' => AuditLog::selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'action'),
            'by_user' => AuditLog::with('user:id,name')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'user_name' => $item->user->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                }),
            'recent_activities' => AuditLog::with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
        ];

        return response()->json($stats);
    }

    /**
     * Get unique actions for filter dropdown.
     */
    #[OperationId('admin.auditLogs.actions')]
    #[Summary('Get available actions')]
    #[Description('Get a list of all unique actions for filtering audit logs.')]
    #[Response(200, 'Available actions', [
        'actions' => [
            'login',
            'logout',
            'user_created',
            'user_updated',
            'dataset_created',
            'dataset_updated',
            'entitlement_assigned',
            'entitlement_removed'
        ]
    ])]
    public function actions(): JsonResponse
    {
        $actions = AuditLog::distinct('action')
            ->pluck('action')
            ->sort()
            ->values();

        return response()->json([
            'actions' => $actions
        ]);
    }

    /**
     * Get unique target types for filter dropdown.
     */
    #[OperationId('admin.auditLogs.targetTypes')]
    #[Summary('Get available target types')]
    #[Description('Get a list of all unique target types for filtering audit logs.')]
    #[Response(200, 'Available target types', [
        'target_types' => [
            'User',
            'Dataset',
            'Entitlement',
            'Building',
            'AnalysisJob'
        ]
    ])]
    public function targetTypes(): JsonResponse
    {
        $targetTypes = AuditLog::distinct('target_type')
            ->whereNotNull('target_type')
            ->pluck('target_type')
            ->sort()
            ->values();

        return response()->json([
            'target_types' => $targetTypes
        ]);
    }
}
