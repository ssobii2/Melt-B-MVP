import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import DashboardLayout from '../components/DashboardLayout';
import MapView from '../components/MapView';
import ContextPanel from '../components/ContextPanel';
import BuildingDetailsDrawer from '../components/BuildingDetailsDrawer';
import { useAuth } from '../contexts/AuthContext';
import { apiClient } from '../utils/api';
import { Toaster, toast } from 'react-hot-toast';

export default function Dashboard() {
    const { user } = useAuth();
    const [selectedBuilding, setSelectedBuilding] = useState(null);
    const [highlightedBuilding, setHighlightedBuilding] = useState(null);
    const [currentMapBounds, setCurrentMapBounds] = useState(null);
    const [hasVisibleTiles, setHasVisibleTiles] = useState(false);
    const [buildingStats, setBuildingStats] = useState({
        totalBuildings: '--',
        anomalyBuildings: '--',
        normalBuildings: '--',
        totalCo2Savings: '--',
        avgConfidence: '--'
    });

    const fetchBuildingStats = async (bounds = null) => {
        try {
            let statsResponse;
            
            if (bounds) {
                // Fetch building statistics for current map bounds
                statsResponse = await apiClient.get('/buildings/stats/within-bounds', {
                    params: {
                        north: bounds.getNorth(),
                        south: bounds.getSouth(),
                        east: bounds.getEast(),
                        west: bounds.getWest()
                    }
                });
            } else {
                // Fetch overall building statistics
                statsResponse = await apiClient.get('/buildings/stats');
            }
            
            const statsData = statsResponse.data;
            
            setBuildingStats({
                totalBuildings: (statsData.total_buildings || 0).toLocaleString(),
                anomalyBuildings: (statsData.anomaly_buildings || 0).toLocaleString(),
                normalBuildings: (statsData.normal_buildings || 0).toLocaleString(),
                totalCo2Savings: Math.round(statsData.total_co2_savings || statsData.avg_co2_savings || 0).toLocaleString(),
                avgConfidence: ((statsData.avg_confidence || 0) * 100).toFixed(1) + '%'
            });
        } catch (error) {
            console.error('Failed to fetch building statistics:', error);
        }
    };

    useEffect(() => {
        fetchBuildingStats();
    }, []);

    // Update stats when map bounds change
    useEffect(() => {
        if (currentMapBounds) {
            fetchBuildingStats(currentMapBounds);
        }
    }, [currentMapBounds]);

    const handleMapBoundsChange = (bounds) => {
        setCurrentMapBounds(bounds);
    };

    const handleBuildingClick = (building) => {
        setSelectedBuilding(building);
    };

    const handleBuildingHighlight = (building) => {
        setHighlightedBuilding(building);
    };

    const handleTileVisibilityChange = (hasVisibleTiles) => {
        setHasVisibleTiles(hasVisibleTiles);
    };

    const handleBuildingExplorerToggleAttempt = () => {
        toast.error('Please hide all thermal tiles to access the Building Explorer');
    };

    return (
        <DashboardLayout title="Thermal Analysis Dashboard">
            <Toaster position="top-right" />
            
            {/* Welcome Header */}
            <div className="bg-white shadow-sm border-b border-gray-200 rounded-lg mb-6">
                <div className="px-6 py-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">
                                Welcome back, {user?.name}!
                            </h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Access thermal analysis data and building efficiency insights for your authorized areas.
                            </p>
                        </div>
                        <div className="flex items-center space-x-3">
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 capitalize">
                                {user?.role} Account
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {/* Main Content Area - Full Width Map */}
            <div className="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div className="px-4 py-5 sm:p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-lg leading-6 font-medium text-gray-900">
                            Interactive Thermal Analysis Map
                        </h3>

                    </div>
                    
                    {/* MapLibre GL Map Component with Context Panel Overlay */}
                    <div className="relative h-[calc(100vh-18rem)] rounded-lg overflow-hidden border border-gray-200">
                        <MapView 
                            onBuildingClick={handleBuildingClick}
                            selectedBuilding={selectedBuilding}
                            highlightedBuilding={highlightedBuilding}
                            onMapBoundsChange={handleMapBoundsChange}
                            onTileVisibilityChange={handleTileVisibilityChange}
                        />
                        
                        {/* Context Panel Overlay */}
                        <div className="absolute top-4 right-4 z-10">
                            <ContextPanel 
                                selectedBuilding={selectedBuilding}
                                onBuildingSelect={handleBuildingClick}
                                onBuildingHighlight={handleBuildingHighlight}
                                isDisabled={hasVisibleTiles}
                                onToggleAttempt={handleBuildingExplorerToggleAttempt}
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Building Details and Data Overview */}
            <div className="space-y-6">
                {/* Building Details Drawer */}
                <BuildingDetailsDrawer 
                    selectedBuilding={selectedBuilding} 
                    onClose={() => setSelectedBuilding(null)}
                />

                {/* Building Data Overview - Always visible */}
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Building Data Overview
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div className="text-center">
                                <div className="text-2xl font-bold text-blue-600">{buildingStats.totalBuildings}</div>
                                <div className="text-sm text-gray-500">Total Buildings</div>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-bold text-red-600">{buildingStats.anomalyBuildings}</div>
                                <div className="text-sm text-gray-500">Anomaly Buildings</div>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-bold text-blue-600">{buildingStats.normalBuildings}</div>
                                <div className="text-sm text-gray-500">Normal Buildings</div>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-bold text-green-600">{buildingStats.totalCo2Savings}</div>
                                <div className="text-sm text-gray-500">CO2 Savings Potential (kg)</div>
                            </div>
                            <div className="text-center">
                                <div className="text-2xl font-bold text-purple-600">{buildingStats.avgConfidence}</div>
                                <div className="text-sm text-gray-500">Average Confidence</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}