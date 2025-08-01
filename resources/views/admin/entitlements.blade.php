@extends('admin.layouts.app')

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
                            <option value="TILES">TILES (Tile Layers)</option>
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
                                    <option value="TILES">TILES (Tile Layers)</option>
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
                                <!-- Interactive Map Editor -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="mb-0">Draw Area of Interest <span class="text-danger">*</span></label>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" id="drawRectangleBtn" class="btn btn-outline-primary" onclick="startDrawingRectangle('create')">
                                                <i class="fas fa-square"></i> Draw Rectangle
                                            </button>
                                            <button type="button" id="clearAOIBtn" class="btn btn-outline-danger" onclick="clearAOI('create')" disabled>
                                                <i class="fas fa-trash"></i> Clear
                                            </button>
                                        </div>
                                    </div>
                                    <div id="createAoiMap" style="height: 400px; border: 1px solid #dee2e6; border-radius: 0.25rem;"></div>
                                    <small class="form-text text-muted mt-2">
                                        <i class="fas fa-info-circle"></i> 
                                        Use the drawing tool above to create your Area of Interest. 
                                        <strong>Rectangle:</strong> Click twice to define opposite corners.
                                        Existing AOIs are shown in blue for reference.
                                    </small>
                                </div>
                                
                                <!-- Hidden field to store generated coordinates -->
                                <input type="hidden" id="createAoiCoordinates" name="aoi_coordinates">
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
                                    <div class="col-md-6">
                                        <div class="form-group">
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

                    <!-- Tile Layers (for TILES) -->
                    <div id="createTileSection" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-layer-group"></i> Tile Layer Selection
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Available Tile Layers</label>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Select Tile Layers (<span id="createSelectedTileCount">0</span> selected) <span class="text-danger">*</span></span>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="selectAllTileLayers('create')">
                                                Select All
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearTileLayerSelection('create')">
                                                Clear All
                                            </button>
                                        </div>
                                    </div>
                                    <div id="createTileLayerList" class="border rounded p-2 bg-light" style="max-height: 300px; overflow-y: auto;">
                                        <p class="text-muted text-center mb-0">Loading tile layers...</p>
                                    </div>
                                </div>
                                <!-- Hidden field to store selected tile layers -->
                                <input type="hidden" id="createTileLayers" name="tile_layers">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createDownloadFormats">Download Formats</label>
                                <div class="gap-2">
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
                                <input type="text" class="form-control" id="editType" name="type" readonly style="background-color: #f8f9fa; cursor: not-allowed;">
                                <small class="text-muted">Entitlement type cannot be changed. Delete and create new entitlement to change type.</small>
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
                                <!-- Interactive Map Editor -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="mb-0">Draw Area of Interest <span class="text-danger">*</span></label>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" id="editDrawRectangleBtn" class="btn btn-outline-primary" onclick="startDrawingRectangle('edit')">
                                                <i class="fas fa-square"></i> Draw Rectangle
                                            </button>
                                            <button type="button" id="editClearAOIBtn" class="btn btn-outline-danger" onclick="clearAOI('edit')" disabled>
                                                <i class="fas fa-trash"></i> Clear
                                            </button>
                                        </div>
                                    </div>
                                    <div id="editAoiMap" style="height: 400px; border: 1px solid #dee2e6; border-radius: 0.25rem;"></div>
                                    <small class="form-text text-muted mt-2">
                                        <i class="fas fa-info-circle"></i> 
                                        Use the drawing tool above to modify your Area of Interest. 
                                        <strong>Rectangle:</strong> Click twice to define opposite corners.
                                        Existing AOIs are shown in blue for reference.
                                    </small>
                                </div>
                                
                                <!-- Hidden field to store generated coordinates -->
                                <input type="hidden" id="editAoiCoordinates" name="aoi_coordinates">
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
                                    <div class="col-md-6">
                                        <!-- Additional column for future use -->
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

                    <!-- Tile Layers (for TILES) -->
                    <div id="editTileSection" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-layer-group"></i> Tile Layer Selection
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Available Tile Layers</label>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Select Tile Layers (<span id="editSelectedTileCount">0</span> selected) <span class="text-danger">*</span></span>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="selectAllTileLayers('edit')">
                                                Select All
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearTileLayerSelection('edit')">
                                                Clear All
                                            </button>
                                        </div>
                                    </div>
                                    <div id="editTileLayerList" class="border rounded p-2 bg-light" style="max-height: 300px; overflow-y: auto;">
                                        <p class="text-muted text-center mb-0">Loading tile layers...</p>
                                    </div>
                                </div>
                                <!-- Hidden field to store selected tile layers -->
                                <input type="hidden" id="editTileLayers" name="tile_layers">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Download Formats</label>
                                <div class="gap-2" id="editDownloadFormats">
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

@push('js')
@vite(['resources/js/admin/aoi-map-editor.js', 'resources/js/admin/entitlements.js'])
@endpush