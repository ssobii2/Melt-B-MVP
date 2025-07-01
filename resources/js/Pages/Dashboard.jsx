import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import DashboardLayout from '../components/DashboardLayout';
import MapView from '../components/MapView';
import ContextPanel from '../components/ContextPanel';
import { useAuth } from '../contexts/AuthContext';
import { apiClient } from '../utils/api';

export default function Dashboard() {
    const { user } = useAuth();
    const [selectedBuilding, setSelectedBuilding] = useState(null);
    const [highlightedBuilding, setHighlightedBuilding] = useState(null);
    const [buildingStats, setBuildingStats] = useState({
        totalBuildings: '--',
        anomalyBuildings: '--',
        normalBuildings: '--',
        totalCo2Savings: '--',
        avgConfidence: '--'
    });

    useEffect(() => {
        const fetchBuildingStats = async () => {
            try {
                // Fetch building statistics using the dedicated stats endpoint
                const statsResponse = await apiClient.get('/buildings/stats');
                const statsData = statsResponse.data;
                
                setBuildingStats({
                    totalBuildings: (statsData.total_buildings || 0).toLocaleString(),
                    anomalyBuildings: (statsData.anomaly_buildings || 0).toLocaleString(),
                    normalBuildings: (statsData.normal_buildings || 0).toLocaleString(),
                    totalCo2Savings: Math.round(statsData.avg_co2_savings || 0).toLocaleString(),
                    avgConfidence: ((statsData.avg_confidence || 0) * 100).toFixed(1) + '%'
                });
            } catch (error) {
                console.error('Failed to fetch building statistics:', error);
            }
        };

        fetchBuildingStats();
    }, []);

    const handleBuildingClick = (building) => {
        setSelectedBuilding(building);
    };

    const handleBuildingHighlight = (building) => {
        setHighlightedBuilding(building);
    };

    return (
        <DashboardLayout title="Thermal Analysis Dashboard">
            {/* Welcome Header */}
            <div className="bg-white shadow-sm border-b border-gray-200 mb-6">
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
                            {user?.email_verified_at && (
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Verified
                                </span>
                            )}
                            <div className="text-sm text-green-600 flex items-center gap-1">
                                <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                                Phase 3.4 - Active
                            </div>
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
                        <div className="text-sm text-green-600 flex items-center gap-1">
                            <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                            Phase 3.4 - Active
                        </div>
                    </div>
                    
                    {/* MapLibre GL Map Component with Context Panel Overlay */}
                    <div className="relative h-[calc(100vh-18rem)] rounded-lg overflow-hidden border border-gray-200">
                        <MapView 
                            onBuildingClick={handleBuildingClick}
                            selectedBuilding={selectedBuilding}
                            highlightedBuilding={highlightedBuilding}
                        />
                        
                        {/* Context Panel Overlay */}
                        <div className="absolute top-4 right-4 z-10">
                            <ContextPanel 
                                selectedBuilding={selectedBuilding}
                                onBuildingSelect={handleBuildingClick}
                                onBuildingHighlight={handleBuildingHighlight}
                            />
                        </div>
                    </div>
                </div>
            </div>

            {/* Building Details and Quick Actions */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2">
                    {/* Building Details - Shows on top when a building is selected */}
                    {selectedBuilding && (
                        <div className="bg-white overflow-hidden shadow rounded-lg mb-6">
                            <div className="px-4 py-5 sm:p-6">
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-lg leading-6 font-medium text-gray-900">
                                        Building Details
                                    </h3>
                                    <button
                                        onClick={() => setSelectedBuilding(null)}
                                        className="text-gray-400 hover:text-gray-600 cursor-pointer"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h4 className="text-sm font-medium text-gray-900 mb-3">Basic Information</h4>
                                        <dl className="space-y-2">
                                            <div>
                                                <dt className="text-xs text-gray-500">Address</dt>
                                                <dd className="text-sm text-gray-900">{selectedBuilding.address || 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Building Type</dt>
                                                <dd className="text-sm text-gray-900 capitalize">{selectedBuilding.building_type_classification || 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Building ID</dt>
                                                <dd className="text-sm text-gray-900">{selectedBuilding.gid}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                    
                                    <div>
                                        <h4 className="text-sm font-medium text-gray-900 mb-3">Anomaly Analysis</h4>
                                        <dl className="space-y-2">
                                            <div>
                                                <dt className="text-xs text-gray-500">Anomaly Status</dt>
                                                <dd className="text-sm">
                                                    <span 
                                                        className={`inline-flex px-2 py-1 text-xs font-medium rounded-full text-white ${
                                                            selectedBuilding.is_anomaly ? 'bg-red-500' : 'bg-blue-500'
                                                        }`}
                                                    >
                                                        {selectedBuilding.is_anomaly ? 'Anomaly' : 'Normal'}
                                                    </span>
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Average Heat Loss</dt>
                                                <dd className="text-sm text-gray-900">{selectedBuilding.average_heatloss != null && !isNaN(selectedBuilding.average_heatloss) ? Number(selectedBuilding.average_heatloss).toFixed(2) : 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Reference Heat Loss</dt>
                                                <dd className="text-sm text-gray-900">{selectedBuilding.reference_heatloss != null && !isNaN(selectedBuilding.reference_heatloss) ? Number(selectedBuilding.reference_heatloss).toFixed(2) : 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Heat Loss Difference</dt>
                                                <dd className="text-sm text-gray-900">{selectedBuilding.heatloss_difference != null && !isNaN(selectedBuilding.heatloss_difference) ? Number(selectedBuilding.heatloss_difference).toFixed(2) : 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">Confidence Score</dt>
                                                <dd className="text-sm text-gray-900">{selectedBuilding.confidence != null && !isNaN(selectedBuilding.confidence) ? `${(Number(selectedBuilding.confidence) * 100).toFixed(1)}%` : 'N/A'}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-gray-500">CO2 Savings Estimate</dt>
                                                <dd className="text-sm text-gray-900">{selectedBuilding.co2_savings_estimate ? `${selectedBuilding.co2_savings_estimate} kg` : 'N/A'}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

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

                <div className="lg:col-span-1">
                    <div className="bg-white overflow-hidden shadow rounded-lg">
                        <div className="px-4 py-5 sm:p-6 flex flex-col" style={{ minHeight: '16rem' }}>
                            <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                                Quick Actions
                            </h3>
                            <div className="space-y-3 flex-grow flex flex-col justify-center">
                                <Link
                                    to="/downloads"
                                    className="block w-full bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-3 rounded-md text-sm font-medium text-center transition-colors cursor-pointer"
                                >
                                    📥 Download Center
                                </Link>
                                <Link
                                    to="/profile"
                                    className="block w-full bg-gray-50 hover:bg-gray-100 text-gray-700 px-4 py-3 rounded-md text-sm font-medium text-center transition-colors cursor-pointer"
                                >
                                    👤 Your Profile
                                </Link>
                                {user?.role === 'admin' && (
                                    <a
                                        href="/admin"
                                        className="block w-full bg-purple-50 hover:bg-purple-100 text-purple-700 px-4 py-3 rounded-md text-sm font-medium text-center transition-colors cursor-pointer"
                                    >
                                        ⚙️ Admin Panel
                                    </a>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}