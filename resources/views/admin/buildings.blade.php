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
                        <select id="tliRangeFilter" class="form-control">
                            <option value="">All TLI Ranges</option>
                            <option value="0-20">Low (0-20)</option>
                            <option value="21-40">Medium-Low (21-40)</option>
                            <option value="41-60">Medium (41-60)</option>
                            <option value="61-80">Medium-High (61-80)</option>
                            <option value="81-100">High (81-100)</option>
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
                                <th>TLI</th>
                                <th>Classification</th>
                                <th>CO2 Savings</th>
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
                                <td><strong>TLI:</strong></td>
                                <td>
                                    <span id="detailTli" class="badge">-</span>
                                </td>
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
<style>
    .tli-badge-0-20 {
        background-color: #00ff00 !important;
        color: #000;
    }

    .tli-badge-21-40 {
        background-color: #80ff00 !important;
        color: #000;
    }

    .tli-badge-41-60 {
        background-color: #ffff00 !important;
        color: #000;
    }

    .tli-badge-61-80 {
        background-color: #ff8000 !important;
        color: #fff;
    }

    .tli-badge-81-100 {
        background-color: #ff0000 !important;
        color: #fff;
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

        $('#datasetFilter, #tliRangeFilter, #perPage').on('change', function() {
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
                tli_range: $('#tliRangeFilter').val()
            };

            lastSearchParams = {
                ...params
            };

            $('#buildingsTableBody').html('<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading buildings...</td></tr>');

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
                    updatePagination(response);
                    $('#buildingCount').text(`${response.total} buildings`);
                },
                error: function(xhr) {
                    $('#buildingsTableBody').html('<tr><td colspan="8" class="text-center text-danger">Error loading buildings: ' +
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
                html = '<tr><td colspan="8" class="text-center text-muted">No buildings found</td></tr>';
            } else {
                buildings.forEach(function(building) {
                    const tliClass = getTliClass(building.thermal_loss_index_tli);
                    const analyzedDate = formatDate(building.last_analyzed_at);

                    html += `
                    <tr>
                        <td><code>${building.gid}</code></td>
                        <td>${building.address || 'N/A'}</td>
                        <td>
                            <span class="badge ${tliClass}">${building.thermal_loss_index_tli || 'N/A'}</span>
                        </td>
                        <td>${building.building_type_classification || 'N/A'}</td>
                        <td>${building.co2_savings_estimate ? building.co2_savings_estimate + ' kg' : 'N/A'}</td>
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

        function getTliClass(tli) {
            if (!tli) return 'badge-secondary';
            if (tli >= 81) return 'tli-badge-81-100';
            if (tli >= 61) return 'tli-badge-61-80';
            if (tli >= 41) return 'tli-badge-41-60';
            if (tli >= 21) return 'tli-badge-21-40';
            return 'tli-badge-0-20';
        }

        function updatePagination(response) {
            const pagination = $('#buildingsPagination');
            pagination.empty();

            if (response.last_page > 1) {
                let paginationHtml = '<nav><ul class="pagination pagination-sm">';

                // Previous page
                if (response.current_page > 1) {
                    paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${response.current_page - 1})">Previous</a></li>`;
                }

                // Page numbers
                const start = Math.max(1, response.current_page - 2);
                const end = Math.min(response.last_page, response.current_page + 2);

                for (let i = start; i <= end; i++) {
                    const active = i === response.current_page ? 'active' : '';
                    paginationHtml += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="goToPage(${i})">${i}</a></li>`;
                }

                // Next page
                if (response.current_page < response.last_page) {
                    paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${response.current_page + 1})">Next</a></li>`;
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
                    $('#detailTli').removeClass().addClass('badge ' + getTliClass(building.thermal_loss_index_tli))
                        .text(building.thermal_loss_index_tli || 'N/A');
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
                    alert('Failed to load building details: ' + (xhr.responseJSON?.message || 'Unknown error'));
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