/**
 * AOI Map Editor for Admin Dashboard
 * Interactive map component for creating and editing Area of Interest (AOI) entitlements
 */

class AOIMapEditor {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.map = null;
        this.draw = null;
        this.existingAOIs = [];
        this.currentAOI = null;
        this.onAOIChange = options.onAOIChange || (() => {});
        this.currentEntitlementId = options.currentEntitlementId || null; // ID of entitlement being edited
        this.adminToken = adminTokenHandler.getToken();
        
        // Drawing state
        this.isDrawing = false;
        this.drawingMode = null; // 'rectangle'
        
        // Rectangle drawing state
        this.rectangleStart = null;
        
        this.init();
    }

    async init() {
        await this.initializeMap();
        // Wait for style to be loaded before setting up drawing tools
        if (this.map.isStyleLoaded()) {
            this.setupDrawingTools();
        } else {
            this.map.on('styledata', () => {
                if (this.map.isStyleLoaded()) {
                    this.setupDrawingTools();
                }
            });
        }
        this.setupEventListeners();
        await this.loadExistingAOIs();
    }

    initializeMap() {
        return new Promise((resolve) => {
            this.map = new maplibregl.Map({
                container: this.containerId,
                style: {
                    version: 8,
                    sources: {
                        'osm': {
                            type: 'raster',
                            tiles: [
                                'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
                            ],
                            tileSize: 256,
                            attribution: '© OpenStreetMap contributors'
                        }
                    },
                    layers: [
                        {
                            id: 'osm',
                            type: 'raster',
                            source: 'osm'
                        }
                    ]
                },
                center: [2.3522, 48.8566], // Paris, France
                zoom: 10
            });

            this.map.on('load', () => {
                resolve();
            });
        });
    }

    async loadExistingAOIs() {
        try {
            const response = await fetch('/api/admin/entitlements/all-aois', {
                headers: {
                    'Authorization': `Bearer ${this.adminToken}`,
                    'Accept': 'application/json'
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                this.existingAOIs = data.features || [];
                this.displayExistingAOIs();
            } else {
                const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
                console.error('Failed to load existing AOIs:', response.status, errorData.message);
                if (typeof toastr !== 'undefined') {
                    toastr.error('Failed to load existing AOI boundaries. Please refresh the page.', 'Error');
                }
            }
        } catch (error) {
            console.error('Failed to load existing AOIs:', error);
            if (typeof toastr !== 'undefined') {
                toastr.error('Network error while loading AOI boundaries. Please check your connection.', 'Connection Error');
            }
        }
    }

    displayExistingAOIs() {
        if (this.existingAOIs.length === 0) {
            console.log('No existing AOIs to display');
            return;
        }
        
        if (!this.map || !this.map.isStyleLoaded()) {
            // Add retry limit to prevent infinite loops
            if (!this.displayRetryCount) this.displayRetryCount = 0;
            if (this.displayRetryCount < 50) { // Max 5 seconds of retries
                this.displayRetryCount++;
                this.displayRetryTimeout = setTimeout(() => this.displayExistingAOIs(), 100);
            }
            return;
        }
        
        // Reset retry count on successful execution
        this.displayRetryCount = 0;

        // Filter out current entitlement's AOI if editing
        const otherAOIs = this.currentEntitlementId ? 
            this.existingAOIs.filter(aoi => aoi.properties?.entitlement_id != this.currentEntitlementId) :
            this.existingAOIs;

        // Add source for other existing AOIs
        try {
            if (!this.map.getSource('existing-aois')) {
                this.map.addSource('existing-aois', {
                    type: 'geojson',
                    data: {
                        type: 'FeatureCollection',
                        features: otherAOIs
                    }
                });
            } else {
                // Update existing source
                this.map.getSource('existing-aois').setData({
                    type: 'FeatureCollection',
                    features: otherAOIs
                });
            }
        } catch (error) {
            console.error('Error adding existing AOIs source:', error);
            return;
        }

        // Add fill layer for other existing AOIs (semi-transparent blue)
        if (!this.map.getLayer('existing-aois-fill')) {
            this.map.addLayer({
                id: 'existing-aois-fill',
                type: 'fill',
                source: 'existing-aois',
                paint: {
                    'fill-color': '#3b82f6',
                    'fill-opacity': 0.15
                }
            });
        }

        // Add stroke layer for other existing AOIs
        if (!this.map.getLayer('existing-aois-stroke')) {
            this.map.addLayer({
                id: 'existing-aois-stroke',
                type: 'line',
                source: 'existing-aois',
                paint: {
                    'line-color': '#1e40af',
                    'line-width': 2,
                    'line-opacity': 0.7
                }
            });
        }
    }

    setupDrawingTools() {
        // Add current AOI source and layers (check if they don't already exist)
        if (!this.map.getSource('current-aoi')) {
            this.map.addSource('current-aoi', {
                type: 'geojson',
                data: {
                    type: 'FeatureCollection',
                    features: []
                }
            });
        }

        // Current AOI fill layer (orange/red for editing)
        if (!this.map.getLayer('current-aoi-fill')) {
            this.map.addLayer({
                id: 'current-aoi-fill',
                type: 'fill',
                source: 'current-aoi',
                paint: {
                    'fill-color': '#f97316',
                    'fill-opacity': 0.25
                }
            });
        }

        // Current AOI stroke layer
        if (!this.map.getLayer('current-aoi-stroke')) {
            this.map.addLayer({
                id: 'current-aoi-stroke',
                type: 'line',
                source: 'current-aoi',
                paint: {
                    'line-color': '#ea580c',
                    'line-width': 3
                }
            });
        }
    }

    setupEventListeners() {
        // Map click handler for drawing
        this.map.on('click', (e) => {
            if (this.isDrawing && this.drawingMode === 'rectangle') {
                this.handleRectangleClick(e);
            } else if (!this.isDrawing) {
                // Handle AOI selection for editing
                this.handleAOIClick(e);
            }
        });

        // Mouse move handler for rectangle preview
        this.map.on('mousemove', (e) => {
            if (this.isDrawing && this.drawingMode === 'rectangle' && this.rectangleStart) {
                this.updateRectanglePreview(e);
            }
        });
        
        // Removed cursor styling for AOI elements (drag functionality disabled)
    }

    startDrawingRectangle() {
        this.isDrawing = true;
        this.drawingMode = 'rectangle';
        this.rectangleStart = null;
        this.clearCurrentAOI();
        this.map.getCanvas().style.cursor = 'crosshair';
        
        // Update UI
        this.updateDrawingButtons();
    }



    handleRectangleClick(e) {
        if (!this.rectangleStart) {
            // First click - set start point
            this.rectangleStart = [e.lngLat.lng, e.lngLat.lat];
        } else {
            // Second click - complete rectangle
            const end = [e.lngLat.lng, e.lngLat.lat];
            this.completeRectangle(this.rectangleStart, end);
        }
    }

    updateRectanglePreview(e) {
        if (!this.rectangleStart) return;
        
        const end = [e.lngLat.lng, e.lngLat.lat];
        const rectangle = this.createRectangleGeometry(this.rectangleStart, end);
        
        if (this.map && this.map.isStyleLoaded()) {
            try {
                const source = this.map.getSource('current-aoi');
                if (source) {
                    source.setData({
                        type: 'FeatureCollection',
                        features: [{
                            type: 'Feature',
                            geometry: rectangle
                        }]
                    });
                }
            } catch (error) {
                console.error('Error updating rectangle preview:', error);
            }
        }
    }

    completeRectangle(start, end) {
        const rectangle = this.createRectangleGeometry(start, end);
        this.setCurrentAOI(rectangle);
        this.stopDrawing();
    }

    createRectangleGeometry(start, end) {
        const [startLng, startLat] = start;
        const [endLng, endLat] = end;
        
        const minLng = Math.min(startLng, endLng);
        const maxLng = Math.max(startLng, endLng);
        const minLat = Math.min(startLat, endLat);
        const maxLat = Math.max(startLat, endLat);
        
        return {
            type: 'Polygon',
            coordinates: [[
                [minLng, maxLat], // Northwest
                [maxLng, maxLat], // Northeast
                [maxLng, minLat], // Southeast
                [minLng, minLat], // Southwest
                [minLng, maxLat]  // Close polygon
            ]]
        };
    }




    



    

    


    setCurrentAOI(geometry) {
        this.currentAOI = geometry;
        
        // Update map display
        if (this.map && this.map.isStyleLoaded()) {
            try {
                const source = this.map.getSource('current-aoi');
                if (source) {
                    source.setData({
                        type: 'FeatureCollection',
                        features: [{
                            type: 'Feature',
                            geometry: geometry
                        }]
                    });
                }
            } catch (error) {
                console.error('Error setting current AOI:', error);
            }
        }
        
        // Extract coordinates for the form
        const coordinates = geometry.coordinates[0];
        
        // Notify parent component
        this.onAOIChange(coordinates);
        
        // Fit map to AOI
        this.fitToAOI(geometry);
    }

    fitToAOI(geometry) {
        const coordinates = geometry.coordinates[0];
        const bounds = new maplibregl.LngLatBounds();
        
        coordinates.forEach(coord => {
            bounds.extend(coord);
        });
        
        this.map.fitBounds(bounds, {
            padding: 50,
            maxZoom: 15
        });
    }

    loadExistingAOI(coordinates) {
        if (!coordinates || !Array.isArray(coordinates)) return;
        
        const geometry = {
            type: 'Polygon',
            coordinates: [coordinates]
        };
        
        // Ensure drawing tools are set up before setting current AOI
        if (this.map && this.map.isStyleLoaded() && this.map.getSource('current-aoi')) {
            this.setCurrentAOI(geometry);
        } else {
            // Wait for drawing tools to be ready with retry limit
            if (!this.checkAndSetRetryCount) this.checkAndSetRetryCount = 0;
            
            const checkAndSet = () => {
                if (this.map && this.map.isStyleLoaded() && this.map.getSource('current-aoi')) {
                    this.setCurrentAOI(geometry);
                    this.checkAndSetRetryCount = 0; // Reset on success
                } else if (this.checkAndSetRetryCount < 50) { // Max 5 seconds of retries
                    this.checkAndSetRetryCount++;
                    this.checkAndSetTimeout = setTimeout(checkAndSet, 100);
                } else {
                    console.error('Failed to load existing AOI: map or drawing tools not ready after maximum retries');
                    this.checkAndSetRetryCount = 0; // Reset for future attempts
                }
            };
            checkAndSet();
        }
    }

    async clearCurrentAOI() {
        // In edit mode, warn user before clearing AOI
        if (this.currentEntitlementId && this.currentAOI) {
            const result = await Swal.fire({
                title: 'Remove Current AOI?',
                text: 'Are you sure you want to remove the current AOI? You must draw a new one before saving.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, remove it!',
                cancelButtonText: 'Cancel'
            });
            
            if (!result.isConfirmed) {
                return false;
            }
        }
        
        this.currentAOI = null;
        
        if (this.map && this.map.isStyleLoaded()) {
            try {
                const source = this.map.getSource('current-aoi');
                if (source) {
                    source.setData({
                        type: 'FeatureCollection',
                        features: []
                    });
                }
            } catch (error) {
                console.error('Error clearing current AOI:', error);
            }
        }
        
        this.onAOIChange(null);
        return true;
    }

    stopDrawing() {
        this.isDrawing = false;
        this.drawingMode = null;
        this.rectangleStart = null;
        this.map.getCanvas().style.cursor = '';
        
        this.updateDrawingButtons();
    }

    updateDrawingButtons() {
        const rectangleBtn = document.getElementById('drawRectangleBtn');
        const clearBtn = document.getElementById('clearAOIBtn');
        
        if (rectangleBtn) {
            rectangleBtn.classList.toggle('btn-success', this.isDrawing && this.drawingMode === 'rectangle');
            rectangleBtn.classList.toggle('btn-outline-primary', !(this.isDrawing && this.drawingMode === 'rectangle'));
            rectangleBtn.disabled = this.isDrawing && this.drawingMode !== 'rectangle';
        }
        
        if (clearBtn) {
            clearBtn.disabled = !this.currentAOI && !this.isDrawing;
        }
    }

    handleAOIClick(e) {
        // Drag functionality disabled - AOI interaction removed
        return;
    }
    
    // Drag functionality removed for simplicity

    getCurrentCoordinates() {
        return this.currentAOI ? this.currentAOI.coordinates[0] : null;
    }

    hasCurrentAOI() {
        return this.currentAOI !== null;
    }

    destroy() {
        // Clear any pending timeouts
        if (this.displayRetryTimeout) {
            clearTimeout(this.displayRetryTimeout);
            this.displayRetryTimeout = null;
        }
        
        if (this.checkAndSetTimeout) {
            clearTimeout(this.checkAndSetTimeout);
            this.checkAndSetTimeout = null;
        }
        
        // Remove event listeners
        if (this.map) {
            this.map.off('click');
            this.map.off('mousemove');
            this.map.off('styledata');
            
            // Remove map instance
            this.map.remove();
            this.map = null;
        }
        
        // Clear references
        this.existingAOIs = [];
        this.currentAOI = null;
        this.rectangleStart = null;
        this.onAOIChange = null;
        this.adminToken = null;
        
        // Reset state
        this.isDrawing = false;
        this.drawingMode = null;
        this.displayRetryCount = 0;
        this.checkAndSetRetryCount = 0;
    }
}

// Global instance for use in entitlements.js
window.AOIMapEditor = AOIMapEditor;
