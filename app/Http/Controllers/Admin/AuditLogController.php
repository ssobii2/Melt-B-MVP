<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    /**
     * Get a paginated list of audit log entries.
     */
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

        return response()->json($auditLogs);
    }

    /**
     * Get details of a specific audit log entry.
     */
    public function show(int $id): JsonResponse
    {
        $auditLog = AuditLog::with('user:id,name,email')->find($id);

        if (!$auditLog) {
            return response()->json(['message' => 'Audit log entry not found'], 404);
        }

        return response()->json([
            'audit_log' => $auditLog
        ]);
    }

    /**
     * Get audit log statistics.
     */
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
