@extends('adminlte::page')

@section('title', 'Entitlement Management - MELT-B Admin')

@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>
        <i class="fas fa-key text-primary"></i>
        Entitlement Management
        <small class="text-muted">Manage user access permissions</small>
    </h1>
    <button class="btn btn-primary" data-toggle="modal" data-target="#createEntitlementModal">
        <i class="fas fa-plus"></i> Add New Entitlement
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
                    All Entitlements
                </h3>
                <div class="card-tools">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" id="searchEntitlements" class="form-control float-right" placeholder="Search entitlements...">
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
                        <select id="typeFilter" class="form-control">
                            <option value="">All Types</option>
                            <option value="DS-ALL">DS-ALL (Full Dataset)</option>
                            <option value="DS-AOI">DS-AOI (Area of Interest)</option>
                            <option value="DS-BLD">DS-BLD (Specific Buildings)</option>
                            <option value="TILES">TILES (Map Tiles)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="datasetFilter" class="form-control">
                            <option value="">All Datasets</option>
                            <!-- Populated dynamically -->
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
                        <button id="refreshEntitlements" class="btn btn-outline-primary">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button id="viewStats" class="btn btn-outline-info" data-toggle="modal" data-target="#statsModal">
                            <i class="fas fa-chart-bar"></i> Statistics
                        </button>
                    </div>
                </div>

                <!-- Entitlements Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="entitlementsTable">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Dataset</th>
                                <th>Details</th>
                                <th>Users</th>
                                <th>Expires</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="entitlementsTableBody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading entitlements...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="entitlementsPagination" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Create Entitlement Modal -->
<div class="modal fade" id="createEntitlementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-plus"></i> Create New Entitlement
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createEntitlementForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createType">Entitlement Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="createType" name="type" required onchange="toggleEntitlementFields('create')">
                                    <option value="">Select Type</option>
                                    <option value="DS-ALL">DS-ALL (Full Dataset Access)</option>
                                    <option value="DS-AOI">DS-AOI (Area of Interest)</option>
                                    <option value="DS-BLD">DS-BLD (Specific Buildings)</option>
                                    <option value="TILES">TILES (Map Tiles)</option>
                                </select>
                                <small class="form-text text-muted">Choose the type of access this entitlement provides</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createDataset">Dataset <span class="text-danger">*</span></label>
                                <select class="form-control" id="createDataset" name="dataset_id" required>
                                    <option value="">Select Dataset</option>
                                    <!-- Populated dynamically -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- AOI Coordinates (for DS-AOI and TILES) -->
                    <div id="createAoiSection" style="display: none;">
                        <div class="form-group">
                            <label for="createAoiCoordinates">Area of Interest Coordinates <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="createAoiCoordinates" name="aoi_coordinates" rows="4"
                                placeholder='[[lng1, lat1], [lng2, lat2], [lng3, lat3], [lng1, lat1]]'></textarea>
                            <small class="form-text text-muted">Enter polygon coordinates as JSON array: [[longitude, latitude], ...]. Must form a closed polygon.</small>
                            <small class="form-text text-info">Example: [[21.5804, 47.5316], [21.6304, 47.5316], [21.6304, 47.5716], [21.5804, 47.5716], [21.5804, 47.5316]]</small>
                        </div>
                    </div>

                    <!-- Building GIDs (for DS-BLD) -->
                    <div id="createBuildingSection" style="display: none;">
                        <div class="form-group">
                            <label for="createBuildingGids">Building GIDs <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="createBuildingGids" name="building_gids" rows="3"
                                placeholder='["building_001", "building_002", "building_003"]'></textarea>
                            <small class="form-text text-muted">Enter building GIDs as JSON array: ["gid1", "gid2", ...]</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createDownloadFormats">Download Formats</label>
                                <div class="form-check-container">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="csv" value="csv" name="download_formats[]">
                                        <label class="form-check-label" for="csv">CSV</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="geojson" value="geojson" name="download_formats[]">
                                        <label class="form-check-label" for="geojson">GeoJSON</label>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createExpiresAt">Expiration Date</label>
                                <input type="datetime-local" class="form-control" id="createExpiresAt" name="expires_at">
                                <small class="form-text text-muted">Leave blank for no expiration</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Entitlement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Entitlement Modal -->
<div class="modal fade" id="editEntitlementModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-edit"></i> Edit Entitlement
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editEntitlementForm">
                <input type="hidden" id="editEntitlementId" name="id">
                <div class="modal-body">
                    <!-- Similar structure as create form -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editType">Entitlement Type <span class="text-danger">*</span></label>
                                <select class="form-control" id="editType" name="type" required onchange="toggleEntitlementFields('edit')">
                                    <option value="DS-ALL">DS-ALL (Full Dataset Access)</option>
                                    <option value="DS-AOI">DS-AOI (Area of Interest)</option>
                                    <option value="DS-BLD">DS-BLD (Specific Buildings)</option>
                                    <option value="TILES">TILES (Map Tiles)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editDataset">Dataset <span class="text-danger">*</span></label>
                                <select class="form-control" id="editDataset" name="dataset_id" required>
                                    <!-- Populated dynamically -->
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- AOI Coordinates -->
                    <div id="editAoiSection" style="display: none;">
                        <div class="form-group">
                            <label for="editAoiCoordinates">Area of Interest Coordinates <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="editAoiCoordinates" name="aoi_coordinates" rows="4"></textarea>
                            <small class="form-text text-muted">Enter polygon coordinates as JSON array</small>
                        </div>
                    </div>

                    <!-- Building GIDs -->
                    <div id="editBuildingSection" style="display: none;">
                        <div class="form-group">
                            <label for="editBuildingGids">Building GIDs <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="editBuildingGids" name="building_gids" rows="3"></textarea>
                            <small class="form-text text-muted">Enter building GIDs as JSON array</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Download Formats</label>
                                <div class="form-check-container" id="editDownloadFormats">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="editCsv" value="csv" name="edit_download_formats[]">
                                        <label class="form-check-label" for="editCsv">CSV</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="editGeojson" value="geojson" name="edit_download_formats[]">
                                        <label class="form-check-label" for="editGeojson">GeoJSON</label>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editExpiresAt">Expiration Date</label>
                                <input type="datetime-local" class="form-control" id="editExpiresAt" name="expires_at">
                                <small class="form-text text-muted">Leave blank for no expiration</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Entitlement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Entitlement Details Modal -->
<div class="modal fade" id="entitlementDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-key"></i> Entitlement Details
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="entitlementDetailsContent">
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
                    <i class="fas fa-chart-bar"></i> Entitlement Statistics
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

<!-- Entitlement Users Management Modal -->
<div class="modal fade" id="entitlementUsersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-users"></i> Manage Entitlement Users
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="manageEntitlementId">

                <!-- Add New User -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-user-plus"></i> Assign New User
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Available Users</label>
                            <select class="form-control" id="availableUsers">
                                <option value="">Select a user to assign...</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="assignUserToEntitlement()">
                            <i class="fas fa-user-plus"></i> Assign User
                        </button>
                    </div>
                </div>

                <!-- Current Users -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list"></i> Current Users
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="currentEntitlementUsers">
                            <!-- Loaded dynamically -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
<style>
    .entitlement-type-badge {
        font-size: 0.8em;
        padding: 0.25em 0.6em;
        border-radius: 0.25rem;
    }

    .table td {
        vertical-align: middle;
    }

    .pagination {
        justify-content: center;
    }

    .form-check-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .details-cell {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
@stop

@section('js')
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

    window.formatDateTimeForInput = function(dateString) {
        if (!dateString) return '';

        const date = new Date(dateString);
        // Convert to local time for datetime-local input
        const offset = date.getTimezoneOffset();
        const localDate = new Date(date.getTime() - (offset * 60 * 1000));
        return localDate.toISOString().slice(0, 16);
    };

    window.parseDateTimeFromInput = function(inputValue) {
        if (!inputValue) return null;

        // Parse the datetime-local input and convert to UTC for server
        const localDate = new Date(inputValue);
        return localDate.toISOString();
    };

    $(document).ready(function() {
        let currentPage = 1;
        let perPage = 15;
        let searchTerm = '';
        let typeFilter = '';
        let datasetFilter = '';

        // Load data on page load
        loadEntitlements();
        loadDatasets();

        // Search functionality
        let searchTimeout;
        $('#searchEntitlements').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                searchTerm = $('#searchEntitlements').val();
                currentPage = 1;
                loadEntitlements();
            }, 500);
        });

        // Filter functionality
        $('#typeFilter, #datasetFilter, #perPage').on('change', function() {
            typeFilter = $('#typeFilter').val();
            datasetFilter = $('#datasetFilter').val();
            perPage = $('#perPage').val();
            currentPage = 1;
            loadEntitlements();
        });

        // Refresh button
        $('#refreshEntitlements').on('click', function() {
            loadEntitlements();
        });

        // View stats button
        $('#viewStats').on('click', function() {
            loadStats();
        });

        // Load datasets for dropdowns
        function loadDatasets() {
            $.ajax({
                url: '/api/admin/entitlements/datasets',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    let options = '<option value="">All Datasets</option>';
                    response.datasets.forEach(function(dataset) {
                        options += `<option value="${dataset.id}">${dataset.name}</option>`;
                    });
                    $('#datasetFilter').html(options);

                    // For create/edit modals
                    let modalOptions = '<option value="">Select Dataset</option>';
                    response.datasets.forEach(function(dataset) {
                        modalOptions += `<option value="${dataset.id}">${dataset.name}</option>`;
                    });
                    $('#createDataset, #editDataset').html(modalOptions);
                }
            });
        }

        // Load entitlements function
        function loadEntitlements() {
            const params = new URLSearchParams({
                page: currentPage,
                per_page: perPage,
                type: typeFilter,
                dataset_id: datasetFilter
            });

            $.ajax({
                url: '/api/admin/entitlements?' + params.toString(),
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                },
                success: function(response) {
                    renderEntitlementsTable(response.data);
                    renderPagination(response);
                },
                error: function(xhr) {
                    console.error('Error loading entitlements:', xhr);
                    $('#entitlementsTableBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading entitlements</td></tr>');
                }
            });
        }

        // Load statistics
        function loadStats() {
            $.ajax({
                url: '/api/admin/entitlements/stats',
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

        // Render entitlements table
        function renderEntitlementsTable(entitlements) {
            let html = '';
            entitlements.forEach(function(entitlement) {
                const usersCount = entitlement.users ? entitlement.users.length : 0;
                const typeColor = getTypeColor(entitlement.type);
                const expiresAt = formatDate(entitlement.expires_at);
                const isExpired = entitlement.expires_at && new Date(entitlement.expires_at) < new Date();

                let details = '';
                if (entitlement.type === 'DS-AOI' || entitlement.type === 'TILES') {
                    details = 'AOI Polygon';
                } else if (entitlement.type === 'DS-BLD') {
                    const buildingCount = entitlement.building_gids ? entitlement.building_gids.length : 0;
                    details = `${buildingCount} Buildings`;
                } else if (entitlement.type === 'DS-ALL') {
                    details = 'Full Access';
                }

                html += `
                <tr ${isExpired ? 'class="table-warning"' : ''}>
                    <td><span class="badge badge-${typeColor}">${entitlement.type}</span></td>
                    <td>${entitlement.dataset?.name || 'Unknown'}</td>
                    <td class="details-cell" title="${details}">${details}</td>
                    <td><span class="badge badge-info">${usersCount}</span></td>
                    <td>
                        ${isExpired ? '<span class="badge badge-danger">Expired</span><br>' : ''}
                        <small>${expiresAt}</small>
                    </td>
                    <td><small>${formatDate(entitlement.created_at)}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="viewEntitlement(${entitlement.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="editEntitlement(${entitlement.id})" title="Edit Entitlement">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger" onclick="deleteEntitlement(${entitlement.id}, '${entitlement.type}')" title="Delete Entitlement">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            });
            $('#entitlementsTableBody').html(html);
        }

        // Render statistics
        function renderStats(stats) {
            let html = `
            <div class="row">
                <div class="col-md-6">
                    <h5>Overview</h5>
                    <p><strong>Total Entitlements:</strong> ${stats.total_entitlements}</p>
                    <p><strong>Active Entitlements:</strong> ${stats.active_entitlements}</p>
                    <p><strong>Expired Entitlements:</strong> ${stats.expired_entitlements}</p>
                    
                    <h5>By Type</h5>
                    <ul class="list-group">
        `;

            Object.entries(stats.by_type).forEach(([type, count]) => {
                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                ${type}
                <span class="badge badge-${getTypeColor(type)} badge-pill">${count}</span>
            </li>`;
            });

            html += `
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>By Dataset</h5>
                    <div class="list-group">
        `;

            stats.by_dataset.forEach(function(item) {
                html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    ${item.dataset_name}
                    <span class="badge badge-info badge-pill">${item.count}</span>
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

            if (response.current_page > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${response.current_page - 1})">Previous</a></li>`;
            }

            for (let i = 1; i <= response.last_page; i++) {
                const active = i === response.current_page ? 'active' : '';
                html += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
            }

            if (response.current_page < response.last_page) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${response.current_page + 1})">Next</a></li>`;
            }

            html += '</ul></nav>';
            $('#entitlementsPagination').html(html);
        }

        // Helper functions
        function getTypeColor(type) {
            const colors = {
                'DS-ALL': 'success',
                'DS-AOI': 'warning',
                'DS-BLD': 'info',
                'TILES': 'primary'
            };
            return colors[type] || 'secondary';
        }

        // Toggle entitlement fields based on type
        window.toggleEntitlementFields = function(prefix) {
            const type = $(`#${prefix}Type`).val();
            const aoiSection = $(`#${prefix}AoiSection`);
            const buildingSection = $(`#${prefix}BuildingSection`);

            aoiSection.hide();
            buildingSection.hide();

            if (type === 'DS-AOI' || type === 'TILES') {
                aoiSection.show();
            } else if (type === 'DS-BLD') {
                buildingSection.show();
            }
        };

        // Create entitlement form
        $('#createEntitlementForm').on('submit', function(e) {
            e.preventDefault();

            const formData = {
                type: $('#createType').val(),
                dataset_id: $('#createDataset').val(),
                expires_at: parseDateTimeFromInput($('#createExpiresAt').val())
            };

            // Handle download formats
            const downloadFormats = [];
            $('input[name="download_formats[]"]:checked').each(function() {
                downloadFormats.push($(this).val());
            });
            if (downloadFormats.length > 0) {
                formData.download_formats = downloadFormats;
            }

            // Handle type-specific fields
            if (formData.type === 'DS-AOI' || formData.type === 'TILES') {
                try {
                    formData.aoi_coordinates = JSON.parse($('#createAoiCoordinates').val());
                } catch (e) {
                    showModalAlert('createEntitlementModal', 'danger', 'Invalid AOI coordinates format. Please check your JSON syntax.');
                    return;
                }
            } else if (formData.type === 'DS-BLD') {
                try {
                    formData.building_gids = JSON.parse($('#createBuildingGids').val());
                } catch (e) {
                    showModalAlert('createEntitlementModal', 'danger', 'Invalid building GIDs format. Please check your JSON syntax.');
                    return;
                }
            }

            $.ajax({
                url: '/api/admin/entitlements',
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(formData),
                success: function(response) {
                    $('#createEntitlementModal').modal('hide');
                    $('#createEntitlementForm')[0].reset();
                    toggleEntitlementFields('create');
                    // Force reload by clearing any potential cache
                    setTimeout(function() {
                        loadEntitlements();
                    }, 100);
                    showAlert('success', 'Entitlement created successfully!');
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        let errorMessage = 'Please fix the following errors:<br>';
                        Object.keys(errors).forEach(key => {
                            errorMessage += `• ${errors[key][0]}<br>`;
                        });
                        showModalAlert('createEntitlementModal', 'danger', errorMessage);
                    } else {
                        showModalAlert('createEntitlementModal', 'danger', xhr.responseJSON?.message || 'Error creating entitlement');
                    }
                }
            });
        });

        // Global functions for buttons
        window.changePage = function(page) {
            currentPage = page;
            loadEntitlements();
        };

        window.viewEntitlement = function(entitlementId) {
            $.ajax({
                url: `/api/admin/entitlements/${entitlementId}?include_geometry=1`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const entitlement = response.entitlement;
                    let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Entitlement Information</h5>
                            <p><strong>Type:</strong> <span class="badge badge-${getTypeColor(entitlement.type)}">${entitlement.type}</span></p>
                            <p><strong>Dataset:</strong> ${entitlement.dataset?.name || 'Unknown'}</p>
                            <p><strong>Created:</strong> ${formatDateTime(entitlement.created_at)}</p>
                            <p><strong>Expires:</strong> ${formatDateTime(entitlement.expires_at)}</p>
                            <p><strong>Download Formats:</strong> ${entitlement.download_formats ? entitlement.download_formats.join(', ') : 'None'}</p>
                `;

                    if (entitlement.type === 'DS-BLD' && entitlement.building_gids) {
                        html += `<p><strong>Building GIDs:</strong> ${entitlement.building_gids.length} buildings</p>`;
                    } else if ((entitlement.type === 'DS-AOI' || entitlement.type === 'TILES') && entitlement.aoi_geom) {
                        html += `<p><strong>AOI:</strong> Polygon defined</p>`;
                    }

                    html += `
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5>Assigned Users (${entitlement.users ? entitlement.users.length : 0})</h5>
                                <button class="btn btn-sm btn-primary" onclick="manageEntitlementUsers(${entitlement.id})">
                                    <i class="fas fa-users"></i> Manage Users
                                </button>
                            </div>
                            <div class="list-group">
                `;

                    if (entitlement.users && entitlement.users.length > 0) {
                        entitlement.users.forEach(function(user) {
                            html += `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${user.name}</strong> (${user.email})
                                        <br><small>Role: ${user.role}</small>
                                    </div>
                                    <button class="btn btn-sm btn-danger" onclick="removeEntitlementUser(${user.id}, ${entitlement.id})">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        });
                    } else {
                        html += `
                        <div class="list-group-item text-muted">
                            <em>No users assigned</em>
                            <br><small>Click "Manage Users" to assign users to this entitlement.</small>
                        </div>
                        `;
                    }

                    html += `
                            </div>
                        </div>
                    </div>
                `;

                    $('#entitlementDetailsContent').html(html);
                    $('#entitlementDetailsModal').modal('show');
                }
            });
        };

        window.editEntitlement = function(entitlementId) {
            // Load entitlement data for editing
            $.ajax({
                url: `/api/admin/entitlements/${entitlementId}?include_geometry=1`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const entitlement = response.entitlement;
                    $('#editEntitlementId').val(entitlement.id);
                    $('#editType').val(entitlement.type);
                    $('#editDataset').val(entitlement.dataset_id);

                    // Handle type-specific fields
                    toggleEntitlementFields('edit');

                    // Clear type-specific fields first
                    $('#editBuildingGids').val('');
                    $('#editAoiCoordinates').val('');

                    // Handle type-specific field values
                    if (entitlement.building_gids) {
                        $('#editBuildingGids').val(JSON.stringify(entitlement.building_gids, null, 2));
                    }

                    // Handle AOI coordinates - check both aoi_coordinates and aoi_geom
                    if (entitlement.aoi_coordinates) {
                        $('#editAoiCoordinates').val(JSON.stringify(entitlement.aoi_coordinates, null, 2));
                    } else if (entitlement.aoi_geom && entitlement.aoi_geom.coordinates) {
                        // Extract coordinates from GeoJSON format
                        const coordinates = entitlement.aoi_geom.coordinates[0];
                        $('#editAoiCoordinates').val(JSON.stringify(coordinates, null, 2));
                    }

                    // Handle download formats
                    $('#editDownloadFormats input[type="checkbox"]').prop('checked', false);
                    if (entitlement.download_formats) {
                        entitlement.download_formats.forEach(function(format) {
                            $(`#edit${format.charAt(0).toUpperCase() + format.slice(1)}`).prop('checked', true);
                        });
                    }

                    $('#editExpiresAt').val(formatDateTimeForInput(entitlement.expires_at));

                    $('#editEntitlementModal').modal('show');
                }
            });
        };

        window.deleteEntitlement = function(entitlementId, entitlementType) {
            if (confirm(`Are you sure you want to delete this ${entitlementType} entitlement?`)) {
                $.ajax({
                    url: `/api/admin/entitlements/${entitlementId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        loadEntitlements();
                        showAlert('success', 'Entitlement deleted successfully!');
                    },
                    error: function(xhr) {
                        showAlert('danger', xhr.responseJSON?.message || 'Error deleting entitlement');
                    }
                });
            }
        };

        // Edit entitlement form
        $('#editEntitlementForm').on('submit', function(e) {
            e.preventDefault();

            const entitlementId = $('#editEntitlementId').val();
            const formData = {
                type: $('#editType').val(),
                dataset_id: $('#editDataset').val(),
                expires_at: parseDateTimeFromInput($('#editExpiresAt').val())
            };

            // Handle download formats
            const downloadFormats = [];
            $('input[name="edit_download_formats[]"]:checked').each(function() {
                downloadFormats.push($(this).val());
            });
            if (downloadFormats.length > 0) {
                formData.download_formats = downloadFormats;
            }

            // Handle type-specific fields
            if (formData.type === 'DS-AOI' || formData.type === 'TILES') {
                const aoiText = $('#editAoiCoordinates').val();
                if (aoiText) {
                    try {
                        formData.aoi_coordinates = JSON.parse(aoiText);
                    } catch (e) {
                        showModalAlert('editEntitlementModal', 'danger', 'Invalid AOI coordinates format. Please check your JSON syntax.');
                        return;
                    }
                }
            } else if (formData.type === 'DS-BLD') {
                const buildingText = $('#editBuildingGids').val();
                if (buildingText) {
                    try {
                        formData.building_gids = JSON.parse(buildingText);
                    } catch (e) {
                        showModalAlert('editEntitlementModal', 'danger', 'Invalid building GIDs format. Please check your JSON syntax.');
                        return;
                    }
                }
            }

            $.ajax({
                url: `/api/admin/entitlements/${entitlementId}`,
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(formData),
                success: function(response) {
                    $('#editEntitlementModal').modal('hide');
                    // Force reload by clearing any potential cache
                    setTimeout(function() {
                        loadEntitlements();
                    }, 100);
                    showAlert('success', 'Entitlement updated successfully!');
                },
                error: function(xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors) {
                        let errorMessage = 'Please fix the following errors:<br>';
                        Object.keys(errors).forEach(key => {
                            errorMessage += `• ${errors[key][0]}<br>`;
                        });
                        showModalAlert('editEntitlementModal', 'danger', errorMessage);
                    } else {
                        showModalAlert('editEntitlementModal', 'danger', xhr.responseJSON?.message || 'Error updating entitlement');
                    }
                }
            });
        });

        function showAlert(type, message) {
            const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
            $('.content-header').after(alertHtml);

            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        }

        function showModalAlert(modalId, type, message) {
            // Remove existing alerts in this modal
            $(`#${modalId} .modal-alert`).remove();

            const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show modal-alert" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
            $(`#${modalId} .modal-body`).prepend(alertHtml);

            // Auto-dismiss after 8 seconds
            setTimeout(function() {
                $(`#${modalId} .modal-alert`).alert('close');
            }, 8000);
        }

        // Entitlement user management functions
        window.manageEntitlementUsers = function(entitlementId) {
            $('#manageEntitlementId').val(entitlementId);
            loadAvailableUsers(entitlementId);
            loadCurrentEntitlementUsers(entitlementId);
            $('#entitlementUsersModal').modal('show');
        };

        function loadAvailableUsers(entitlementId) {
            $.ajax({
                url: '/api/admin/users',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    let html = '<option value="">Select a user to assign...</option>';

                    // Get current entitlement's users to filter them out
                    $.ajax({
                        url: `/api/admin/entitlements/${entitlementId}`,
                        method: 'GET',
                        headers: {
                            'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                            'Accept': 'application/json'
                        },
                        success: function(entitlementResponse) {
                            const entitlementUserIds = entitlementResponse.entitlement.users?.map(u => u.id) || [];

                            response.data.forEach(function(user) {
                                if (!entitlementUserIds.includes(user.id)) {
                                    html += `<option value="${user.id}">
                                        ${user.name} (${user.email}) - ${user.role}
                                    </option>`;
                                }
                            });

                            $('#availableUsers').html(html);
                        }
                    });
                }
            });
        }

        function loadCurrentEntitlementUsers(entitlementId) {
            $.ajax({
                url: `/api/admin/entitlements/${entitlementId}`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const users = response.entitlement.users || [];
                    let html = '';

                    if (users.length > 0) {
                        users.forEach(function(user) {
                            html += `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${user.name}</strong> (${user.email})
                                        <br><small class="text-muted">Role: ${user.role}</small>
                                    </div>
                                    <button class="btn btn-sm btn-danger" onclick="removeEntitlementUser(${user.id}, ${entitlementId})">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                            </div>
                            `;
                        });
                    } else {
                        html = '<div class="text-center text-muted p-3"><em>No users assigned</em></div>';
                    }

                    $('#currentEntitlementUsers').html(html);
                }
            });
        }

        window.assignUserToEntitlement = function() {
            const entitlementId = $('#manageEntitlementId').val();
            const userId = $('#availableUsers').val();

            if (!userId) {
                showModalAlert('entitlementUsersModal', 'warning', 'Please select a user to assign.');
                return;
            }

            $.ajax({
                url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    showAlert('success', 'User assigned successfully!');
                    loadAvailableUsers(entitlementId);
                    loadCurrentEntitlementUsers(entitlementId);
                    loadEntitlements(); // Refresh the main table
                },
                error: function(xhr) {
                    showAlert('danger', xhr.responseJSON?.message || 'Error assigning user');
                }
            });
        };

        window.removeEntitlementUser = function(userId, entitlementId) {
            if (confirm('Are you sure you want to remove this user from the entitlement?')) {
                $.ajax({
                    url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        showAlert('success', 'User removed successfully!');
                        loadEntitlements(); // Refresh the main table

                        // If entitlement details modal is open, refresh it
                        if ($('#entitlementDetailsModal').hasClass('show')) {
                            viewEntitlement(entitlementId);
                        }
                    },
                    error: function(xhr) {
                        showAlert('danger', xhr.responseJSON?.message || 'Error removing user');
                    }
                });
            }
        };

        window.removeUserFromEntitlementModal = function(userId, entitlementId) {
            if (confirm('Are you sure you want to remove this user from the entitlement?')) {
                $.ajax({
                    url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        showAlert('success', 'User removed successfully!');
                        loadAvailableUsers(entitlementId);
                        loadCurrentEntitlementUsers(entitlementId);
                        loadEntitlements(); // Refresh the main table
                    },
                    error: function(xhr) {
                        showAlert('danger', xhr.responseJSON?.message || 'Error removing user');
                    }
                });
            }
        };
    });
</script>
@stop