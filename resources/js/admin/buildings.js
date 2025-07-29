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
            anomaly_filter: $('#anomalyFilter').val()
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
                'Authorization': 'Bearer ' + adminTokenHandler.getToken(),
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
                'Authorization': 'Bearer ' + adminTokenHandler.getToken(),
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
                'Authorization': 'Bearer ' + adminTokenHandler.getToken(),
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