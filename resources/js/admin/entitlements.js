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

// AOI Map Editor instances
let createMapEditor = null;
let editMapEditor = null;

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
    async function initializeCreateForm() {
        // Clear all fields
        $('#createAoiCoordinates, #createBuildingGids').val('');
        $('#createBuildingDataset, #createBuildingSearch').val('');
        $('#createBuildingList').html('<p class="text-muted text-center mb-0">Select a dataset to view buildings</p>');
        $('#createSelectedCount').text('0');
        // Clear global selection state
        window.globalBuildingSelection.create.clear();
        
        // Initialize AOI map for create modal
        if (createMapEditor && typeof createMapEditor.clearCurrentAOI === 'function') {
            await createMapEditor.clearCurrentAOI();
        }
        
        // Hide AOI info
        $('#createAoiInfo').hide();
        $('#createClearAOIBtn').prop('disabled', true);
    }
    
    // Load data on page load
    loadEntitlements();
    loadDatasets();
    
    // Initialize create form UI
    initializeCreateForm();
    
    // Reset create form when modal is opened
    $('#createEntitlementModal').on('show.bs.modal', async function() {
        await initializeCreateForm();
        // Do not initialize map immediately - only when AOI type is selected
        // This prevents unnecessary map loading for non-AOI entitlements
    });
    
    // Initialize edit map when modal is opened
    $('#editEntitlementModal').on('show.bs.modal', function() {
        setTimeout(() => {
            initializeAOIMap('edit');
        }, 300);
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
                'Authorization': 'Bearer ' + window.adminToken,
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
                'Authorization': 'Bearer ' + window.adminToken,
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
                'Authorization': 'Bearer ' + window.adminToken,
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
                <td title="${details}">${details}</td>
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
        let html = '<nav><ul class="pagination pagination-sm justify-content-center">';

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
            // Only initialize AOI map if not already initialized
            // This prevents loading the map immediately when modal opens
            if (prefix === 'create') {
                // For create modal, initialize map only when AOI type is selected
                setTimeout(() => {
                    initializeAOIMap(prefix);
                }, 100);
            } else {
                // For edit modal, initialize immediately since we're editing existing AOI
                setTimeout(() => {
                    initializeAOIMap(prefix);
                }, 100);
            }
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
    // Function to extract bounds from polygon coordinates
    // Helper function to extract bounds from coordinates
    function extractBoundsFromCoordinates(coordinates) {
        if (!coordinates || !Array.isArray(coordinates) || coordinates.length === 0) {
            return null;
        }
        
        let minLat = Infinity, maxLat = -Infinity;
        let minLng = Infinity, maxLng = -Infinity;
        
        coordinates.forEach(coord => {
            if (Array.isArray(coord) && coord.length >= 2) {
                const [lng, lat] = coord;
                if (!isNaN(lng) && !isNaN(lat)) {
                    minLat = Math.min(minLat, lat);
                    maxLat = Math.max(maxLat, lat);
                    minLng = Math.min(minLng, lng);
                    maxLng = Math.max(maxLng, lng);
                }
            }
        });
        
        if (minLat === Infinity) {
            return null;
        }
        
        return {
            north: maxLat,
            south: minLat,
            east: maxLng,
            west: minLng
        };
    }
    
    // Function to populate bounding box fields from coordinates
    function populateBoundsFromCoordinates(prefix) {
        const coordinatesField = document.getElementById(prefix + 'AoiCoordinates');
        if (!coordinatesField) return;
        
        const coordinatesText = coordinatesField.value.trim();
        
        if (!coordinatesText) {
            return;
        }
        
        try {
            const coordinates = JSON.parse(coordinatesText);
            const bounds = extractBoundsFromCoordinates(coordinates);
            
            if (bounds) {
                // Only populate bounds if the fields exist in the DOM
                const northBound = document.getElementById(prefix + 'NorthBound');
                const southBound = document.getElementById(prefix + 'SouthBound');
                const eastBound = document.getElementById(prefix + 'EastBound');
                const westBound = document.getElementById(prefix + 'WestBound');
                
                // Check if any bound fields exist before trying to populate them
                if (northBound || southBound || eastBound || westBound) {
                    if (northBound) northBound.value = bounds.north.toFixed(6);
                    if (southBound) southBound.value = bounds.south.toFixed(6);
                    if (eastBound) eastBound.value = bounds.east.toFixed(6);
                    if (westBound) westBound.value = bounds.west.toFixed(6);
                }
            }
        } catch (e) {
            console.warn('Could not parse coordinates for bounds:', e);
        }
    }

    // Function to display polygon coordinates in a readable format
    window.displayPolygonInfo = function(prefix) {
        const coordinatesField = document.getElementById(prefix + 'AoiCoordinates');
        if (!coordinatesField) {
            console.warn('Coordinates field not found for prefix:', prefix);
            return;
        }
        const coordinatesText = coordinatesField.value.trim();
        
        if (!coordinatesText) {
            // Remove existing info if no coordinates
            const existingInfo = document.querySelector(`#${prefix}PolygonInfo`);
            if (existingInfo) {
                existingInfo.remove();
            }
            return;
        }
        
        try {
            const coordinates = JSON.parse(coordinatesText);
            const bounds = extractBoundsFromCoordinates(coordinates);
            
            // Populate bounding box fields (only if they exist)
            populateBoundsFromCoordinates(prefix);
            
            let infoHtml = '<div class="alert alert-info mt-3"><h6><i class="fas fa-info-circle"></i> Generated Polygon Information</h6>';
            
            if (bounds) {
                infoHtml += `<p><strong>Bounding Box:</strong><br>`;
                infoHtml += `North: ${bounds.north.toFixed(6)}°, South: ${bounds.south.toFixed(6)}°<br>`;
                infoHtml += `East: ${bounds.east.toFixed(6)}°, West: ${bounds.west.toFixed(6)}°</p>`;
            }
            
            infoHtml += `<p><strong>Polygon Points:</strong> ${coordinates.length} coordinates</p>`;
            infoHtml += `<details><summary>View Coordinates</summary><pre class="mt-2">${JSON.stringify(coordinates, null, 2)}</pre></details>`;
            infoHtml += '</div>';
            
            // Remove existing info if present
            const existingInfo = document.querySelector(`#${prefix}PolygonInfo`);
            if (existingInfo) {
                existingInfo.remove();
            }
            
            // Add new info after the coordinates field
            const infoDiv = document.createElement('div');
            infoDiv.id = prefix + 'PolygonInfo';
            infoDiv.innerHTML = infoHtml;
            coordinatesField.parentNode.appendChild(infoDiv);
            
        } catch (e) {
            console.warn('Could not parse coordinates for display:', e);
        }
    };

    // Initialize AOI Map Editor
    function initializeAOIMap(prefix) {
        const mapContainer = document.getElementById(prefix + 'AoiMap');
        if (!mapContainer) return;
        
        // Create or get existing map editor
        let mapEditor;
        if (prefix === 'create') {
            if (createMapEditor) {
                createMapEditor.destroy();
            }
            createMapEditor = new AOIMapEditor(mapContainer.id, {
                onAOIChange: (coordinates) => {
                    updateAOICoordinates(prefix, coordinates);
                }
            });
            mapEditor = createMapEditor;
        } else {
            if (editMapEditor) {
                editMapEditor.destroy();
            }
            // Get current entitlement ID for edit mode
            const currentEntitlementId = document.getElementById('editEntitlementId')?.value || null;
            editMapEditor = new AOIMapEditor(mapContainer.id, {
                onAOIChange: (coordinates) => {
                    updateAOICoordinates(prefix, coordinates);
                },
                currentEntitlementId: currentEntitlementId
            });
            mapEditor = editMapEditor;
        }
        
        // Load existing AOI if editing
        if (prefix === 'edit') {
            const existingCoordinatesField = document.getElementById('editAoiCoordinates');
            if (existingCoordinatesField && existingCoordinatesField.value) {
                try {
                    const coords = JSON.parse(existingCoordinatesField.value);
                    if (mapEditor && typeof mapEditor.loadExistingAOI === 'function') {
                        mapEditor.loadExistingAOI(coords);
                    }
                } catch (e) {
                    console.warn('Could not parse existing coordinates:', e);
                }
            }
        }
    }
    
    // Update AOI coordinates and UI
    function updateAOICoordinates(prefix, coordinates) {
        const coordinatesField = document.getElementById(prefix + 'AoiCoordinates');
        if (!coordinatesField) {
            console.warn('Coordinates field not found for prefix:', prefix);
            return;
        }
        const infoDiv = document.getElementById(prefix + 'AoiInfo');
        const clearBtn = document.getElementById(prefix + 'ClearAOIBtn') || document.getElementById(prefix.replace('create', 'clear').replace('edit', 'editClear') + 'AOIBtn');
        
        if (coordinates && coordinates.length > 0) {
            coordinatesField.value = JSON.stringify(coordinates);
            
            // Update info display if elements exist (they were removed in recent updates)
            const coordCountEl = document.getElementById(prefix + 'CoordCount');
            const areaInfoEl = document.getElementById(prefix + 'AreaInfo');
            
            if (coordCountEl) {
                coordCountEl.textContent = coordinates.length;
            }
            
            if (areaInfoEl) {
                // Calculate approximate area (simple polygon area calculation)
                const area = calculatePolygonArea(coordinates);
                areaInfoEl.textContent = formatArea(area);
            }
            
            if (infoDiv) {
                infoDiv.style.display = 'block';
            }
            if (clearBtn) clearBtn.disabled = false;
        } else {
            coordinatesField.value = '';
            if (infoDiv) {
                infoDiv.style.display = 'none';
            }
            if (clearBtn) clearBtn.disabled = true;
        }
    }
    
    // Calculate polygon area (approximate)
    function calculatePolygonArea(coordinates) {
        if (coordinates.length < 3) return 0;
        
        let area = 0;
        for (let i = 0; i < coordinates.length - 1; i++) {
            const [x1, y1] = coordinates[i];
            const [x2, y2] = coordinates[i + 1];
            area += (x1 * y2 - x2 * y1);
        }
        return Math.abs(area / 2);
    }
    
    // Format area for display
    function formatArea(area) {
        if (area < 0.01) {
            return (area * 1000000).toFixed(2) + ' m²';
        } else if (area < 1) {
            return (area * 100).toFixed(2) + ' hectares';
        } else {
            return area.toFixed(2) + ' km²';
        }
    }
    
    // Drawing tool functions
    window.startDrawingRectangle = function(prefix) {
        const mapEditor = prefix === 'create' ? createMapEditor : editMapEditor;
        if (mapEditor) {
            mapEditor.startDrawingRectangle();
        }
    };
    

    
    window.clearAOI = async function(prefix) {
        const mapEditor = prefix === 'create' ? createMapEditor : editMapEditor;
        if (mapEditor && typeof mapEditor.clearCurrentAOI === 'function') {
            const cleared = await mapEditor.clearCurrentAOI();
            // If clearing was cancelled (in edit mode), don't proceed
            if (cleared === false) {
                return false;
            }
        }
        return true;
    };

    // Load datasets for building filtering
    function loadDatasetsForBuildings(prefix) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '/api/admin/datasets',
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + window.adminToken,
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
                    'Authorization': 'Bearer ' + window.adminToken,
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
                    'Authorization': 'Bearer ' + window.adminToken,
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
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid AOI Coordinates',
                        text: 'Invalid AOI coordinates format. Please check your JSON syntax or generate coordinates from bounds.'
                    });
                    return;
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing AOI Coordinates',
                    text: 'AOI coordinates are required for this entitlement type.'
                });
                return;
            }
        } else if (formData.type === 'DS-BLD') {
            const buildingGidsText = $('#createBuildingGids').val().trim();
            if (buildingGidsText) {
                try {
                    formData.building_gids = JSON.parse(buildingGidsText);
                    if (!Array.isArray(formData.building_gids) || formData.building_gids.length === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'No Buildings Selected',
                            text: 'Please select at least one building or enter valid building GIDs.'
                        });
                        return;
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Building GIDs',
                        text: 'Invalid building GIDs format. Please check your JSON syntax or select buildings from the list.'
                    });
                    return;
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Building GIDs',
                    text: 'Building GIDs are required for this entitlement type.'
                });
                return;
            }
        }

        $.ajax({
            url: '/api/admin/entitlements',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + window.adminToken,
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
                toastr.success('Entitlement created successfully!');
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    let errorMessage = 'Please fix the following errors:<br>';
                    Object.keys(errors).forEach(key => {
                        errorMessage += `• ${errors[key][0]}<br>`;
                    });
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Errors',
                        html: errorMessage
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Error creating entitlement'
                    });
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
                'Authorization': 'Bearer ' + window.adminToken,
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
                'Authorization': 'Bearer ' + window.adminToken,
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
                
                // Remove any existing polygon info displays
                const existingEditInfo = document.querySelector('#editPolygonInfo');
                if (existingEditInfo) {
                    existingEditInfo.remove();
                }

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
                let aoiCoordinates = null;
                if (entitlement.aoi_coordinates) {
                    aoiCoordinates = entitlement.aoi_coordinates;
                    $('#editAoiCoordinates').val(JSON.stringify(entitlement.aoi_coordinates));
                    // Display polygon information automatically
                    setTimeout(() => {
                        window.displayPolygonInfo('edit');
                    }, 100);
                } else if (entitlement.aoi_geom && entitlement.aoi_geom.coordinates) {
                    // Extract coordinates from GeoJSON format
                    aoiCoordinates = entitlement.aoi_geom.coordinates[0];
                    $('#editAoiCoordinates').val(JSON.stringify(aoiCoordinates));
                    // Display polygon information automatically
                    setTimeout(() => {
                        window.displayPolygonInfo('edit');
                    }, 100);
                }
                
                // Load AOI into map editor if coordinates exist
                if (aoiCoordinates && entitlement.type === 'DS-AOI') {
                    setTimeout(() => {
                        if (editMapEditor && editMapEditor.loadExistingAOI) {
                            editMapEditor.loadExistingAOI(aoiCoordinates);
                        }
                    }, 500); // Wait for map to be fully initialized
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
        Swal.fire({
            title: 'Delete Entitlement',
            text: `Are you sure you want to delete this ${entitlementType} entitlement?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/api/admin/entitlements/${entitlementId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + window.adminToken,
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        loadEntitlements();
                        toastr.success('Entitlement deleted successfully!');
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Error deleting entitlement');
                    }
                });
            }
        });
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
            
            // Check if coordinates exist in the text field
            if (aoiCoordinatesText) {
                try {
                    formData.aoi_coordinates = JSON.parse(aoiCoordinatesText);
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid AOI Coordinates',
                        text: 'Invalid AOI coordinates format. Please check your JSON syntax or generate coordinates from bounds.'
                    });
                    return;
                }
            } else {
                // Check if map editor has current AOI
                if (editMapEditor && editMapEditor.hasCurrentAOI && editMapEditor.hasCurrentAOI()) {
                    // Get coordinates from map editor
                    const currentCoordinates = editMapEditor.getCurrentCoordinates();
                    if (currentCoordinates && currentCoordinates.length > 0) {
                        formData.aoi_coordinates = currentCoordinates;
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Missing AOI Coordinates',
                            text: 'AOI coordinates are required for this entitlement type. Please draw an AOI on the map.'
                        });
                        return;
                    }
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Missing AOI Coordinates',
                        text: 'AOI coordinates are required for this entitlement type. Please draw an AOI on the map.'
                    });
                    return;
                }
            }
        } else if (formData.type === 'DS-BLD') {
            const buildingGidsText = $('#editBuildingGids').val().trim();
            if (buildingGidsText) {
                try {
                    formData.building_gids = JSON.parse(buildingGidsText);
                    if (!Array.isArray(formData.building_gids) || formData.building_gids.length === 0) {
                        Swal.fire({
                            icon: 'error',
                            title: 'No Buildings Selected',
                            text: 'Please select at least one building or enter valid building GIDs.'
                        });
                        return;
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Building GIDs',
                        text: 'Invalid building GIDs format. Please check your JSON syntax or select buildings from the list.'
                    });
                    return;
                }
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Missing Building GIDs',
                    text: 'Building GIDs are required for this entitlement type.'
                });
                return;
            }
        }

        $.ajax({
            url: `/api/admin/entitlements/${entitlementId}`,
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + window.adminToken,
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
                toastr.success('Entitlement updated successfully!');
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    let errorMessage = 'Please fix the following errors:<br>';
                    Object.keys(errors).forEach(key => {
                        errorMessage += `• ${errors[key][0]}<br>`;
                    });
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Errors',
                        html: errorMessage
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Error updating entitlement'
                    });
                }
            }
        });
    });

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
                'Authorization': 'Bearer ' + window.adminToken,
                'Accept': 'application/json'
            },
            success: function(response) {
                let html = '<option value="">Select a user to assign...</option>';

                // Get current entitlement's users to filter them out
                $.ajax({
                    url: `/api/admin/entitlements/${entitlementId}`,
                    method: 'GET',
                    headers: {
                        'Authorization': 'Bearer ' + window.adminToken,
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
                'Authorization': 'Bearer ' + window.adminToken,
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
            Swal.fire({
                icon: 'warning',
                title: 'No User Selected',
                text: 'Please select a user to assign.'
            });
            return;
        }

        $.ajax({
            url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + window.adminToken,
                'Accept': 'application/json'
            },
            success: function(response) {
                toastr.success('User assigned successfully!');
                loadAvailableUsers(entitlementId);
                loadCurrentEntitlementUsers(entitlementId);
                loadEntitlements(); // Refresh the main table
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Error assigning user');
            }
        });
    };

    window.removeEntitlementUser = function(userId, entitlementId) {
        Swal.fire({
            title: 'Remove User',
            text: 'Are you sure you want to remove this user from the entitlement?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove user!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + window.adminToken,
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        toastr.success('User removed successfully!');
                        loadEntitlements(); // Refresh the main table

                        // If entitlement details modal is open, refresh it
                        if ($('#entitlementDetailsModal').hasClass('show')) {
                            viewEntitlement(entitlementId);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Error removing user');
                    }
                });
            }
        });
    };

    window.removeUserFromEntitlementModal = function(userId, entitlementId) {
        Swal.fire({
            title: 'Remove User',
            text: 'Are you sure you want to remove this user from the entitlement?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, remove user!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/api/admin/users/${userId}/entitlements/${entitlementId}`,
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + window.adminToken,
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        toastr.success('User removed successfully!');
                        loadAvailableUsers(entitlementId);
                        loadCurrentEntitlementUsers(entitlementId);
                        loadEntitlements(); // Refresh the main table
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON?.message || 'Error removing user');
                    }
                });
            }
        });
    };
});