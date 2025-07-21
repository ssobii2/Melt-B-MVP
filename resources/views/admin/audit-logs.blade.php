@extends('admin.layouts.app')

@section('title', 'Audit Logs - MELT-B Admin')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>
        <i class="fas fa-history text-primary"></i>
        Audit Logs
        <small class="text-muted">System activity and change tracking</small>
    </h1>
    <button id="viewStats" class="btn btn-outline-info" data-toggle="modal" data-target="#statsModal">
        <i class="fas fa-chart-bar"></i> Statistics
    </button>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    Activity Logs
                </h3>
                <div class="card-tools">
                    <button id="refreshLogs" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-2">
                        <select id="actionFilter" class="form-control">
                            <option value="">All Actions</option>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="targetTypeFilter" class="form-control">
                            <option value="">All Target Types</option>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="dateFromFilter" class="form-control" placeholder="From Date">
                    </div>
                    <div class="col-md-2">
                        <input type="date" id="dateToFilter" class="form-control" placeholder="To Date">
                    </div>
                    <div class="col-md-2">
                        <select id="perPage" class="form-control">
                            <option value="20">20 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button id="clearFilters" class="btn btn-outline-secondary btn-block">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>

                <!-- Audit Logs Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm" id="auditLogsTable">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>IP Address</th>
                                <th>User Agent</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="auditLogsTableBody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading audit logs...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="auditLogsPagination" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Audit Log Details Modal -->
<div class="modal fade" id="auditLogDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-info-circle"></i> Audit Log Details
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="auditLogDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Statistics Modal -->
<div class="modal fade" id="statsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-chart-bar"></i> Audit Log Statistics
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="statsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>
@stop



@push('js')
<script>
    // Set admin token for use in external JS file
    window.adminToken = '{{ session("admin_token") }}';
</script>
@vite(['resources/js/admin/audit-logs.js'])
@endpush