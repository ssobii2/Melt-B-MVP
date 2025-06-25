import React, { useEffect, useRef, useState } from 'react';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { apiClient } from '../utils/api';
import Cookies from 'js-cookie';

const MapView = ({ onBuildingClick, selectedBuilding }) => {
    const mapContainer = useRef(null);
    const map = useRef(null);
    const [buildingsData, setBuildingsData] = useState(null);
    const [datasets, setDatasets] = useState([]);
    const [selectedDataset, setSelectedDataset] = useState(null);
    const [isLoading, setIsLoading] = useState(true);

    // Initialize map
    useEffect(() => {
        if (map.current) return; // Initialize map only once

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
            center: [21.6255, 47.5316], // Debrecen, Hungary (where test data is located)
            zoom: 13,
            maxZoom: 20
        });

        // Add navigation controls
        map.current.addControl(new maplibregl.NavigationControl(), 'top-right');

        // Add scale control
        map.current.addControl(new maplibregl.ScaleControl(), 'bottom-left');

        map.current.on('load', () => {
            setIsLoading(false);
            loadInitialData();
        });

        // Cleanup function
        return () => {
            if (map.current) {
                map.current.remove();
                map.current = null;
            }
        };
    }, []);

    // Load initial data when map is ready
    const loadInitialData = async () => {
        try {
            // Load user entitlements to get available datasets
            const entitlementsResponse = await apiClient.get('/me/entitlements');
            const entitlements = entitlementsResponse.data.entitlements || [];
            
            // Extract unique datasets from entitlements
            const availableDatasets = entitlements
                .map(entitlement => entitlement.dataset)
                .filter((dataset, index, self) => 
                    dataset && self.findIndex(d => d.id === dataset.id) === index
                );
            
            setDatasets(availableDatasets);

            // Find a thermal raster dataset for tile layer
            const thermalDataset = availableDatasets.find(d => 
                d.data_type === 'thermal_raster' || d.data_type === 'thermal_rasters'
            );
            
            if (thermalDataset) {
                setSelectedDataset(thermalDataset);
                addThermalTileLayer(thermalDataset.id);
            }

            // Load building footprint data
            loadBuildingData();
        } catch (error) {
            console.error('Failed to load initial map data:', error);
        }
    };

    // Add thermal tile layer to map
    const addThermalTileLayer = (datasetId) => {
        if (!map.current || !datasetId) return;

        const token = Cookies.get('auth_token');
        
        // Add thermal raster source with token as query parameter
        map.current.addSource('thermal-tiles', {
            type: 'raster',
            tiles: [`/api/tiles/${datasetId}/{z}/{x}/{y}.png?token=${token}`],
            tileSize: 256,
            minzoom: 10, // Only show at zoom level 10+ (1:10,000 scale)
            maxzoom: 18
        });

        // Add thermal layer (only visible at high zoom levels)
        map.current.addLayer({
            id: 'thermal-layer',
            type: 'raster',
            source: 'thermal-tiles',
            layout: {
                visibility: 'visible'
            },
            paint: {
                'raster-opacity': 0.6
            },
            minzoom: 10 // Only show when zoomed in enough (≥1:10,000)
        });
    };

    // Load building footprint data
    const loadBuildingData = async () => {
        try {
            // Get current map bounds for filtering
            const bounds = map.current.getBounds();
            
            const response = await apiClient.get('/buildings/within/bounds', {
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
                    tli: building.thermal_loss_index_tli,
                    address: building.address,
                    type: building.building_type_classification,
                    co2_savings: building.co2_savings_estimate,
                    tli_color: building.tli_color
                }
            }))
        };

        // Add buildings source
        map.current.addSource('buildings', {
            type: 'geojson',
            data: geojson
        });

        // Add building fill layer with TLI-based coloring
        map.current.addLayer({
            id: 'buildings-fill',
            type: 'fill',
            source: 'buildings',
            paint: {
                'fill-color': [
                    'case',
                    ['has', 'tli_color'],
                    ['get', 'tli_color'],
                    '#cccccc' // Default gray for buildings without TLI
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

    // Reload building data when map moves
    useEffect(() => {
        if (!map.current) return;

        const handleMoveEnd = () => {
            loadBuildingData();
        };

        map.current.on('moveend', handleMoveEnd);

        return () => {
            if (map.current) {
                map.current.off('moveend', handleMoveEnd);
            }
        };
    }, []);

    // Highlight selected building
    useEffect(() => {
        if (!map.current || !selectedBuilding) return;

        // Remove existing highlight
        if (map.current.getLayer('selected-building')) {
            map.current.removeLayer('selected-building');
        }
        if (map.current.getSource('selected-building')) {
            map.current.removeSource('selected-building');
        }

        // Add highlight for selected building
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
    }, [selectedBuilding, buildingsData]);

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
                <h4 className="font-semibold mb-2">Thermal Loss Index (TLI)</h4>
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-3 bg-green-500 rounded"></div>
                        <span>Low (0-30)</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-3 bg-yellow-500 rounded"></div>
                        <span>Medium (30-60)</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-3 bg-orange-500 rounded"></div>
                        <span>High (60-90)</span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="w-4 h-3 bg-red-500 rounded"></div>
                        <span>Very High (90+)</span>
                    </div>
                </div>
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