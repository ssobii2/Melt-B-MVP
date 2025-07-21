@extends('admin.layouts.app')

@section('title', 'Buildings View - MELT-B Admin')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>
        <i class="fas fa-building text-info"></i>
        Buildings
        <small class="text-muted">View building thermal data (Read-only)</small>
    </h1>
    <button id="refreshBuildings" class="btn btn-outline-primary">
        <i class="fas fa-sync"></i> Refresh
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
                    All Buildings
                </h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 300px;">
                        <input type="text" id="searchBuildings" class="form-control float-right" placeholder="Search by address or GID...">
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
                        <select id="datasetFilter" class="form-control">
                            <option value="">All Datasets</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="anomalyFilter" class="form-control">
                            <option value="">All Buildings</option>
                            <option value="true">Anomalies Only</option>
                            <option value="false">Normal Buildings</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="perPage" class="form-control">
                            <option value="15">15 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-right">
                        <span id="buildingCount" class="badge badge-info">Loading...</span>
                    </div>
                </div>

                <!-- Buildings Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="buildingsTable">
                        <thead>
                            <tr>
                                <th>GID</th>
                                <th>Address</th>
                                <th>Classification</th>
                                <th>CO2 Savings</th>
                                <th>Heat Loss</th>
                                <th>Anomaly Status</th>
                                <th>Dataset</th>
                                <th>Analyzed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="buildingsTableBody">
                            <tr>
                                <td colspan="8" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading buildings...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="buildingsPagination" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Building Details Modal (Read-only) -->
<div class="modal fade" id="buildingDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-building"></i> Building Details
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>GID:</strong></td>
                                <td id="detailGid">-</td>
                            </tr>
                            <tr>
                                <td><strong>Address:</strong></td>
                                <td id="detailAddress">-</td>
                            </tr>
                            <tr>
                                <td><strong>Average Heat Loss:</strong></td>
                                <td id="detailHeatLoss">-</td>
                            </tr>
                            <tr>
                                <td><strong>Reference Heat Loss:</strong></td>
                                <td id="detailReferenceHeatLoss">-</td>
                            </tr>
                            <tr>
                                <td><strong>Heat Loss Difference:</strong></td>
                                <td id="detailHeatLossDifference">-</td>
                            </tr>
                            <tr>
                                <td><strong>Abs. Heat Loss Difference:</strong></td>
                                <td id="detailAbsHeatLossDifference">-</td>
                            </tr>
                            <tr>
                                <td><strong>Threshold:</strong></td>
                                <td id="detailThreshold">-</td>
                            </tr>
                            <tr>
                                <td><strong>Anomaly Status:</strong></td>
                                <td id="detailAnomalyStatus">-</td>
                            </tr>
                            <tr>
                                <td><strong>Confidence:</strong></td>
                                <td id="detailConfidence">-</td>
                            </tr>
                            <tr>
                                <td><strong>Classification:</strong></td>
                                <td id="detailClassification">-</td>
                            </tr>
                            <tr>
                                <td><strong>CO2 Savings:</strong></td>
                                <td id="detailCo2">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Dataset:</strong></td>
                                <td id="detailDataset">-</td>
                            </tr>
                            <tr>
                                <td><strong>Cadastral Ref:</strong></td>
                                <td id="detailCadastral">-</td>
                            </tr>
                            <tr>
                                <td><strong>Owner Details:</strong></td>
                                <td id="detailOwner">-</td>
                            </tr>
                            <tr>
                                <td><strong>Last Analyzed:</strong></td>
                                <td id="detailAnalyzed">-</td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td id="detailCreated">-</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
@vite(['resources/js/admin/buildings.js'])
@endpush