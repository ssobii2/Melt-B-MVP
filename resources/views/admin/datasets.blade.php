@extends('admin.layouts.app')

@section('title', 'Dataset Management - MELT-B Admin')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>
        <i class="fas fa-database text-primary"></i>
        Dataset Management
        <small class="text-muted">Manage thermal data datasets</small>
    </h1>
    <button class="btn btn-primary" data-toggle="modal" data-target="#createDatasetModal">
        <i class="fas fa-plus"></i> Add New Dataset
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
                    All Datasets
                </h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" id="searchDatasets" class="form-control float-right" placeholder="Search datasets...">
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
                        <select id="dataTypeFilter" class="form-control">
                            <option value="">All Data Types</option>
                            <!-- Options loaded dynamically -->
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
                        <button id="refreshDatasets" class="btn btn-outline-primary">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button id="viewStats" class="btn btn-outline-info" data-toggle="modal" data-target="#statsModal">
                            <i class="fas fa-chart-bar"></i> Statistics
                        </button>
                    </div>
                </div>

                <!-- Datasets Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="datasetsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Data Type</th>
                                <th>Description</th>
                                <th>Entitlements</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="datasetsTableBody">
                            <tr>
                                <td colspan="6" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading datasets...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="datasetsPagination" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Create Dataset Modal -->
<div class="modal fade" id="createDatasetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-plus"></i> Create New Dataset
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createDatasetForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createName">Dataset Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="createName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createDataType">Data Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="createDataType" name="data_type" required>
                    <option value="">Select Data Type</option>
                    <!-- Options loaded dynamically -->
                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="createDescription">Description</label>
                        <textarea class="form-control" id="createDescription" name="description" rows="3" placeholder="Describe this dataset..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createStorageLocation">Storage Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="createStorageLocation" name="storage_location" placeholder="/data/thermal-raster/debrecen" required>
                                <small class="form-text text-muted">Path or prefix in object storage</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createVersion">Version</label>
                                <input type="text" class="form-control" id="createVersion" name="version" placeholder="v2024.1">
                                <small class="form-text text-muted">Version identifier for the dataset</small>
                            </div>
                        </div>
                    </div>

                    <!-- Metadata Fields -->
                    <h6 class="text-muted mb-3">Metadata (Optional)</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createSource">Source</label>
                                <input type="text" class="form-control" id="createSource" name="source" placeholder="Data Science Team">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createFormat">Format</label>
                                <input type="text" class="form-control" id="createFormat" name="format" placeholder="GeoTIFF, CSV">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="createSizeMb">Size (MB)</label>
                                <input type="number" class="form-control" id="createSizeMb" name="size_mb" placeholder="1024" step="0.1" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="createSpatialResolution">Spatial Resolution</label>
                                <input type="text" class="form-control" id="createSpatialResolution" name="spatial_resolution" placeholder="1m x 1m">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="createTemporalCoverage">Temporal Coverage</label>
                                <input type="text" class="form-control" id="createTemporalCoverage" name="temporal_coverage" placeholder="2024-Q1">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Dataset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Dataset Modal -->
<div class="modal fade" id="editDatasetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-edit"></i> Edit Dataset
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editDatasetForm">
                <input type="hidden" id="editDatasetId" name="id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editName">Dataset Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editDataType">Data Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="editDataType" name="data_type" required>
                    <!-- Options loaded dynamically -->
                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editDescription">Description</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editStorageLocation">Storage Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editStorageLocation" name="storage_location" required>
                                <small class="form-text text-muted">Path or prefix in object storage</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editVersion">Version</label>
                                <input type="text" class="form-control" id="editVersion" name="version">
                                <small class="form-text text-muted">Version identifier for the dataset</small>
                            </div>
                        </div>
                    </div>

                    <!-- Metadata Fields -->
                    <h6 class="text-muted mb-3">Metadata (Optional)</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editSource">Source</label>
                                <input type="text" class="form-control" id="editSource" name="source">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editFormat">Format</label>
                                <input type="text" class="form-control" id="editFormat" name="format">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editSizeMb">Size (MB)</label>
                                <input type="number" class="form-control" id="editSizeMb" name="size_mb" step="0.1" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editSpatialResolution">Spatial Resolution</label>
                                <input type="text" class="form-control" id="editSpatialResolution" name="spatial_resolution">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="editTemporalCoverage">Temporal Coverage</label>
                                <input type="text" class="form-control" id="editTemporalCoverage" name="temporal_coverage">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Dataset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Dataset Details Modal -->
<div class="modal fade" id="datasetDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-database"></i> Dataset Details
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="datasetDetailsContent">
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
                    <i class="fas fa-chart-bar"></i> Dataset Statistics
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
@vite(['resources/js/admin/datasets.js'])
@endpush