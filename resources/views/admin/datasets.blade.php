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
    // Global timezone handling functions
    window.formatDateTime = function(dateString, options = {}) {
        if (!dateString) return 'Never';

        const date = new Date(dateString);
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZoneName: 'short'
        };

        return date.toLocaleString(undefined, {
            ...defaultOptions,
            ...options
        });
    };

    window.formatDate = function(dateString, options = {}) {
        if (!dateString) return 'Never';

        const date = new Date(dateString);
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };

        return date.toLocaleDateString(undefined, {
            ...defaultOptions,
            ...options
        });
    };

    $(document).ready(function() {
        let currentPage = 1;
        let perPage = 15;
        let searchTerm = '';
        let dataTypeFilter = '';

        // Load datasets and data types on page load
        loadDatasets();
        loadDataTypes();

        // Search functionality
        let searchTimeout;
        $('#searchDatasets').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchTerm = $('#searchDatasets').val();
                currentPage = 1;
                loadDatasets();
            }, 500);
        });

        // Filter functionality
        $('#dataTypeFilter, #perPage').on('change', function() {
            dataTypeFilter = $('#dataTypeFilter').val();
            perPage = $('#perPage').val();
            currentPage = 1;
            loadDatasets();
        });

        // Refresh button
        $('#refreshDatasets').on('click', function() {
            loadDatasets();
        });

        // View stats button
        $('#viewStats').on('click', function() {
            loadStats();
        });

        // Load data types function
        function loadDataTypes() {
            $.ajax({
                url: '/api/admin/datasets/data-types',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    populateDataTypeDropdowns(response.data_types);
                },
                error: function(xhr) {
                    console.error('Error loading data types:', xhr);
                    // Fallback to default options if API fails
                    const fallbackTypes = {
                        'thermal_raster': 'Thermal Raster',
                        'building_data': 'Building Data',
                        'thermal_analysis': 'Thermal Analysis',
                        'heat_map': 'Heat Map',
                        'building_anomalies': 'Building Anomalies'
                    };
                    populateDataTypeDropdowns(fallbackTypes);
                }
            });
        }

        // Populate data type dropdowns
        function populateDataTypeDropdowns(dataTypes) {
            const filterSelect = $('#dataTypeFilter');
            const createSelect = $('#createDataType');
            const editSelect = $('#editDataType');

            // Clear existing options (except "All Data Types" for filter)
            filterSelect.find('option:not(:first)').remove();
            createSelect.find('option:not(:first)').remove();
            editSelect.empty();

            // Populate dropdowns
            Object.keys(dataTypes).forEach(function(key) {
                const option = `<option value="${key}">${dataTypes[key]}</option>`;
                filterSelect.append(option);
                createSelect.append(option);
                editSelect.append(option);
            });
        }

        // Load datasets function
        function loadDatasets() {
            const params = new URLSearchParams({
                page: currentPage,
                per_page: perPage,
                search: searchTerm,
                data_type: dataTypeFilter
            });

            $.ajax({
                url: '/api/admin/datasets?' + params.toString(),
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    renderDatasetsTable(response.data);
                    renderPagination(response);
                },
                error: function(xhr) {
                    console.error('Error loading datasets:', xhr);
                    $('#datasetsTableBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading datasets</td></tr>');
                }
            });
        }

        // Load statistics
        function loadStats() {
            $.ajax({
                url: '/api/admin/datasets/stats',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    renderStats(response);
                },
                error: function(xhr) {
                    $('#statsContent').html('<p class="text-danger">Error loading statistics</p>');
                }
            });
        }

        // Render datasets table
        function renderDatasetsTable(datasets) {
            let html = '';
            datasets.forEach(function(dataset) {
                const entitlementsCount = dataset.entitlements_count || 0;
                const dataTypeColor = getDataTypeColor(dataset.data_type);
                const description = dataset.description || '';
                const truncatedDescription = description.length > 50 ? description.substring(0, 50) + '...' : description;

                html += `
                <tr>
                    <td><strong>${dataset.name}</strong></td>
                    <td><span class="badge badge-${dataTypeColor}">${formatDataType(dataset.data_type)}</span></td>
                    <td class="text-truncate" style="max-width: 200px;" title="${description}">${truncatedDescription}</td>
                    <td><span class="badge badge-info">${entitlementsCount}</span></td>
                    <td><small>${formatDate(dataset.created_at)}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="viewDataset(${dataset.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="editDataset(${dataset.id})" title="Edit Dataset">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger" onclick="deleteDataset(${dataset.id}, '${dataset.name}')" title="Delete Dataset">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            });
            $('#datasetsTableBody').html(html);
        }

        // Render statistics
        function renderStats(stats) {
            let html = `
            <div class="row">
                <div class="col-md-6">
                    <h5>Overview</h5>
                    <p><strong>Total Datasets:</strong> ${stats.total_datasets}</p>
                    <p><strong>With Entitlements:</strong> ${stats.datasets_with_entitlements}</p>
                    <p><strong>Without Entitlements:</strong> ${stats.datasets_without_entitlements}</p>
                    
                    <h5>By Data Type</h5>
                    <ul class="list-group">
        `;

            Object.entries(stats.by_data_type).forEach(([type, count]) => {
                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                ${formatDataType(type)}
                <span class="badge badge-${getDataTypeColor(type)} badge-pill">${count}</span>
            </li>`;
            });

            html += `
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Recent Datasets</h5>
                    <div class="list-group">
        `;

            stats.recent_datasets.forEach(function(dataset) {
                html += `
                <div class="list-group-item">
                    <strong>${dataset.name}</strong>
                    <br><small>${formatDataType(dataset.data_type)} - ${formatDate(dataset.created_at)}</small>
                </div>
            `;
            });

            html += `
                    </div>
                    
                    <h5>Most Used Datasets</h5>
                    <div class="list-group">
        `;

            stats.most_used_datasets.forEach(function(dataset) {
                html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${dataset.name}</strong>
                        <br><small>${formatDataType(dataset.data_type)}</small>
                    </div>
                    <span class="badge badge-info badge-pill">${dataset.entitlements_count}</span>
                </div>
            `;
            });

            html += `
                    </div>
                </div>
            </div>
        `;

            $('#statsContent').html(html);
        }

        // Render pagination
        function renderPagination(response) {
            let html = '<nav><ul class="pagination pagination-sm">';

            // Previous button
            if (response.current_page > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${response.current_page - 1})">Previous</a></li>`;
            }

            // Page numbers
            for (let i = 1; i <= response.last_page; i++) {
                const active = i === response.current_page ? 'active' : '';
                html += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
            }

            // Next button
            if (response.current_page < response.last_page) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${response.current_page + 1})">Next</a></li>`;
            }

            html += '</ul></nav>';
            $('#datasetsPagination').html(html);
        }

        // Helper functions
        function getDataTypeColor(dataType) {
            const colors = {
                'thermal_raster': 'danger',
                'building_data': 'success',
                'thermal_analysis': 'warning',
                'heat_map': 'info'
            };
            return colors[dataType] || 'secondary';
        }

        function formatDataType(dataType) {
            const formatted = {
                'thermal_raster': 'Thermal Raster',
                'building_data': 'Building Data',
                'thermal_analysis': 'Thermal Analysis',
                'heat_map': 'Heat Map'
            };
            return formatted[dataType] || dataType;
        }

        // Create dataset form
        $('#createDatasetForm').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                name: $('#createName').val(),
                data_type: $('#createDataType').val(),
                description: $('#createDescription').val(),
                storage_location: $('#createStorageLocation').val(),
                version: $('#createVersion').val(),
                source: $('#createSource').val(),
                format: $('#createFormat').val(),
                size_mb: $('#createSizeMb').val(),
                spatial_resolution: $('#createSpatialResolution').val(),
                temporal_coverage: $('#createTemporalCoverage').val()
            };

            $.ajax({
                url: '/api/admin/datasets',
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(formData),
                success: function(response) {
                    $('#createDatasetModal').modal('hide');
                    $('#createDatasetForm')[0].reset();
                    loadDatasets();
                    toastr.success('Dataset created successfully!');
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        let errorMessage = 'Please fix the following errors:<br>';
                        Object.keys(errors).forEach(key => {
                            errorMessage += `• ${errors[key][0]}<br>`;
                        });
                        toastr.error(errorMessage);
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Error creating dataset');
                    }
                }
            });
        });

        // Global functions for buttons
        window.changePage = function(page) {
            currentPage = page;
            loadDatasets();
        };

        window.viewDataset = function(datasetId) {
            // Load dataset details
            $.ajax({
                url: `/api/admin/datasets/${datasetId}`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const dataset = response.dataset;
                    let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Dataset Information</h5>
                            <p><strong>Name:</strong> ${dataset.name}</p>
                            <p><strong>Data Type:</strong> <span class="badge badge-${getDataTypeColor(dataset.data_type)}">${formatDataType(dataset.data_type)}</span></p>
                            <p><strong>Description:</strong> ${dataset.description || 'None'}</p>
                            <p><strong>Storage Location:</strong> ${dataset.storage_location || 'None'}</p>
                            <p><strong>Version:</strong> ${dataset.version || 'None'}</p>
                            <p><strong>Created:</strong> ${formatDateTime(dataset.created_at)}</p>
                            <p><strong>Users with Access:</strong> ${response.users_with_access}</p>
                        </div>
                        <div class="col-md-6">
                            <h5>Metadata</h5>
                            <pre class="bg-light p-3">${dataset.metadata ? JSON.stringify(dataset.metadata, null, 2) : 'None'}</pre>
                            
                            <h5>Entitlements (${response.entitlements_count})</h5>
                            <div class="list-group">
                `;

                    dataset.entitlements.forEach(function(entitlement) {
                        html += `
                        <div class="list-group-item">
                            <strong>${entitlement.type}</strong>
                            <br><small>Expires: ${formatDate(entitlement.expires_at)}</small>
                            <br><small>Users: ${entitlement.users.length}</small>
                        </div>
                    `;
                    });

                    html += `
                            </div>
                        </div>
                    </div>
                `;

                    $('#datasetDetailsContent').html(html);
                    $('#datasetDetailsModal').modal('show');
                }
            });
        };

        window.editDataset = function(datasetId) {
            // Load dataset data for editing
            $.ajax({
                url: `/api/admin/datasets/${datasetId}`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const dataset = response.dataset;
                    $('#editDatasetId').val(dataset.id);
                    $('#editName').val(dataset.name);
                    $('#editDataType').val(dataset.data_type);
                    $('#editDescription').val(dataset.description || '');
                    $('#editStorageLocation').val(dataset.storage_location || '');
                    $('#editVersion').val(dataset.version || '');

                    // Populate metadata fields
                    const metadata = dataset.metadata || {};
                    $('#editSource').val(metadata.source || '');
                    $('#editFormat').val(metadata.format || '');
                    $('#editSizeMb').val(metadata.size_mb || '');
                    $('#editSpatialResolution').val(metadata.spatial_resolution || '');
                    $('#editTemporalCoverage').val(metadata.temporal_coverage || '');

                    $('#editDatasetModal').modal('show');
                }
            });
        };

        window.deleteDataset = function(datasetId, datasetName) {
            showDeleteConfirm(datasetName, function() {
                $.ajax({
                    url: `/api/admin/datasets/${datasetId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        loadDatasets();
                        toastr.success('Dataset deleted successfully!');
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Error deleting dataset');
                    }
                });
            });
        };

        // Edit dataset form
        $('#editDatasetForm').on('submit', function(e) {
            e.preventDefault();

            const datasetId = $('#editDatasetId').val();
            const formData = {
                name: $('#editName').val(),
                data_type: $('#editDataType').val(),
                description: $('#editDescription').val(),
                storage_location: $('#editStorageLocation').val(),
                version: $('#editVersion').val(),
                source: $('#editSource').val(),
                format: $('#editFormat').val(),
                size_mb: $('#editSizeMb').val(),
                spatial_resolution: $('#editSpatialResolution').val(),
                temporal_coverage: $('#editTemporalCoverage').val()
            };

            $.ajax({
                url: `/api/admin/datasets/${datasetId}`,
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(formData),
                success: function(response) {
                    $('#editDatasetModal').modal('hide');
                    loadDatasets();
                    toastr.success('Dataset updated successfully!');
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        let errorMessage = 'Please fix the following errors:<br>';
                        Object.keys(errors).forEach(key => {
                            errorMessage += `• ${errors[key][0]}<br>`;
                        });
                        toastr.error(errorMessage);
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Error updating dataset');
                    }
                }
            });
        });
    });
</script>
@endpush