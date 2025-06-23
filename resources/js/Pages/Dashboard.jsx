import React from 'react';
import { Link } from 'react-router-dom';
import DashboardLayout from '../components/DashboardLayout';
import { useAuth } from '../contexts/AuthContext';

export default function Dashboard() {
    const { user } = useAuth();

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

                {/* Main Content Area - This will contain the map in Phase 3.3 */}
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-lg leading-6 font-medium text-gray-900">
                                Interactive Map
                            </h3>
                            <div className="text-sm text-gray-500">
                                Phase 3.3 - Coming Soon
                            </div>
                        </div>
                        
                        {/* Placeholder for map component */}
                        <div className="h-96 bg-gray-100 rounded-lg flex items-center justify-center">
                            <div className="text-center">
                                <div className="text-4xl text-gray-400 mb-4">🗺️</div>
                                <h4 className="text-lg font-medium text-gray-700 mb-2">MapLibre GL Integration</h4>
                                <p className="text-sm text-gray-500">
                                    Interactive thermal analysis map will be implemented in Phase 3.3
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Side Panel Placeholder - This will contain building list in Phase 3.4 */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2">
                        <div className="bg-white overflow-hidden shadow rounded-lg">
                            <div className="px-4 py-5 sm:p-6">
                                <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Building Data Overview
                                </h3>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-blue-600">--</div>
                                        <div className="text-sm text-gray-500">Total Buildings</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-orange-600">--</div>
                                        <div className="text-sm text-gray-500">High TLI Buildings</div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-2xl font-bold text-green-600">--</div>
                                        <div className="text-sm text-gray-500">CO2 Savings Potential</div>
                                    </div>
                                </div>
                                <div className="mt-4 text-sm text-gray-500 text-center">
                                    Data will be loaded from /api/buildings in Phase 3.3
                                </div>
                            </div>
                        </div>
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
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex">
                        <div className="flex-shrink-0">
                            <svg className="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                            </svg>
                        </div>
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-blue-800">
                                Phase 3.1 & 3.2 Complete
                            </h3>
                            <div className="mt-2 text-sm text-blue-700">
                                <p>
                                    ✅ Authentication system implemented<br/>
                                    ✅ Dashboard layout and navigation complete<br/>
                                    🔄 Next: Map integration (Phase 3.3) and building interaction (Phase 3.4)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
} 