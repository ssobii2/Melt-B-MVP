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

                    <!-- AOI Coordinates (for DS-AOI) -->
                    <div id="createAoiSection" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map-marked-alt"></i> Area of Interest Selection
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Bounding Box Method -->
                                <div id="createAoiBounds">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="createNorthBound">North Latitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="createNorthBound" step="0.000001" placeholder="48.8566">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="createSouthBound">South Latitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="createSouthBound" step="0.000001" placeholder="48.8466">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="createEastBound">East Longitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="createEastBound" step="0.000001" placeholder="2.3522">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="createWestBound">West Longitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="createWestBound" step="0.000001" placeholder="2.3422">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-light p-3 rounded mb-3">
                                        <button type="button" class="btn btn-info btn-sm" onclick="generatePolygonFromBounds('create')">
                                            <i class="fas fa-magic"></i> Generate Polygon from Bounds
                                        </button>
                                        <small class="form-text text-muted mt-2">Enter the bounding box coordinates and click the button to generate polygon coordinates automatically.</small>
                                    </div>
                                    <!-- Hidden field to store generated coordinates -->
                                    <input type="hidden" id="createAoiCoordinates" name="aoi_coordinates">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Building GIDs (for DS-BLD) -->
                    <div id="createBuildingSection" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-building"></i> Building Selection
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">                                        <div class="form-group">
                                            <label for="createBuildingDataset">Filter by Dataset</label>
                                            <select class="form-control" id="createBuildingDataset">
                                                <option value="">All Datasets</option>
                                                <!-- Populated dynamically -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Select from List Method -->
                                <div id="createBuildingSelect">
                                    <div class="form-group">
                                        <label>Available Buildings</label>
                                        <input type="text" class="form-control" id="createBuildingSearch" placeholder="Search buildings by GID...">
                                    </div>
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="mb-0">Select Buildings (<span id="createSelectedCount">0</span> selected) <span class="text-danger">*</span></label>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="selectAllBuildings('create')">
                                                    Select All
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearBuildingSelection('create')">
                                                    Clear All
                                                </button>
                                            </div>
                                        </div>
                                        <div id="createBuildingList" class="border rounded p-2 bg-light" style="max-height: 300px; overflow-y: auto;">
                                            <p class="text-muted text-center mb-0">Loading buildings...</p>
                                        </div>
                                        <div id="createBuildingPagination" class="d-flex justify-content-between align-items-center mt-2" style="display: none;">
                                            <small class="text-muted" id="createBuildingPageInfo"></small>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="createBuildingPrevBtn" onclick="changeBuildingPage('create', 'prev')" disabled>
                                                    <i class="fas fa-chevron-left"></i> Previous
                                                </button>
                                                <span class="mx-2">Page <span id="createBuildingCurrentPage">1</span> of <span id="createBuildingTotalPages">1</span></span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="createBuildingNextBtn" onclick="changeBuildingPage('create', 'next')">
                                                    Next <i class="fas fa-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Hidden field to store selected building GIDs -->
                                    <input type="hidden" id="createBuildingGids" name="building_gids">
                                </div>
                            </div>
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
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map-marked-alt"></i> Area of Interest Selection
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Bounding Box Method -->
                                <div id="editAoiBounds">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="editNorthBound">North Latitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="editNorthBound" step="0.000001">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="editSouthBound">South Latitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="editSouthBound" step="0.000001">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="editEastBound">East Longitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="editEastBound" step="0.000001">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="editWestBound">West Longitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="editWestBound" step="0.000001">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-light p-3 rounded mb-3">
                                        <button type="button" class="btn btn-info btn-sm" onclick="generatePolygonFromBounds('edit')">
                                            <i class="fas fa-magic"></i> Generate Polygon from Bounds
                                        </button>
                                        <small class="form-text text-muted mt-2">Enter the bounding box coordinates and click the button to generate polygon coordinates automatically.</small>
                                    </div>
                                    <!-- Hidden field to store generated coordinates -->
                                    <input type="hidden" id="editAoiCoordinates" name="aoi_coordinates">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Building GIDs -->
                    <div id="editBuildingSection" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-building"></i> Building Selection
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="editBuildingDataset">Filter by Dataset</label>
                                            <select class="form-control" id="editBuildingDataset">
                                                <option value="">All Datasets</option>
                                                <!-- Populated dynamically -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Select from List Method -->
                                <div id="editBuildingSelect">
                                    <div class="form-group">
                                        <label>Available Buildings</label>
                                        <input type="text" class="form-control" id="editBuildingSearch" placeholder="Search buildings by GID...">
                                    </div>
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="mb-0">Select Buildings (<span id="editSelectedCount">0</span> selected) <span class="text-danger">*</span></label>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="selectAllBuildings('edit')">
                                                    Select All
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearBuildingSelection('edit')">
                                                    Clear All
                                                </button>
                                            </div>
                                        </div>
                                        <div id="editBuildingList" class="border rounded p-2 bg-light" style="max-height: 300px; overflow-y: auto;">
                                            <p class="text-muted text-center mb-0">Loading buildings...</p>
                                        </div>
                                        <div id="editBuildingPagination" class="d-flex justify-content-between align-items-center mt-2" style="display: none;">
                                            <small class="text-muted" id="editBuildingPageInfo"></small>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="editBuildingPrevBtn" onclick="changeBuildingPage('edit', 'prev')" disabled>
                                                    <i class="fas fa-chevron-left"></i> Previous
                                                </button>
                                                <span class="mx-2">Page <span id="editBuildingCurrentPage">1</span> of <span id="editBuildingTotalPages">1</span></span>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="editBuildingNextBtn" onclick="changeBuildingPage('edit', 'next')">
                                                    Next <i class="fas fa-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Hidden field to store selected building GIDs -->
                                    <input type="hidden" id="editBuildingGids" name="building_gids">
                                </div>
                            </div>
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

        // Initialize global variables
        window.globalBuildingSelection = {
            create: new Set(),
            edit: new Set()
        };
        
        window.buildingPagination = {
            create: { currentPage: 1, totalPages: 1, perPage: 50 },
            edit: { currentPage: 1, totalPages: 1, perPage: 50 }
        };

        // Initialize create form UI function
        function initializeCreateForm() {
            // Clear all fields
            $('#createAoiCoordinates, #createBuildingGids').val('');
            $('#createNorthBound, #createSouthBound, #createEastBound, #createWestBound').val('');
            $('#createBuildingDataset, #createBuildingSearch').val('');
            $('#createBuildingList').html('<p class="text-muted text-center mb-0">Select a dataset to view buildings</p>');
            $('#createSelectedCount').text('0');
            // Clear global selection state
            window.globalBuildingSelection.create.clear();
        }
        
        // Load data on page load
        loadEntitlements();
        loadDatasets();
        
        // Initialize create form UI
        initializeCreateForm();
        
        // Reset create form when modal is opened
        $('#createEntitlementModal').on('show.bs.modal', function() {
            initializeCreateForm();
        });
        
        // Auto-load buildings when create modal opens and DS-BLD is selected
        $('#createType').on('change', function() {
            if ($(this).val() === 'DS-BLD') {
                // Only auto-load if a dataset is already selected
                const selectedDataset = $('#createBuildingDataset').val();
                if (selectedDataset) {
                    setTimeout(() => {
                        loadBuildingsForSelection('create');
                    }, 100);
                }
            }
        });
        
        // Auto-load buildings when edit modal opens
        $('#editEntitlementModal').on('show.bs.modal', function() {
            const entitlementType = $('#editType').val();
            if (entitlementType === 'DS-BLD') {
                // Only auto-load if a dataset is already selected
                const selectedDataset = $('#editBuildingDataset').val();
                if (selectedDataset) {
                    setTimeout(() => {
                        loadBuildingsForSelection('edit');
                    }, 100);
                }
            }
        });

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
                    let options = '<option value="">Select Dataset</option>';
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
                if (entitlement.type === 'DS-AOI') {
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

            // Calculate last page from total and per_page
            const lastPage = Math.ceil(response.total / response.per_page);

            for (let i = 1; i <= lastPage; i++) {
                const active = i === response.current_page ? 'active' : '';
                html += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="changePage(${i})">${i}</a></li>`;
            }

            if (response.current_page < lastPage) {
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
                'DS-BLD': 'info'
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

            if (type === 'DS-AOI') {
                aoiSection.show();
            } else if (type === 'DS-BLD') {
                buildingSection.show();
                // Load datasets for building filtering
                loadDatasetsForBuildings(prefix);
                
                // For create modal, clear any previous selections and reset UI
                if (prefix === 'create') {
                    $(`#${prefix}BuildingDataset`).val('');
                    $(`#${prefix}BuildingSearch`).val('');
                    $(`#${prefix}BuildingGids`).val('');
                    $(`#${prefix}SelectedCount`).text('0');
                }
            }
        };

        // Generate polygon from bounding box coordinates
        window.generatePolygonFromBounds = function(prefix) {
            const north = parseFloat(document.getElementById(prefix + 'NorthBound').value);
            const south = parseFloat(document.getElementById(prefix + 'SouthBound').value);
            const east = parseFloat(document.getElementById(prefix + 'EastBound').value);
            const west = parseFloat(document.getElementById(prefix + 'WestBound').value);
            
            if (isNaN(north) || isNaN(south) || isNaN(east) || isNaN(west)) {
                alert('Please enter valid coordinates for all bounds.');
                return;
            }
            
            if (north <= south) {
                alert('North latitude must be greater than south latitude.');
                return;
            }
            
            if (east <= west) {
                alert('East longitude must be greater than west longitude.');
                return;
            }
            
            // Create polygon coordinates (clockwise)
            const polygon = [
                [west, north],  // Northwest
                [east, north],  // Northeast
                [east, south],  // Southeast
                [west, south],  // Southwest
                [west, north]   // Close polygon
            ];
            
            // Update the coordinates textarea
            const coordinatesField = document.getElementById(prefix + 'AoiCoordinates');
            coordinatesField.value = JSON.stringify(polygon, null, 2);
            
            // Show success message
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Generated!';
            button.classList.remove('btn-info');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-info');
            }, 2000);
        };

        // Load datasets for building filtering
        function loadDatasetsForBuildings(prefix) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: '/api/admin/datasets',
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        const select = $(`#${prefix}BuildingDataset`);
                        select.html('<option value="">Select Dataset</option>');
                        
                        if (response.data) {
                            response.data.forEach(dataset => {
                                const option = `<option value="${dataset.id}">${dataset.name}</option>`;
                                select.append(option);
                            });
                        }
                        
                        // For create modal, ensure buildings are not loaded initially
                        if (prefix === 'create') {
                            $(`#${prefix}BuildingList`).html('<p class="text-muted text-center">Select a dataset to view buildings</p>');
                            $(`#${prefix}SelectedCount`).text('0');
                            $(`#${prefix}BuildingPagination`).hide();
                        }
                        
                        resolve(response);
                    },
                    error: function(error) {
                        console.error('Error loading datasets:', error);
                        reject(error);
                    }
                });
            });
        }



        // Load buildings for selection
        window.loadBuildingsForSelection = function(prefix, preSelectedGids = [], page = 1) {
            const datasetFilter = $(`#${prefix}BuildingDataset`).val();
            const searchTerm = $(`#${prefix}BuildingSearch`).val();
            const buildingList = $(`#${prefix}BuildingList`);
            
            // Initialize global selection state with pre-selected GIDs if provided
            if (preSelectedGids && preSelectedGids.length > 0) {
                window.globalBuildingSelection[prefix].clear();
                preSelectedGids.forEach(gid => {
                    window.globalBuildingSelection[prefix].add(gid);
                });
            }
            
            // Show loading state
            buildingList.html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading buildings...</div>');
            
            let url = '/api/admin/buildings';
            const params = new URLSearchParams();
            
            if (datasetFilter) {
                params.append('dataset_id', datasetFilter);
            }
            if (searchTerm) {
                params.append('search', searchTerm);
            }
            
            // Use different endpoints based on modal type (create vs edit)
            if (prefix === 'edit' && preSelectedGids && preSelectedGids.length > 0) {
                // Edit modal with pre-selected buildings: Use POST endpoint with priority sorting
                const postData = {
                    dataset_id: datasetFilter || null,
                    search: searchTerm || null,
                    priority_gids: preSelectedGids,
                    per_page: window.buildingPagination[prefix].perPage,
                    page: page
                };
                
                $.ajax({
                    url: '/api/admin/buildings/with-priority',
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify(postData),
                    success: function(response) {
                        handleBuildingsResponse(response, prefix, page);
                    },
                    error: function(error) {
                        console.error('Error loading buildings:', error);
                        buildingList.html('<p class="text-danger text-center">Error loading buildings</p>');
                    }
                });
            } else {
                // Create modal OR edit modal without pre-selected buildings: Use GET endpoint for normal pagination
                if (datasetFilter) {
                    params.append('dataset_id', datasetFilter);
                }
                if (searchTerm) {
                    params.append('search', searchTerm);
                }
                
                // Add pagination parameters (Laravel format)
                const perPage = window.buildingPagination[prefix].perPage;
                params.append('per_page', perPage.toString());
                params.append('page', page.toString());
                
                if (params.toString()) {
                    url += '?' + params.toString();
                }
                
                $.ajax({
                    url: url,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        handleBuildingsResponse(response, prefix, page);
                    },
                    error: function(error) {
                        console.error('Error loading buildings:', error);
                        buildingList.html('<p class="text-danger text-center">Error loading buildings</p>');
                    }
                });
            }
        };
        
        // Handle buildings response (extracted to avoid code duplication)
        function handleBuildingsResponse(response, prefix, page) {
            const buildingList = $(`#${prefix}BuildingList`);
            buildingList.html('');
            
            // Update pagination state using Laravel pagination meta
            const meta = response.meta || {};
            const totalCount = meta.total || response.data?.length || 0;
            window.buildingPagination[prefix].currentPage = meta.current_page || page;
            window.buildingPagination[prefix].totalPages = meta.last_page || 1;
            window.buildingPagination[prefix].perPage = meta.per_page || window.buildingPagination[prefix].perPage;
            
            if (response.data && response.data.length > 0) {
                // Buildings are already sorted by the backend (priority GIDs first, then by GID)
                let buildings = response.data;
                const globalSelection = window.globalBuildingSelection[prefix];
                
                buildings.forEach(building => {
                    const isSelected = globalSelection.has(building.gid);
                    const checkboxDiv = $(`
                        <div class="form-check">
                            <input class="form-check-input building-checkbox" type="checkbox" 
                                   value="${building.gid}" id="${prefix}Building${building.id}" ${isSelected ? 'checked' : ''}>
                            <label class="form-check-label" for="${prefix}Building${building.id}">
                                ${building.gid}
                            </label>
                        </div>
                    `);
                    buildingList.append(checkboxDiv);
                });
                
                // Add event listeners to update global selection
                buildingList.find('.building-checkbox').on('change', function() {
                    const gid = $(this).val();
                    if ($(this).is(':checked')) {
                        globalSelection.add(gid);
                    } else {
                        globalSelection.delete(gid);
                    }
                    updateSelectedCount(prefix);
                });
                
                updateSelectedCount(prefix);
                
                // Update pagination controls
                updateBuildingPagination(prefix, totalCount);
            } else {
                buildingList.html('<p class="text-muted text-center">No buildings found</p>');
                $(`#${prefix}BuildingPagination`).hide();
            }
        }

        // Update building pagination controls
        function updateBuildingPagination(prefix, totalCount) {
            const pagination = window.buildingPagination[prefix];
            const paginationDiv = $(`#${prefix}BuildingPagination`);
            
            if (pagination.totalPages <= 1) {
                paginationDiv.hide();
                return;
            }
            
            paginationDiv.show();
            
            // Update page info
            const startItem = ((pagination.currentPage - 1) * pagination.perPage) + 1;
            const endItem = Math.min(pagination.currentPage * pagination.perPage, totalCount);
            $(`#${prefix}BuildingPageInfo`).text(`${startItem}-${endItem} of ${totalCount}`);
            
            // Update buttons
            const prevBtn = $(`#${prefix}BuildingPrevBtn`);
            const nextBtn = $(`#${prefix}BuildingNextBtn`);
            
            prevBtn.prop('disabled', pagination.currentPage <= 1);
            nextBtn.prop('disabled', pagination.currentPage >= pagination.totalPages);
            
            // Update page number
            $(`#${prefix}BuildingCurrentPage`).text(pagination.currentPage);
            $(`#${prefix}BuildingTotalPages`).text(pagination.totalPages);
        }

        // Change building page function
        window.changeBuildingPage = function(prefix, direction) {
            const pagination = window.buildingPagination[prefix];
            let newPage = pagination.currentPage;
            
            if (direction === 'prev' && pagination.currentPage > 1) {
                newPage = pagination.currentPage - 1;
            } else if (direction === 'next' && pagination.currentPage < pagination.totalPages) {
                newPage = pagination.currentPage + 1;
            }
            
            if (newPage !== pagination.currentPage) {
                // Use global selection state (no need to parse from textarea)
                const globalSelection = window.globalBuildingSelection[prefix];
                const preSelectedGids = Array.from(globalSelection);
                
                loadBuildingsForSelection(prefix, preSelectedGids, newPage);
            }
        };



        // Update selected building count
        function updateSelectedCount(prefix) {
            const globalSelection = window.globalBuildingSelection[prefix];
            const countSpan = $(`#${prefix}SelectedCount`);
            countSpan.text(globalSelection.size);
            
            // Update the hidden textarea with selected GIDs from global selection
            const selectedGids = Array.from(globalSelection);
            const gidsField = $(`#${prefix}BuildingGids`);
            gidsField.val(JSON.stringify(selectedGids, null, 2));
        }

        // Select all buildings (on current page)
        window.selectAllBuildings = function(prefix) {
            const checkboxes = $(`#${prefix}BuildingList .building-checkbox`);
            const globalSelection = window.globalBuildingSelection[prefix];
            
            checkboxes.each(function() {
                const gid = $(this).val();
                $(this).prop('checked', true);
                globalSelection.add(gid);
            });
            
            updateSelectedCount(prefix);
        };

        // Clear building selection (on current page only)
        window.clearBuildingSelection = function(prefix) {
            const checkboxes = $(`#${prefix}BuildingList .building-checkbox`);
            const globalSelection = window.globalBuildingSelection[prefix];
            
            // Only remove the GIDs of buildings on the current page from global selection
            checkboxes.each(function() {
                const gid = $(this).val();
                globalSelection.delete(gid);
                $(this).prop('checked', false);
            });
            
            updateSelectedCount(prefix);
        };

        // Note: Method toggle handlers removed as we now only support bounding box for AOI and building selection for DS-BLD

        // Handle dataset filter change for buildings
        $(document).on('change', '#createBuildingDataset, #editBuildingDataset', function() {
            const prefix = $(this).attr('id').replace('BuildingDataset', '');
            const selectedDataset = $(this).val();
            
            // Only load buildings if a valid dataset ID is selected (not empty string or placeholder)
            if (selectedDataset && selectedDataset !== '' && selectedDataset !== 'Select Dataset') {
                // Use global selection state
                const globalSelection = window.globalBuildingSelection[prefix];
                const preSelectedGids = Array.from(globalSelection);
                
                // Auto-load buildings for the selected dataset
                loadBuildingsForSelection(prefix, preSelectedGids, 1);
            } else {
                // Clear the building list when no dataset is selected
                $(`#${prefix}BuildingList`).html('<p class="text-muted text-center">Select a dataset to view buildings</p>');
                $(`#${prefix}SelectedCount`).text('0');
                $(`#${prefix}BuildingPagination`).hide();
                // Clear the hidden field
                $(`#${prefix}BuildingGids`).val('');
            }
        });

        // Handle search input for buildings
        $(document).on('keyup', '#createBuildingSearch, #editBuildingSearch', function() {
            const prefix = $(this).attr('id').replace('BuildingSearch', '');
            const selectedDataset = $(`#${prefix}BuildingDataset`).val();
            
            // Only search buildings if a valid dataset ID is selected (not empty string or placeholder)
            if (selectedDataset && selectedDataset !== '' && selectedDataset !== 'Select Dataset') {
                // Use global selection state
                const globalSelection = window.globalBuildingSelection[prefix];
                const preSelectedGids = Array.from(globalSelection);
                
                // Auto-load buildings with search filter
                loadBuildingsForSelection(prefix, preSelectedGids, 1);
            } else {
                // Clear the building list when no dataset is selected
                $(`#${prefix}BuildingList`).html('<p class="text-muted text-center">Select a dataset to view buildings</p>');
                $(`#${prefix}SelectedCount`).text('0');
            }
        });

        // Reset create modal when closed
        $('#createEntitlementModal').on('hidden.bs.modal', function() {
            $('#createEntitlementForm')[0].reset();
            // Clear all create modal fields and reset UI
            $('#createBuildingDataset, #createBuildingSearch').val('');
            $('#createBuildingGids, #createAoiCoordinates').val('');
            $('#createSelectedCount').text('0');
            $('#createBuildingList').html('<p class="text-muted text-center">Select a dataset to view buildings</p>');
            $('#createBuildingPagination').hide();
            // Clear global selection state
            window.globalBuildingSelection.create.clear();
            toggleEntitlementFields('create');
        });

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
            if (formData.type === 'DS-AOI') {
                const aoiCoordinatesText = $('#createAoiCoordinates').val().trim();
                if (aoiCoordinatesText) {
                    try {
                        formData.aoi_coordinates = JSON.parse(aoiCoordinatesText);
                    } catch (e) {
                        showModalAlert('createEntitlementModal', 'danger', 'Invalid AOI coordinates format. Please check your JSON syntax or generate coordinates from bounds.');
                        return;
                    }
                } else {
                    showModalAlert('createEntitlementModal', 'danger', 'AOI coordinates are required for this entitlement type.');
                    return;
                }
            } else if (formData.type === 'DS-BLD') {
                const buildingGidsText = $('#createBuildingGids').val().trim();
                if (buildingGidsText) {
                    try {
                        formData.building_gids = JSON.parse(buildingGidsText);
                        if (!Array.isArray(formData.building_gids) || formData.building_gids.length === 0) {
                            showModalAlert('createEntitlementModal', 'danger', 'Please select at least one building or enter valid building GIDs.');
                            return;
                        }
                    } catch (e) {
                        showModalAlert('createEntitlementModal', 'danger', 'Invalid building GIDs format. Please check your JSON syntax or select buildings from the list.');
                        return;
                    }
                } else {
                    showModalAlert('createEntitlementModal', 'danger', 'Building GIDs are required for this entitlement type.');
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
                    // Clear all create modal fields and reset UI
                    $('#createBuildingDataset, #createBuildingSearch').val('');
                    $('#createBuildingGids, #createAoiCoordinates').val('');
                    $('#createSelectedCount').text('0');
                    $('#createBuildingList').html('<p class="text-muted text-center">Select a dataset to view buildings</p>');
                    $('#createBuildingPagination').hide();
                    // Clear global selection state
                    window.globalBuildingSelection.create.clear();
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
                    } else if (entitlement.type === 'DS-AOI' && entitlement.aoi_geom) {
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
                    
                    // Clear bounding box fields
                    $('#editNorthBound, #editSouthBound, #editEastBound, #editWestBound').val('');
                    
                    // Clear building selection UI
                    $('#editBuildingDataset').val('');
                    $('#editBuildingSearch').val('');
                    $('#editBuildingList').html('<p class="text-muted text-center mb-0">Loading buildings...</p>');
                    $('#editSelectedCount').text('0');
                    // Clear global selection state
                    window.globalBuildingSelection.edit.clear();

                    // Handle type-specific field values
                    if (entitlement.building_gids) {
                        $('#editBuildingGids').val(JSON.stringify(entitlement.building_gids, null, 2));
                        // Auto-load buildings with pre-selection for DS-BLD type
                        if (entitlement.type === 'DS-BLD') {
                            // Load datasets first, then set the dataset filter and load buildings
                            loadDatasetsForBuildings('edit').then(() => {
                                // Set the dataset filter after datasets are loaded
                                if (entitlement.dataset_id) {
                                    $('#editBuildingDataset').val(entitlement.dataset_id);
                                }
                                // Load buildings with pre-selection
                                setTimeout(() => {
                                    loadBuildingsForSelection('edit', entitlement.building_gids);
                                }, 100);
                            });
                        }
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
            if (formData.type === 'DS-AOI') {
                const aoiCoordinatesText = $('#editAoiCoordinates').val().trim();
                if (aoiCoordinatesText) {
                    try {
                        formData.aoi_coordinates = JSON.parse(aoiCoordinatesText);
                    } catch (e) {
                        showModalAlert('editEntitlementModal', 'danger', 'Invalid AOI coordinates format. Please check your JSON syntax or generate coordinates from bounds.');
                        return;
                    }
                } else {
                    showModalAlert('editEntitlementModal', 'danger', 'AOI coordinates are required for this entitlement type.');
                    return;
                }
            } else if (formData.type === 'DS-BLD') {
                const buildingGidsText = $('#editBuildingGids').val().trim();
                if (buildingGidsText) {
                    try {
                        formData.building_gids = JSON.parse(buildingGidsText);
                        if (!Array.isArray(formData.building_gids) || formData.building_gids.length === 0) {
                            showModalAlert('editEntitlementModal', 'danger', 'Please select at least one building or enter valid building GIDs.');
                            return;
                        }
                    } catch (e) {
                        showModalAlert('editEntitlementModal', 'danger', 'Invalid building GIDs format. Please check your JSON syntax or select buildings from the list.');
                        return;
                    }
                } else {
                    showModalAlert('editEntitlementModal', 'danger', 'Building GIDs are required for this entitlement type.');
                    return;
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