import React from 'react';
import { Link } from 'react-router-dom';
import DashboardLayout from '../components/DashboardLayout';

export default function Downloads() {
    return (
        <DashboardLayout title="Download Center">
            <div className="max-w-4xl mx-auto">
                <div className="bg-white overflow-hidden shadow rounded-lg">
                    <div className="px-4 py-5 sm:p-6">
                        <div className="text-center py-12">
                            <div className="text-6xl text-gray-300 mb-4">📥</div>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                Download Center
                            </h3>
                            <p className="text-sm text-gray-500 mb-6">
                                Data download functionality will be implemented in Phase 4.2
                            </p>
                            
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left max-w-2xl mx-auto">
                                <h4 className="text-sm font-medium text-blue-900 mb-2">Coming Soon:</h4>
                                <ul className="text-sm text-blue-700 space-y-1">
                                    <li>• Download thermal analysis datasets</li>
                                    <li>• Export building data in multiple formats (CSV, GeoJSON)</li>
                                    <li>• API token management for programmatic access</li>
                                    <li>• Download history and progress tracking</li>
                                </ul>
                            </div>

                            <div className="mt-6">
                                <Link
                                    to="/dashboard"
                                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 cursor-pointer"
                                >
                                    ← Back to Dashboard
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}