import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import DashboardLayout from '../components/DashboardLayout';
import MapView from '../components/MapView';
import { useAuth } from '../contexts/AuthContext';

export default function Dashboard() {
    const { user } = useAuth();
    const [selectedBuilding, setSelectedBuilding] = useState(null);

    const handleBuildingClick = (building) => {
        setSelectedBuilding(building);
    };

    return (
        <DashboardLayout title="Thermal Analysis Dashboard">
            <div className="space-y-6">
                {/* Welcome Section */}
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <h3 className="text-lg leading-6 font-medium text-gray-900 mb-2">
                            Welcome back, {user?.name}!
                        </h3>
                        <p className="text-sm text-gray-600">
                            Access thermal analysis data and building efficiency insights for your authorized areas.
                        </p>
                        <div className="mt-4 flex items-center space-x-4">
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 capitalize">
                                {user?.role} Account
                            </span>
                            {user?.email_verified_at && (
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Verified
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                {/* Main Content Area - Interactive Map */}
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg leading-6 font-medium text-gray-900">
                                Interactive Thermal Analysis Map
                            </h3>
                            <div className="text-sm text-green-600 flex items-center gap-1">
                                <span className="w-2 h-2 bg-green-500 rounded-full"></span>
                                Phase 3.3 - Active
                            </div>
                        </div>
                        
                        {/* MapLibre GL Map Component */}
                        <div className="h-96 rounded-lg overflow-hidden border border-gray-200">
                            <MapView 
                                onBuildingClick={handleBuildingClick}
                                selectedBuilding={selectedBuilding}
                            />
                        </div>
                    </div>
                </div>

                {/* Building Details and Overview */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2">
                        {selectedBuilding ? (
                            <div className="bg-white overflow-hidden shadow rounded-lg">
                                <div className="px-4 py-5 sm:p-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-lg leading-6 font-medium text-gray-900">
                                            Building Details
                                        </h3>
                                        <button
                                            onClick={() => setSelectedBuilding(null)}
                                            className="text-gray-400 hover:text-gray-600"
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
                                            <h4 className="text-sm font-medium text-gray-900 mb-3">Thermal Analysis</h4>
                                            <dl className="space-y-2">
                                                <div>
                                                    <dt className="text-xs text-gray-500">Thermal Loss Index (TLI)</dt>
                                                    <dd className="text-sm">
                                                        <span 
                                                            className="inline-flex px-2 py-1 text-xs font-medium rounded-full"
                                                            style={{
                                                                backgroundColor: selectedBuilding.tli_color || '#gray',
                                                                color: 'white'
                                                            }}
                                                        >
                                                            {selectedBuilding.thermal_loss_index_tli}
                                                        </span>
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-xs text-gray-500">CO2 Savings Estimate</dt>
                                                    <dd className="text-sm text-gray-900">{selectedBuilding.co2_savings_estimate ? `${selectedBuilding.co2_savings_estimate} kg` : 'N/A'}</dd>
                                                </div>
                                            </dl>
                                        </div>
                                    </div>
                                    
                                    <div className="mt-4 text-xs text-gray-500">
                                        Click on the map to select different buildings
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="bg-white overflow-hidden shadow rounded-lg">
                                <div className="px-4 py-5 sm:p-6">
                                    <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                                        Building Selection
                                    </h3>
                                    <div className="text-center py-8">
                                        <div className="text-4xl text-gray-400 mb-4">🏢</div>
                                        <h4 className="text-lg font-medium text-gray-700 mb-2">Select a Building</h4>
                                        <p className="text-sm text-gray-500">
                                            Click on any building on the map to view its thermal analysis details
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="lg:col-span-1">
                        <div className="bg-white overflow-hidden shadow rounded-lg">
                            <div className="px-4 py-5 sm:p-6">
                                <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Quick Actions
                                </h3>
                                <div className="space-y-3">
                                    <Link
                                        to="/downloads"
                                        className="block w-full bg-blue-50 hover:bg-blue-100 text-blue-700 px-4 py-3 rounded-md text-sm font-medium text-center transition-colors"
                                    >
                                        📥 Download Center
                                    </Link>
                                    <Link
                                        to="/profile"
                                        className="block w-full bg-gray-50 hover:bg-gray-100 text-gray-700 px-4 py-3 rounded-md text-sm font-medium text-center transition-colors"
                                    >
                                        👤 Your Profile
                                    </Link>
                                    {user?.role === 'admin' && (
                                        <a
                                            href="/admin"
                                            className="block w-full bg-purple-50 hover:bg-purple-100 text-purple-700 px-4 py-3 rounded-md text-sm font-medium text-center transition-colors"
                                        >
                                            ⚙️ Admin Panel
                                        </a>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Status Information */}
                <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-green-800">
                                Phase 3.3 Interactive Map Complete
                            </h3>
                            <div className="mt-2 text-sm text-green-700">
                                <p>
                                    ✅ Authentication system implemented<br/>
                                    ✅ Dashboard layout and navigation complete<br/>
                                    ✅ MapLibre GL integration with thermal tiles and building footprints<br/>
                                    ✅ TLI-based building coloring and click interactions<br/>
                                    🔄 Next: Context panel and building list (Phase 3.4)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
} 