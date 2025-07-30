import React, { useEffect, useRef, useState } from 'react';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { apiClient } from '../utils/api';
import { useAuth } from '../contexts/AuthContext';

const MapView = ({ onBuildingClick, selectedBuilding, highlightedBuilding, onMapBoundsChange }) => {
    const { isAdmin } = useAuth();
    const mapContainer = useRef(null);
    const map = useRef(null);
    const [buildingsData, setBuildingsData] = useState(null);
    const [datasets, setDatasets] = useState([]);
    const [selectedDataset, setSelectedDataset] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [allBuildings, setAllBuildings] = useState([]); // Store all accessible buildings
    const [isZooming, setIsZooming] = useState(false); // Track zooming state
    const [aoiEntitlements, setAoiEntitlements] = useState([]); // Store AOI entitlements
    const [showAoiBoundaries, setShowAoiBoundaries] = useState(true); // Toggle AOI visibility
    const [tileLayers, setTileLayers] = useState([]); // Available tile layers
    const [visibleTileLayers, setVisibleTileLayers] = useState(new Set()); // Currently visible tile layers
    const [showTileLayers, setShowTileLayers] = useState(false); // Toggle all tile layers visibility

    // Anomaly color helper function
    const getAnomalyColor = (isAnomaly) => {
        return isAnomaly ? '#ef4444' : '#3b82f6'; // red for anomaly, blue for normal
    };

    // Calculate optimal initial view based on building density
    const calculateOptimalInitialView = (buildings) => {
        if (!buildings || buildings.length === 0) {
            return {
                center: [2.3522, 48.8566], // Paris, France
                zoom: 13
            };
        }

        if (buildings.length === 1) {
            // If only one building, center on it
            const building = buildings[0];
            if (building.geometry) {
                let center;
                if (building.geometry.type === 'Polygon') {
                    const coords = building.geometry.coordinates[0];
                    if (coords && coords.length > 0) {
                        let lon = 0, lat = 0;
                        coords.forEach(coord => {
                            lon += coord[0];
                            lat += coord[1];
                        });
                        center = [lon / coords.length, lat / coords.length];
                    }
                } else if (building.geometry.type === 'Point') {
                    center = building.geometry.coordinates;
                }
                if (center) {
                    return { center, zoom: 15 };
                }
            }
        }

        // Extract coordinates from all buildings
        const coordinates = [];
        buildings.forEach(building => {
            if (building.geometry) {
                if (building.geometry.type === 'Polygon') {
                    const coords = building.geometry.coordinates[0];
                    if (coords && coords.length > 0) {
                        let lon = 0, lat = 0;
                        coords.forEach(coord => {
                            lon += coord[0];
                            lat += coord[1];
                        });
                        coordinates.push([lon / coords.length, lat / coords.length]);
                    }
                } else if (building.geometry.type === 'Point') {
                    coordinates.push(building.geometry.coordinates);
                }
            }
        });

        if (coordinates.length === 0) {
            return {
                center: [2.3522, 48.8566], // Paris, France
                zoom: 13
            };
        }

        // Find the area with highest building density within ~100m view
        // Using a grid-based approach to find clusters
        const gridSize = 0.001; // Roughly 100m at mid-latitudes
        const clusters = {};

        coordinates.forEach(coord => {
            const gridX = Math.floor(coord[0] / gridSize);
            const gridY = Math.floor(coord[1] / gridSize);
            const key = `${gridX},${gridY}`;
            
            if (!clusters[key]) {
                clusters[key] = {
                    count: 0,
                    centerX: gridX * gridSize + gridSize / 2,
                    centerY: gridY * gridSize + gridSize / 2,
                    buildings: []
                };
            }
            clusters[key].count++;
            clusters[key].buildings.push(coord);
        });

        // Find cluster with most buildings
        let bestCluster = null;
        let maxCount = 0;

        Object.values(clusters).forEach(cluster => {
            if (cluster.count > maxCount) {
                maxCount = cluster.count;
                bestCluster = cluster;
            }
        });

        if (bestCluster) {
            return {
                center: [bestCluster.centerX, bestCluster.centerY],
                zoom: 15 // Good zoom level for ~100m view
            };
        }

        // Fallback to first building
        return {
            center: coordinates[0],
            zoom: 15
        };
    };

    // Fit map to bounds of all available buildings
    const fitMapToBuildings = (buildings) => {
        if (!map.current || !buildings || buildings.length === 0) return;
        // Extract all coordinates
        const coordinates = [];
        buildings.forEach(building => {
            if (building.geometry) {
                if (building.geometry.type === 'Polygon') {
                    const coords = building.geometry.coordinates[0];
                    coords.forEach(coord => coordinates.push(coord));
                } else if (building.geometry.type === 'Point') {
                    coordinates.push(building.geometry.coordinates);
                }
            }
        });
        if (coordinates.length === 0) return;
        // Calculate bounds
        const lons = coordinates.map(c => c[0]);
        const lats = coordinates.map(c => c[1]);
        const sw = [Math.min(...lons), Math.min(...lats)];
        const ne = [Math.max(...lons), Math.max(...lats)];
        map.current.fitBounds([sw, ne], { padding: 60, duration: 1200 });
    };

    // Load all accessible buildings for initial view calculation
    const loadAllAccessibleBuildings = async () => {
        try {
            const endpoint = isAdmin ? '/admin/buildings' : '/buildings';
            const response = await apiClient.get(endpoint, {
                params: {
                    per_page: 1000, // Get more buildings for better distribution calculation
                    include_geometry: 1
                }
            });
            const buildings = response.data.data || [];
            setAllBuildings(buildings);
            return buildings;
        } catch (error) {
            console.error('Failed to load all buildings:', error);
            return [];
        }
    };

    // Initialize map
    useEffect(() => {
        if (map.current) return; // Initialize map only once

        const initializeMap = async () => {
            // Load all buildings first to calculate optimal initial view
            const allBuildings = await loadAllAccessibleBuildings();
            const initialView = calculateOptimalInitialView(allBuildings);

            map.current = new maplibregl.Map({
                container: mapContainer.current,
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
                            id: 'background',
                            type: 'background',
                            paint: {
                                'background-color': '#f8f9fa'
                            }
                        },
                        {
                            id: 'osm',
                            type: 'raster',
                            source: 'osm',
                            minzoom: 0,
                            maxzoom: 22
                        }
                    ]
                },
                center: initialView.center,
                zoom: initialView.zoom,
                maxZoom: 20,
                attributionControl: false // Remove default attribution control
            });

            // Add custom attribution control (collapsed by default)
            map.current.addControl(new maplibregl.AttributionControl({
                compact: true,
                customAttribution: 'MapLibre',
                collapsed: true
            }));

            // Add navigation control without zoom buttons
            map.current.addControl(new maplibregl.NavigationControl({
                showZoom: false,
                showCompass: false
            }), 'top-right');

            // Add scale control
            map.current.addControl(new maplibregl.ScaleControl(), 'bottom-left');

            map.current.on('load', () => {
                setIsLoading(false);
                loadInitialData();

                // Add moveend handler here after map is loaded
                const handleMoveEnd = () => {
                    loadBuildingData();
                    // Notify parent component about bounds change for real-time stats
                    if (onMapBoundsChange) {
                        const bounds = map.current.getBounds();
                        onMapBoundsChange(bounds);
                    }
                };
                map.current.on('moveend', handleMoveEnd);
            });
        };

        initializeMap();

        // Cleanup function
        return () => {
            if (map.current) {
                map.current.remove();
                map.current = null;
            }
        };
    }, []);

    // Zoom to building when selectedBuilding changes
    useEffect(() => {
        if (selectedBuilding && map.current) {
            zoomToBuilding(selectedBuilding);
        }
    }, [selectedBuilding]);

    // Load initial data when map is ready
    const loadInitialData = async () => {
        try {
            // Load user entitlements to get available datasets
            const entitlementsResponse = await apiClient.get('/me/entitlements');
            const entitlements = entitlementsResponse.data.entitlements || [];
            
            // Extract AOI entitlements with geometry
            const aoiEntitlements = entitlements.filter(entitlement => 
                entitlement.type === 'DS-AOI' && entitlement.aoi_geom
            );
            setAoiEntitlements(aoiEntitlements);
            
            // Extract unique datasets from entitlements
            const availableDatasets = entitlements
                .map(entitlement => entitlement.dataset)
                .filter((dataset, index, self) => 
                    dataset && self.findIndex(d => d.id === dataset.id) === index
                );
            
            // Sort by id to ensure consistent order (older datasets first)
            availableDatasets.sort((a,b) => a.id - b.id);
            setDatasets(availableDatasets);

            // Set the first available dataset as selected (for context purposes)
            if (availableDatasets.length > 0) {
                setSelectedDataset(availableDatasets[0]);
            }

            // Load tile layers
            loadTileLayers();

            // Load building footprint data for current view
            loadBuildingData();
            
            // Add AOI boundaries if any exist
            if (aoiEntitlements.length > 0) {
                addAoiBoundaries(aoiEntitlements);
            }
        } catch (error) {
            console.error('Failed to load initial map data:', error);
        }
    };



    // Load available tile layers
    const loadTileLayers = async () => {
        try {
            const response = await apiClient.get('/tiles/layers');
            const layers = response.data.layers || [];
            setTileLayers(layers);
        } catch (error) {
            console.error('Failed to load tile layers:', error);
        }
    };

    // Load building footprint data (bounds-based for performance)
    const loadBuildingData = async () => {
        try {
            // Get current map bounds for filtering
            const bounds = map.current.getBounds();
            
            // Use admin endpoint if user is admin to see all buildings without entitlement filtering
            const endpoint = isAdmin ? '/admin/buildings/within/bounds' : '/buildings/within/bounds';
            const response = await apiClient.get(endpoint, {
                params: {
                    north: bounds.getNorth(),
                    south: bounds.getSouth(),
                    east: bounds.getEast(),
                    west: bounds.getWest(),
                    limit: 1000,
                    include_geometry: 1
                }
            });

            const buildings = response.data.data;
            setBuildingsData(buildings);
            
            // Update allBuildings with new data (merge to avoid duplicates)
            setAllBuildings(prevAll => {
                const existingGids = new Set(prevAll.map(b => b.gid));
                const newBuildings = buildings.filter(b => !existingGids.has(b.gid));
                return [...prevAll, ...newBuildings];
            });
            
            addBuildingLayer(buildings);
        } catch (error) {
            console.error('Failed to load building data:', error);
        }
    };

    // Add building footprint layer to map
    const addBuildingLayer = (buildings) => {
        if (!map.current || !buildings) return;

        // Remove existing building layers
        if (map.current.getLayer('buildings-fill')) {
            map.current.removeLayer('buildings-fill');
        }
        if (map.current.getLayer('buildings-stroke')) {
            map.current.removeLayer('buildings-stroke');
        }
        if (map.current.getSource('buildings')) {
            map.current.removeSource('buildings');
        }

        // Convert buildings to GeoJSON
        const geojson = {
            type: 'FeatureCollection',
            features: buildings.map(building => ({
                type: 'Feature',
                geometry: building.geometry || {
                    type: 'Point',
                    coordinates: [21.6255, 47.5316] // Fallback coordinates
                },
                properties: {
                    gid: building.gid,
                    is_anomaly: building.is_anomaly,
                    average_heatloss: building.average_heatloss,
                    reference_heatloss: building.reference_heatloss,
                    heatloss_difference: building.heatloss_difference,
                    confidence: building.confidence,
                    address: building.address,
                    type: building.building_type_classification,
                    co2_savings: building.co2_savings_estimate
                }
            }))
        };

        // Add buildings source
        map.current.addSource('buildings', {
            type: 'geojson',
            data: geojson
        });

        // Add building fill layer with anomaly-based coloring
        map.current.addLayer({
            id: 'buildings-fill',
            type: 'fill',
            source: 'buildings',
            paint: {
                'fill-color': [
                    'case',
                    ['==', ['get', 'is_anomaly'], true],
                    '#ef4444', // Red for anomalies
                    ['==', ['get', 'is_anomaly'], false],
                    '#3b82f6', // Blue for normal buildings
                    '#cccccc' // Default gray for buildings without anomaly data
                ],
                'fill-opacity': 0.7
            }
        });

        // Add building stroke layer
        map.current.addLayer({
            id: 'buildings-stroke',
            type: 'line',
            source: 'buildings',
            paint: {
                'line-color': '#ffffff',
                'line-width': 1,
                'line-opacity': 0.8
            }
        });

        // Add click handler for buildings
        map.current.on('click', 'buildings-fill', (e) => {
            if (e.features.length > 0) {
                const feature = e.features[0];
                const building = buildings.find(b => b.gid === feature.properties.gid);
                if (building && onBuildingClick) {
                    onBuildingClick(building);
                }
            }
        });

        // Change cursor on hover
        map.current.on('mouseenter', 'buildings-fill', () => {
            map.current.getCanvas().style.cursor = 'pointer';
        });

        map.current.on('mouseleave', 'buildings-fill', () => {
            map.current.getCanvas().style.cursor = '';
        });
    };

    // Highlight selected and hovered buildings
    useEffect(() => {
        if (!map.current) return;

        // Remove existing highlights
        ['selected-building', 'highlighted-building'].forEach(layerId => {
            if (map.current.getLayer(layerId)) {
                map.current.removeLayer(layerId);
            }
            if (map.current.getSource(layerId)) {
                map.current.removeSource(layerId);
            }
        });

        // Add highlight for selected building (red border)
        if (selectedBuilding) {
            const building = buildingsData?.find(b => b.gid === selectedBuilding.gid);
            if (building && building.geometry) {
                map.current.addSource('selected-building', {
                    type: 'geojson',
                    data: {
                        type: 'Feature',
                        geometry: building.geometry,
                        properties: building
                    }
                });

                map.current.addLayer({
                    id: 'selected-building',
                    type: 'line',
                    source: 'selected-building',
                    paint: {
                        'line-color': '#ff0000',
                        'line-width': 3,
                        'line-opacity': 1
                    }
                });
            }
        }

        // Add highlight for hovered building (blue border)
        if (highlightedBuilding && highlightedBuilding.gid !== selectedBuilding?.gid) {
            const building = buildingsData?.find(b => b.gid === highlightedBuilding.gid);
            if (building && building.geometry) {
                map.current.addSource('highlighted-building', {
                    type: 'geojson',
                    data: {
                        type: 'Feature',
                        geometry: building.geometry,
                        properties: building
                    }
                });

                map.current.addLayer({
                    id: 'highlighted-building',
                    type: 'line',
                    source: 'highlighted-building',
                    paint: {
                        'line-color': '#3b82f6',
                        'line-width': 2,
                        'line-opacity': 0.8
                    }
                });
            }
        }
    }, [selectedBuilding, highlightedBuilding, buildingsData]);

    // Add AOI boundary layers to map
    const addAoiBoundaries = (aoiEntitlements) => {
        if (!map.current || !aoiEntitlements || aoiEntitlements.length === 0) {
            return;
        }

        // Remove existing AOI layers
        if (map.current.getLayer('aoi-boundaries-fill')) {
            map.current.removeLayer('aoi-boundaries-fill');
        }
        if (map.current.getLayer('aoi-boundaries-stroke')) {
            map.current.removeLayer('aoi-boundaries-stroke');
        }
        if (map.current.getSource('aoi-boundaries')) {
            map.current.removeSource('aoi-boundaries');
        }

        // Convert AOI entitlements to GeoJSON
        const aoiFeatures = aoiEntitlements.map((entitlement, index) => {
            // Convert aoi_geom to proper GeoJSON format
            let geometry;
            if (entitlement.aoi_geom && entitlement.aoi_geom.type === 'Polygon') {
                geometry = entitlement.aoi_geom;
            } else if (entitlement.aoi_geom && entitlement.aoi_geom.coordinates) {
                geometry = {
                    type: 'Polygon',
                    coordinates: entitlement.aoi_geom.coordinates
                };
            } else {
                return null;
            }

            const feature = {
                type: 'Feature',
                geometry: geometry,
                properties: {
                    entitlement_id: entitlement.id,
                    dataset_name: entitlement.dataset?.name || 'Unknown Dataset',
                    index: index
                }
            };
            return feature;
        }).filter(feature => feature !== null);

        if (aoiFeatures.length === 0) return;

        const aoiGeojson = {
            type: 'FeatureCollection',
            features: aoiFeatures
        };

        // Add AOI source
        map.current.addSource('aoi-boundaries', {
            type: 'geojson',
            data: aoiGeojson
        });

        // Add AOI fill layer (semi-transparent)
        map.current.addLayer({
            id: 'aoi-boundaries-fill',
            type: 'fill',
            source: 'aoi-boundaries',
            paint: {
                'fill-color': [
                    'case',
                    ['==', ['%', ['get', 'index'], 5], 0], '#10b981', // Green
                    ['==', ['%', ['get', 'index'], 5], 1], '#f59e0b', // Orange  
                    ['==', ['%', ['get', 'index'], 5], 2], '#8b5cf6', // Purple
                    ['==', ['%', ['get', 'index'], 5], 3], '#ef4444', // Red
                    '#06b6d4' // Cyan
                ],
                'fill-opacity': 0.15
            }
        });

        // Add AOI stroke layer
        map.current.addLayer({
            id: 'aoi-boundaries-stroke',
            type: 'line',
            source: 'aoi-boundaries',
            paint: {
                'line-color': [
                    'case',
                    ['==', ['%', ['get', 'index'], 5], 0], '#10b981', // Green
                    ['==', ['%', ['get', 'index'], 5], 1], '#f59e0b', // Orange
                    ['==', ['%', ['get', 'index'], 5], 2], '#8b5cf6', // Purple
                    ['==', ['%', ['get', 'index'], 5], 3], '#ef4444', // Red
                    '#06b6d4' // Cyan
                ],
                'line-width': 2,
                'line-opacity': 0.8
            }
        });
    };

    // Toggle AOI boundary visibility
    useEffect(() => {
        if (!map.current) return;

        const layers = ['aoi-boundaries-fill', 'aoi-boundaries-stroke'];
        layers.forEach(layerId => {
            if (map.current.getLayer(layerId)) {
                map.current.setLayoutProperty(layerId, 'visibility', showAoiBoundaries ? 'visible' : 'none');
            }
        });
    }, [showAoiBoundaries]);

    // Zoom to AOI boundaries
    const zoomToAoiBoundaries = () => {
        if (!map.current || !aoiEntitlements || aoiEntitlements.length === 0) {
            return;
        }
        
        // Calculate bounds of all AOI polygons
        let minLng = Infinity, minLat = Infinity, maxLng = -Infinity, maxLat = -Infinity;
        let hasValidBounds = false;

        aoiEntitlements.forEach((entitlement) => {
            const geometry = entitlement.aoi_geom;
            if (geometry && geometry.coordinates && geometry.coordinates[0]) {
                geometry.coordinates[0].forEach(coord => {
                    if (coord && coord.length >= 2) {
                        const [lng, lat] = coord;
                        if (!isNaN(lng) && !isNaN(lat)) {
                            minLng = Math.min(minLng, lng);
                            minLat = Math.min(minLat, lat);
                            maxLng = Math.max(maxLng, lng);
                            maxLat = Math.max(maxLat, lat);
                            hasValidBounds = true;
                        }
                    }
                });
            }
        });

        if (hasValidBounds) {
            // Add some padding to the bounds
            const padding = 0.001; // Adjust as needed
            const bounds = [
                [minLng - padding, minLat - padding], // Southwest
                [maxLng + padding, maxLat + padding]  // Northeast
            ];
            
            map.current.fitBounds(bounds, {
                padding: 50,
                duration: 2000
            });
            
            // Ensure AOI boundaries are visible when zooming to them
            if (!showAoiBoundaries) {
                setShowAoiBoundaries(true);
            }
        }
    };

    // Add tile layer to map
    const addTileLayer = (layerName) => {
        if (!map.current) return;

        const sourceId = `tiles-${layerName}`;
        const layerId = `tiles-${layerName}-layer`;

        // Remove existing layer if it exists
        if (map.current.getLayer(layerId)) {
            map.current.removeLayer(layerId);
        }
        if (map.current.getSource(sourceId)) {
            map.current.removeSource(sourceId);
        }

        // Add tile source
        map.current.addSource(sourceId, {
            type: 'raster',
            tiles: [`${window.location.origin}/api/tiles/${layerName}/{z}/{x}/{y}.png`],
            tileSize: 256,
            minzoom: 11,
            maxzoom: 14,
            scheme: 'xyz', // Explicitly specify XYZ scheme
            // Handle missing tiles gracefully
            bounds: [2.23962759265300, 48.81837244150312, 2.34634047084554, 48.87724759682047] // Paris bounds from tilemapresource.xml
        });

        // Add tile layer
        map.current.addLayer({
            id: layerId,
            type: 'raster',
            source: sourceId,
            paint: {
                'raster-opacity': 0.8
            }
        });

        // Update visible layers state
        setVisibleTileLayers(prev => new Set([...prev, layerName]));
    };

    // Remove tile layer from map
    const removeTileLayer = (layerName) => {
        if (!map.current) return;

        const sourceId = `tiles-${layerName}`;
        const layerId = `tiles-${layerName}-layer`;

        if (map.current.getLayer(layerId)) {
            map.current.removeLayer(layerId);
        }
        if (map.current.getSource(sourceId)) {
            map.current.removeSource(sourceId);
        }

        // Update visible layers state
        setVisibleTileLayers(prev => {
            const newSet = new Set(prev);
            newSet.delete(layerName);
            return newSet;
        });
    };

    // Toggle all tile layers visibility
    const toggleAllTileLayers = () => {
        if (showTileLayers) {
            // Hide all tile layers
            tileLayers.forEach(layer => {
                removeTileLayer(layer.name);
            });
            setShowTileLayers(false);
        } else {
            // Show all tile layers
            tileLayers.forEach(layer => {
                addTileLayer(layer.name);
            });
            setShowTileLayers(true);
        }
    };

    // Zoom to tile boundaries
    const zoomToTileBoundaries = () => {
        if (!map.current || !tileLayers || tileLayers.length === 0) {
            return;
        }
        
        // Use the bounds from tilemapresource.xml for Paris thermal data
        const bounds = [
            [2.23962759265300, 48.81837244150312], // Southwest
            [2.34634047084554, 48.87724759682047]  // Northeast
        ];
        
        map.current.fitBounds(bounds, {
            padding: 50,
            duration: 2000
        });
        
        // Ensure tile layers are visible when zooming to them
        if (!showTileLayers) {
            setShowTileLayers(true);
            tileLayers.forEach(layer => {
                addTileLayer(layer.name);
            });
        }
    };

    // Zoom to a specific building (slower animation)
    const zoomToBuilding = (building) => {
        if (!map.current || !building) return;
        setIsZooming(true);
        let center;
        if (building.geometry) {
            if (building.geometry.type === 'Polygon') {
                const coords = building.geometry.coordinates[0];
                if (coords && coords.length > 0) {
                    let lon = 0, lat = 0;
                    coords.forEach(coord => {
                        lon += coord[0];
                        lat += coord[1];
                    });
                    center = [lon / coords.length, lat / coords.length];
                }
            } else if (building.geometry.type === 'Point') {
                center = building.geometry.coordinates;
            }
        } else {
            const buildingWithGeometry = allBuildings.find(b => b.gid === building.gid) || 
                                        buildingsData?.find(b => b.gid === building.gid);
            if (buildingWithGeometry && buildingWithGeometry.geometry) {
                setIsZooming(false);
                return zoomToBuilding(buildingWithGeometry);
            } else {
                setIsZooming(false);
                return;
            }
        }
        if (center && center.length === 2 && !isNaN(center[0]) && !isNaN(center[1])) {
            map.current.flyTo({
                center: center,
                zoom: 17,
                duration: 2500 // Slower animation
            });
            setTimeout(() => setIsZooming(false), 2500);
        } else {
            setIsZooming(false);
        }
    };

    return (
        <div className="relative w-full h-full">
            {isLoading && (
                <div className="absolute inset-0 bg-gray-100 flex items-center justify-center z-10">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
                        <p className="text-sm text-gray-600">Loading map...</p>
                    </div>
                </div>
            )}
            
            <div ref={mapContainer} className="w-full h-full" />
            
            {/* Map Legend */}
            <div className="absolute top-4 left-4 bg-white rounded-lg shadow-lg p-3 text-xs max-w-48">
                <h4 className="font-semibold mb-2">Anomaly Detection</h4>
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-3 rounded" style={{ backgroundColor: '#ef4444' }}></div>
                        <span>Anomaly</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-3 rounded" style={{ backgroundColor: '#3b82f6' }}></div>
                        <span>Normal</span>
                    </div>
                </div>

                {/* Tile Layers Section */}
                {tileLayers.length > 0 && (
                    <div className="mt-2 pt-2 border-t border-gray-200">
                        <div className="flex items-center justify-between mb-1">
                            <h5 className="font-semibold">Tile Layers</h5>
                            <div className="flex gap-1">
                                <button
                                    onClick={() => zoomToTileBoundaries()}
                                    className="px-2 py-1 text-xs rounded bg-green-500 text-white hover:bg-green-600 cursor-pointer transition-colors duration-200"
                                    title="Zoom to tile boundaries"
                                >
                                    📍
                                </button>
                                <button
                                    onClick={() => toggleAllTileLayers()}
                                    className={`px-2 py-1 text-xs rounded cursor-pointer transition-colors duration-200 ${
                                        showTileLayers 
                                            ? 'bg-blue-500 text-white hover:bg-blue-600' 
                                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                    }`}
                                >
                                    {showTileLayers ? 'Hide' : 'Show'}
                                </button>
                            </div>
                        </div>
                        <div className="space-y-1">
                            {tileLayers.map((layer) => (
                                <div key={layer.name} className="flex items-center gap-2">
                                    <div className="w-4 h-3 rounded" style={{ backgroundColor: '#8b5cf6' }}></div>
                                    <span className="truncate">{layer.display_name}</span>
                                </div>
                            ))}
                        </div>
                        <p className="text-gray-600 text-xs mt-1">
                            {tileLayers.length} tile layer{tileLayers.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                )}
                
                {/* AOI Boundaries Section */}
                {aoiEntitlements.length > 0 && (
                    <div className="mt-2 pt-2 border-t border-gray-200">
                        <div className="flex items-center justify-between mb-1">
                            <h5 className="font-semibold">AOI Boundaries</h5>
                            <div className="flex gap-1">
                                <button
                                    onClick={() => zoomToAoiBoundaries()}
                                    className="px-2 py-1 text-xs rounded bg-green-500 text-white hover:bg-green-600 cursor-pointer transition-colors duration-200"
                                    title="Zoom to AOI boundaries"
                                >
                                    📍
                                </button>
                                <button
                                    onClick={() => setShowAoiBoundaries(!showAoiBoundaries)}
                                    className={`px-2 py-1 text-xs rounded cursor-pointer transition-colors duration-200 ${
                                        showAoiBoundaries 
                                            ? 'bg-blue-500 text-white hover:bg-blue-600' 
                                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                    }`}
                                >
                                    {showAoiBoundaries ? 'Hide' : 'Show'}
                                </button>
                            </div>
                        </div>
                        <div className="space-y-1">
                            {aoiEntitlements.map((entitlement, index) => {
                                const colors = [
                                    { border: '#10b981', fill: 'rgba(16, 185, 129, 0.15)' },
                                    { border: '#f59e0b', fill: 'rgba(245, 158, 11, 0.15)' },
                                    { border: '#8b5cf6', fill: 'rgba(139, 92, 246, 0.15)' },
                                    { border: '#ef4444', fill: 'rgba(239, 68, 68, 0.15)' },
                                    { border: '#06b6d4', fill: 'rgba(6, 182, 212, 0.15)' }
                                ];
                                const color = colors[index % colors.length];
                                return (
                                    <div key={entitlement.id} className="flex items-center gap-2">
                                        <div 
                                            className="w-4 h-3 rounded border-2" 
                                            style={{ borderColor: color.border, backgroundColor: color.fill }}
                                        ></div>
                                        <span className="truncate">{entitlement.dataset?.name || `AOI ${index + 1}`}</span>
                                    </div>
                                );
                            })}
                        </div>
                        <p className="text-gray-600 text-xs mt-1">
                            {aoiEntitlements.length} AOI entitlement{aoiEntitlements.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                )}
                
                {selectedDataset && (
                    <div className="mt-2 pt-2 border-t border-gray-200">
                        <p className="text-gray-600 text-xs">
                            Dataset: {selectedDataset.name}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default MapView;