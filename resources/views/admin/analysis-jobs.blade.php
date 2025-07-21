@extends('admin.layouts.app')

@section('title', 'Analysis Jobs - MELT-B Admin')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>
        <i class="fas fa-tasks text-primary"></i>
        Analysis Jobs
        <small class="text-muted">Monitor and track analysis job progress</small>
    </h1>
</div>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list"></i>
                    All Analysis Jobs
                </h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" id="searchJobs" class="form-control float-right" placeholder="Search jobs...">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-default">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="running">Running</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="perPage" class="form-control">
                            <option value="15">15 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-right">
                        <button id="refreshJobs" class="btn btn-outline-primary">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button id="viewStats" class="btn btn-outline-info" data-toggle="modal" data-target="#statsModal">
                            <i class="fas fa-chart-bar"></i> Statistics
                        </button>
                    </div>
                </div>

                <!-- Jobs Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="jobsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>External Job ID</th>
                                <th>Input Source</th>
                                <th>Output CSV</th>
                                <th>Started</th>
                                <th>Completed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="jobsTableBody">
                            <tr>
                                <td colspan="8" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading analysis jobs...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="jobsPagination" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Job Details Modal -->
<div class="modal fade" id="jobDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-tasks"></i> Analysis Job Details
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Job Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Job ID:</strong></td>
                                <td id="detailJobId">-</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td id="detailStatus">-</td>
                            </tr>
                            <tr>
                                <td><strong>External Job ID:</strong></td>
                                <td id="detailExternalJobId">-</td>
                            </tr>
                            <tr>
                                <td><strong>Started At:</strong></td>
                                <td id="detailStartedAt">-</td>
                            </tr>
                            <tr>
                                <td><strong>Completed At:</strong></td>
                                <td id="detailCompletedAt">-</td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td id="detailCreated">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Files & Links</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Input Source:</strong></td>
                                <td id="detailInputSource">-</td>
                            </tr>
                            <tr>
                                <td><strong>Output CSV:</strong></td>
                                <td id="detailOutputCsv">-</td>
                            </tr>
                        </table>
                        
                        <h6>Metadata</h6>
                        <div id="detailMetadata" class="border p-2 bg-light">
                            <small class="text-muted">No metadata available</small>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3" id="errorSection" style="display: none;">
                    <div class="col-12">
                        <h6 class="text-danger">Error Message</h6>
                        <div id="detailErrorMessage" class="alert alert-danger">
                            <!-- Error message will be displayed here -->
                        </div>
                    </div>
                </div>
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
                    <i class="fas fa-chart-bar"></i> Analysis Jobs Statistics
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
@vite(['resources/js/admin/analysis-jobs.js'])
@endpush