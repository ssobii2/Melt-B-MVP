@extends('adminlte::page')

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

@section('css')
@include('admin.partials.toastr-config')
<style>
    .anomaly-badge {
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

    .badge {
        font-size: 0.8em;
    }
</style>
@stop

@section('js')
@include('admin.partials.toastr-config')
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
        // Simple placeholder implementation
        let currentPage = 1;
        let lastSearchParams = {};

        // Load initial data
        loadBuildings();
        loadDatasets();

        // Event handlers
        $('#searchBuildings').on('input', debounce(function() {
            currentPage = 1;
            loadBuildings();
        }, 500));

        $('#datasetFilter, #anomalyFilter, #perPage').on('change', function() {
            currentPage = 1;
            loadBuildings();
        });

        $('#refreshBuildings').on('click', function() {
            loadBuildings();
        });

        $('#exportBuildings').on('click', function() {
            exportBuildings();
        });

        function loadBuildings() {
            const params = {
                page: currentPage,
                per_page: $('#perPage').val(),
                search: $('#searchBuildings').val(),
                dataset_id: $('#datasetFilter').val(),
                is_anomaly: $('#anomalyFilter').val()
            };

            lastSearchParams = {
                ...params
            };

            $('#buildingsTableBody').html('<tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading buildings...</td></tr>');

            $.ajax({
                url: '/api/admin/buildings',
                method: 'GET',
                data: params,
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    displayBuildings(response.data);
                    updatePagination(response.meta);
                    $('#buildingCount').text(`${response.meta.total} buildings`);
                },
                error: function(xhr) {
                    $('#buildingsTableBody').html('<tr><td colspan="9" class="text-center text-danger">Error loading buildings: ' +
                        (xhr.responseJSON?.message || 'Unknown error') + '</td></tr>');
                }
            });
        }

        function loadDatasets() {
            $.ajax({
                url: '/api/admin/datasets',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(response) {
                    const datasetSelect = $('#datasetFilter');
                    datasetSelect.find('option:not(:first)').remove();

                    response.data.forEach(function(dataset) {
                        datasetSelect.append(`<option value="${dataset.id}">${dataset.name}</option>`);
                    });
                },
                error: function(xhr) {
                    console.error('Error loading datasets:', xhr.responseJSON?.message || 'Unknown error');
                }
            });
        }

        function displayBuildings(buildings) {
            let html = '';

            if (buildings.length === 0) {
                html = '<tr><td colspan="9" class="text-center text-muted">No buildings found</td></tr>';
            } else {
                buildings.forEach(function(building) {
                    const analyzedDate = formatDate(building.last_analyzed_at);
                    const heatLoss = building.average_heatloss != null && !isNaN(building.average_heatloss) ? Number(building.average_heatloss).toFixed(2) : 'N/A';
                    const anomalyStatus = building.is_anomaly ? 
                        '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Anomaly</span>' : 
                        '<span class="badge badge-success"><i class="fas fa-check"></i> Normal</span>';

                    html += `
                    <tr>
                        <td><code>${building.gid}</code></td>
                        <td>${building.address || 'N/A'}</td>
                        <td>${building.building_type_classification || 'N/A'}</td>
                        <td>${building.co2_savings_estimate ? building.co2_savings_estimate + ' kg' : 'N/A'}</td>
                        <td>${heatLoss}</td>
                        <td>${anomalyStatus}</td>
                        <td>${building.dataset ? building.dataset.name : 'N/A'}</td>
                        <td>${analyzedDate}</td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewBuildingDetails('${building.gid}')">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                `;
                });
            }

            $('#buildingsTableBody').html(html);
        }



        function updatePagination(meta) {
            const pagination = $('#buildingsPagination');
            pagination.empty();

            if (meta.last_page > 1) {
                let paginationHtml = '<nav><ul class="pagination pagination-sm">';

                // Previous page
                if (meta.current_page > 1) {
                    paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${meta.current_page - 1})">Previous</a></li>`;
                }

                // Page numbers
                const start = Math.max(1, meta.current_page - 2);
                const end = Math.min(meta.last_page, meta.current_page + 2);

                for (let i = start; i <= end; i++) {
                    const active = i === meta.current_page ? 'active' : '';
                    paginationHtml += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="goToPage(${i})">${i}</a></li>`;
                }

                // Next page
                if (meta.current_page < meta.last_page) {
                    paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${meta.current_page + 1})">Next</a></li>`;
                }

                paginationHtml += '</ul></nav>';
                pagination.html(paginationHtml);
            }
        }

        window.goToPage = function(page) {
            currentPage = page;
            loadBuildings();
        };

        window.viewBuildingDetails = function(gid) {
            $.ajax({
                url: `/api/admin/buildings/${gid}`,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + '{{ session("admin_token") }}',
                    'Accept': 'application/json'
                },
                success: function(building) {
                    // Populate modal with building data
                    $('#detailGid').text(building.gid);
                    $('#detailAddress').text(building.address || 'N/A');
                    $('#detailHeatLoss').text(building.average_heatloss != null && !isNaN(building.average_heatloss) ? Number(building.average_heatloss).toFixed(2) : 'N/A');
                    $('#detailReferenceHeatLoss').text(building.reference_heatloss != null && !isNaN(building.reference_heatloss) ? Number(building.reference_heatloss).toFixed(2) : 'N/A');
                    $('#detailHeatLossDifference').text(building.heatloss_difference != null && !isNaN(building.heatloss_difference) ? Number(building.heatloss_difference).toFixed(2) : 'N/A');
                    $('#detailAbsHeatLossDifference').text(building.abs_heatloss_difference != null && !isNaN(building.abs_heatloss_difference) ? Number(building.abs_heatloss_difference).toFixed(2) : 'N/A');
                    $('#detailThreshold').text(building.threshold != null && !isNaN(building.threshold) ? Number(building.threshold).toFixed(2) : 'N/A');
                    
                    const anomalyStatus = building.is_anomaly ? 
                        '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Anomaly</span>' : 
                        '<span class="badge badge-success"><i class="fas fa-check"></i> Normal</span>';
                    $('#detailAnomalyStatus').html(anomalyStatus);
                    
                    $('#detailConfidence').text(building.confidence != null && !isNaN(building.confidence) ? (Number(building.confidence) * 100).toFixed(1) + '%' : 'N/A');
                    $('#detailClassification').text(building.building_type_classification || 'N/A');
                    $('#detailCo2').text(building.co2_savings_estimate ? building.co2_savings_estimate + ' kg' : 'N/A');
                    $('#detailDataset').text(building.dataset ? building.dataset.name : 'N/A');
                    $('#detailCadastral').text(building.cadastral_reference || 'N/A');
                    $('#detailOwner').text(building.owner_operator_details || 'N/A');
                    $('#detailAnalyzed').text(formatDateTime(building.last_analyzed_at));
                    $('#detailCreated').text(formatDateTime(building.created_at));

                    $('#buildingDetailsModal').modal('show');
                },
                error: function(xhr) {
                    toastr.error('Failed to load building details: ' + (xhr.responseJSON?.message || 'Unknown error'));
                }
            });
        };

        function exportBuildings() {
            const params = new URLSearchParams(lastSearchParams);
            params.delete('page'); // Export all, not just current page

            window.open(`/api/admin/buildings/export?${params.toString()}`, '_blank');
        }

        function debounce(func, delay) {
            let timeoutId;
            return function(...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func.apply(this, args), delay);
            };
        }
    });
</script>
@stop