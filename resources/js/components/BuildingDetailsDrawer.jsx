import React, { useState, useEffect } from 'react';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
} from 'chart.js';
import { Bar, Line } from 'react-chartjs-2';
import { apiClient } from '../utils/api';
import { useAuth } from '../contexts/AuthContext';
import toast, { Toaster } from 'react-hot-toast';

// Register Chart.js components
ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Title,
    Tooltip,
    Legend
);

const BuildingDetailsDrawer = ({ selectedBuilding, onClose }) => {
    const { user } = useAuth();
    const [userEntitlements, setUserEntitlements] = useState([]);
    const [isLoadingEntitlements, setIsLoadingEntitlements] = useState(false);
    const [heatLossAnalytics, setHeatLossAnalytics] = useState(null);
    const [isLoadingAnalytics, setIsLoadingAnalytics] = useState(false);

    // Fetch user entitlements for download permissions
    useEffect(() => {
        const fetchUserEntitlements = async () => {
            setIsLoadingEntitlements(true);
            try {
                const response = await apiClient.get('/me/entitlements');
                setUserEntitlements(response.data.entitlements || []);
            } catch (error) {
                console.error('Failed to fetch user entitlements:', error);
                setUserEntitlements([]);
            } finally {
                setIsLoadingEntitlements(false);
            }
        };

        if (selectedBuilding) {
            fetchUserEntitlements();
        }
    }, [selectedBuilding]);

    // Fetch heat loss analytics
    useEffect(() => {
        const fetchHeatLossAnalytics = async () => {
            if (!selectedBuilding) return;
            
            setIsLoadingAnalytics(true);
            try {
                const params = {
                    building_gid: selectedBuilding.gid
                };
                
                // Add building type filter if available
                if (selectedBuilding.building_type_classification) {
                    params.building_type = selectedBuilding.building_type_classification;
                }
                
                const response = await apiClient.get('/buildings/analytics/heat-loss', { params });
                setHeatLossAnalytics(response.data);
            } catch (error) {
                console.error('Failed to fetch heat loss analytics:', error);
                setHeatLossAnalytics(null);
            } finally {
                setIsLoadingAnalytics(false);
            }
        };

        fetchHeatLossAnalytics();
    }, [selectedBuilding]);

    // Check if user can download in specific format
    const canDownloadFormat = (format) => {
        // Admin users can download any format
        if (user?.role === 'admin') {
            return true;
        }
        
        if (!userEntitlements || !Array.isArray(userEntitlements)) {
            return false;
        }
        
        // Find entitlements that grant access to this specific building
        const buildingAccessEntitlements = userEntitlements.filter(entitlement => {
            // Only check DS-ALL, DS-AOI, and DS-BLD entitlements (TILES entitlements don't grant download access)
            const hasDownloadType = ['DS-ALL', 'DS-AOI', 'DS-BLD'].includes(entitlement.type);
            
            if (!hasDownloadType) return false;
            
            // Check if user has access to this building/dataset
            if (entitlement.type === 'DS-ALL') {
                return true; // DS-ALL grants access to all buildings in the dataset
            } else if (entitlement.type === 'DS-BLD') {
                return entitlement.building_gids?.includes(selectedBuilding.gid);
            } else if (entitlement.type === 'DS-AOI') {
                // For DS-AOI, we assume the user has access if they can see the building
                // The backend will handle the spatial filtering
                return true;
            }
            
            return false;
        });
        
        // Check if any of the building access entitlements have the specific format
        return buildingAccessEntitlements.some(entitlement => {
            return entitlement.download_formats && 
                Array.isArray(entitlement.download_formats) && 
                entitlement.download_formats.includes(format);
        });
    };

    // Handle download
    const handleDownload = async (format) => {
        if (!selectedBuilding || !canDownloadFormat(format)) {
            toast.error('You do not have permission to download data in this format.');
            return;
        }

        try {
            // Show loading toast
            const toastId = toast.loading(`Preparing ${format === 'csv' ? 'CSV' : 'GeoJSON'} download...`);

            // Find the dataset ID for this building
            const datasetId = selectedBuilding.dataset_id;
            if (!datasetId) {
                toast.error('No dataset information available for this building.', { id: toastId });
                return;
            }

            // Use admin endpoint for admin users, regular endpoint for others
            const endpoint = user?.role === 'admin' ? `/admin/downloads/${datasetId}` : `/downloads/${datasetId}`;

            const response = await apiClient.get(endpoint, {
                params: { 
                    format,
                    building_gid: selectedBuilding.gid // Pass the building GID to download only this building
                },
                responseType: 'blob'
            });

            // Create download link
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `building_${selectedBuilding.gid}_data.${format}`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);

            // Show success toast
            toast.success(`${format === 'csv' ? 'CSV' : 'GeoJSON'} download completed!`, { id: toastId });
        } catch (error) {
            console.error('Download failed:', error);
            
            let errorMessage = 'Download failed. Please try again.';
            if (error.response?.status === 403) {
                errorMessage = 'You do not have permission to download this format.';
            } else if (error.response?.status === 404) {
                errorMessage = 'Building or dataset not found.';
            }
            
            toast.error(errorMessage);
        }
    };

    if (!selectedBuilding) {
        return null;
    }

    // Prepare chart data for heat loss comparison
    const chartData = {
        labels: ['Selected Building', 'Category Average'],
        datasets: [
            {
                label: 'Heat Loss',
                data: [
                    selectedBuilding.average_heatloss || 0,
                    selectedBuilding.reference_heatloss || 0
                ],
                backgroundColor: [
                    selectedBuilding.is_anomaly ? '#ef4444' : '#3b82f6', // Red for anomaly, blue for normal
                    '#94a3b8' // Gray for reference
                ],
                borderColor: [
                    selectedBuilding.is_anomaly ? '#dc2626' : '#2563eb',
                    '#64748b'
                ],
                borderWidth: 1,
                barThickness: 60, // Make bars thinner
            },
        ],
    };

    const chartOptions = {
        responsive: true,
        plugins: {
            legend: {
                display: false,
            },
            title: {
                display: true,
                text: 'Heat Loss Comparison',
                font: {
                    size: 14,
                    weight: 'bold'
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `${context.label}: ${context.parsed.y.toFixed(2)}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Heat Loss Value'
                }
            },
        },
    };

    // Calculate deviation color and label
    const getDeviationColor = (difference) => {
        if (difference > 0) return 'text-red-600'; // Worse than average
        if (difference < 0) return 'text-green-600'; // Better than average
        return 'text-gray-600'; // Same as average
    };

    const getDeviationLabel = (difference) => {
        if (difference > 0) return 'Above Average';
        if (difference < 0) return 'Below Average';
        return 'At Average';
    };

    return (
        <div className="bg-white overflow-hidden shadow rounded-lg mb-6">
            <Toaster position="top-right" />
            <div className="px-4 py-5 sm:p-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg leading-6 font-medium text-gray-900">
                        Building Details
                    </h3>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 cursor-pointer"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    {/* Left Column - Basic Information and KPIs */}
                    <div className="xl:col-span-1 space-y-6">
                        {/* Basic Information */}
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

                        {/* Key Performance Indicators */}
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-3">Key Performance Indicators</h4>
                            <div className="grid grid-cols-2 gap-4">
                                {/* Anomaly Status */}
                                <div className="bg-gray-50 p-3 rounded-lg">
                                    <dt className="text-xs text-gray-500 mb-1">Anomaly Status</dt>
                                    <dd>
                                        <span 
                                            className={`inline-flex px-2 py-1 text-xs font-medium rounded-full text-white ${
                                                selectedBuilding.is_anomaly ? 'bg-red-500' : 'bg-blue-500'
                                            }`}
                                        >
                                            {selectedBuilding.is_anomaly ? 'Anomaly' : 'Normal'}
                                        </span>
                                    </dd>
                                </div>

                                {/* Confidence Score */}
                                <div className="bg-gray-50 p-3 rounded-lg">
                                    <dt className="text-xs text-gray-500 mb-1">Confidence Score</dt>
                                    <dd className="text-lg font-semibold text-gray-900">
                                        {selectedBuilding.confidence != null && !isNaN(selectedBuilding.confidence) 
                                            ? `${(Number(selectedBuilding.confidence) * 100).toFixed(1)}%` 
                                            : 'N/A'
                                        }
                                    </dd>
                                </div>

                                {/* Heat Loss Deviation */}
                                <div className="bg-gray-50 p-3 rounded-lg">
                                    <dt className="text-xs text-gray-500 mb-1">Deviation from Average</dt>
                                    <dd className={`text-lg font-semibold ${
                                        getDeviationColor(selectedBuilding.heatloss_difference)
                                    }`}>
                                        {selectedBuilding.heatloss_difference != null && !isNaN(selectedBuilding.heatloss_difference)
                                            ? `${Number(selectedBuilding.heatloss_difference).toFixed(2)}`
                                            : 'N/A'
                                        }
                                    </dd>
                                    <dd className={`text-xs ${
                                        getDeviationColor(selectedBuilding.heatloss_difference)
                                    }`}>
                                        {selectedBuilding.heatloss_difference != null && !isNaN(selectedBuilding.heatloss_difference)
                                            ? getDeviationLabel(selectedBuilding.heatloss_difference)
                                            : ''
                                        }
                                    </dd>
                                </div>

                                {/* CO2 Savings Estimate */}
                                <div className="bg-gray-50 p-3 rounded-lg">
                                    <dt className="text-xs text-gray-500 mb-1">CO2 Savings Estimate</dt>
                                    <dd className="text-lg font-semibold text-green-600">
                                        {selectedBuilding.co2_savings_estimate 
                                            ? `${selectedBuilding.co2_savings_estimate} kg` 
                                            : 'N/A'
                                        }
                                    </dd>
                                </div>
                            </div>
                        </div>

                        {/* Download Section */}
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-3">Download Building Data</h4>
                            {isLoadingEntitlements ? (
                                <div className="flex items-center justify-center py-4">
                                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                                </div>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    {['csv', 'geojson'].map(format => (
                                        <button
                                            key={format}
                                            onClick={() => handleDownload(format)}
                                            disabled={!canDownloadFormat(format)}
                                            className={`px-3 py-2 text-sm font-medium rounded-md ${
                                                canDownloadFormat(format)
                                                    ? 'bg-blue-600 text-white hover:bg-blue-700 cursor-pointer'
                                                    : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                            }`}
                                            title={!canDownloadFormat(format) ? 'You do not have permission to download in this format' : ''}
                                        >
                                            Download as {format === 'csv' ? 'CSV' : 'GeoJSON'}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Building Metrics */}
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-3">Building Metrics</h4>
                            <dl className="space-y-3">
                                <div className="flex justify-between">
                                    <dt className="text-sm text-gray-500">Average Heat Loss</dt>
                                    <dd className="text-sm font-medium text-gray-900">
                                        {selectedBuilding.average_heatloss != null && !isNaN(selectedBuilding.average_heatloss) 
                                            ? Number(selectedBuilding.average_heatloss).toFixed(2) 
                                            : 'N/A'
                                        }
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-sm text-gray-500">Reference Heat Loss</dt>
                                    <dd className="text-sm font-medium text-gray-900">
                                        {selectedBuilding.reference_heatloss != null && !isNaN(selectedBuilding.reference_heatloss) 
                                            ? Number(selectedBuilding.reference_heatloss).toFixed(2) 
                                            : 'N/A'
                                        }
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-sm text-gray-500">Heat Loss Difference</dt>
                                    <dd className={`text-sm font-medium ${
                                        getDeviationColor(selectedBuilding.heatloss_difference)
                                    }`}>
                                        {selectedBuilding.heatloss_difference != null && !isNaN(selectedBuilding.heatloss_difference) 
                                            ? Number(selectedBuilding.heatloss_difference).toFixed(2) 
                                            : 'N/A'
                                        }
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-sm text-gray-500">Analysis Confidence</dt>
                                    <dd className="text-sm font-medium text-gray-900">
                                        {selectedBuilding.confidence != null && !isNaN(selectedBuilding.confidence) 
                                            ? `${(Number(selectedBuilding.confidence) * 100).toFixed(1)}%` 
                                            : 'N/A'
                                        }
                                    </dd>
                                </div>
                                {selectedBuilding.dataset_name && (
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Dataset</dt>
                                        <dd className="text-sm font-medium text-gray-900">
                                            {selectedBuilding.dataset_name}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </div>

                    </div>

                    {/* Right Column - Chart and Detailed Metrics */}
                    <div className="xl:col-span-2 space-y-6">
                        {/* Heat Loss Comparison Chart */}
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-3">Heat Loss Analysis</h4>
                            <div className="bg-gray-50 p-6 rounded-lg">
                                {selectedBuilding.average_heatloss != null && selectedBuilding.reference_heatloss != null ? (
                                    <>
                                        <div className="mb-3">
                                            <p className="text-xs text-gray-600">
                                                This chart compares your building's heat loss (blue) against the category average (gray). 
                                                Lower values indicate better thermal performance.
                                            </p>
                                        </div>
                                        <div className="h-80 lg:h-96">
                                            <Bar data={chartData} options={{...chartOptions, maintainAspectRatio: false}} />
                                        </div>
                                    </>
                                ) : (
                                    <div className="text-center py-16 text-gray-500">
                                        <svg className="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <p className="mt-4 text-lg">Heat loss data not available</p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Statistical Analysis */}
                        {isLoadingAnalytics ? (
                            <div className="flex items-center justify-center py-8">
                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                                <span className="ml-2 text-gray-600">Loading analytics...</span>
                            </div>
                        ) : heatLossAnalytics && (
                            <div>
                                <h4 className="text-sm font-medium text-gray-900 mb-3">Statistical Analysis</h4>
                                
                                {/* Distribution Chart */}
                                {heatLossAnalytics.distribution && heatLossAnalytics.distribution.length > 0 && (
                                    <div className="bg-gray-50 p-6 rounded-lg mb-6">
                                        <h5 className="text-sm font-medium text-gray-700 mb-3">Heat Loss Distribution</h5>
                                        <div className="mb-3">
                                            <p className="text-xs text-gray-600">
                                                This histogram shows how heat loss values are distributed across all buildings in the dataset. 
                                                Your building is highlighted in {selectedBuilding.is_anomaly ? 'red (anomaly detected)' : 'blue'}, 
                                                while other buildings appear in gray.
                                            </p>
                                        </div>
                                        <div className="h-64">
                                            <Bar 
                                                data={{
                                                    labels: heatLossAnalytics.distribution.map(bin => 
                                                        `${bin.range_start.toFixed(1)} - ${bin.range_end.toFixed(1)}`
                                                    ),
                                                    datasets: [{
                                                        label: 'Number of Buildings',
                                                        data: heatLossAnalytics.distribution.map(bin => bin.count),
                                                        backgroundColor: heatLossAnalytics.distribution.map(bin => {
                                                            // Highlight the bin containing the current building
                                                            const buildingValue = selectedBuilding.average_heatloss;
                                                            if (buildingValue >= bin.range_start && buildingValue < bin.range_end) {
                                                                return selectedBuilding.is_anomaly ? '#ef4444' : '#3b82f6';
                                                            }
                                                            return '#e5e7eb';
                                                        }),
                                                        borderColor: '#9ca3af',
                                                        borderWidth: 1,
                                                        barThickness: 40,
                                                        maxBarThickness: 50
                                                    }]
                                                }}
                                                options={{
                                                    responsive: true,
                                                    maintainAspectRatio: false,
                                                    plugins: {
                                                        legend: { display: false },
                                                        title: {
                                                            display: true,
                                                            text: `Distribution of Heat Loss Values (${heatLossAnalytics.heat_loss_statistics?.total_buildings || 0} buildings)`,
                                                            font: { size: 12 }
                                                        },
                                                        tooltip: {
                                                            callbacks: {
                                                                label: function(context) {
                                                                    const totalBuildings = heatLossAnalytics.heat_loss_statistics?.total_buildings || 1;
                                                                    const percentage = ((context.parsed.y / totalBuildings) * 100).toFixed(1);
                                                                    return `${context.parsed.y} buildings (${percentage}%)`;
                                                                }
                                                            }
                                                        }
                                                    },
                                                    scales: {
                                                        y: {
                                                            beginAtZero: true,
                                                            title: { display: true, text: 'Number of Buildings' }
                                                        },
                                                        x: {
                                                            title: { display: true, text: 'Heat Loss Range' }
                                                        }
                                                    }
                                                }}
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Statistical Metrics Grid */}
                                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                                    <div className="bg-blue-50 p-4 rounded-lg">
                                        <dt className="text-xs text-blue-600 font-medium">Mean</dt>
                                        <dd className="text-lg font-semibold text-blue-900">
                                            {heatLossAnalytics.heat_loss_statistics?.mean?.toFixed(2) || 'N/A'}
                                        </dd>
                                    </div>
                                    <div className="bg-green-50 p-4 rounded-lg">
                                        <dt className="text-xs text-green-600 font-medium">Median</dt>
                                        <dd className="text-lg font-semibold text-green-900">
                                            {heatLossAnalytics.heat_loss_statistics?.median?.toFixed(2) || 'N/A'}
                                        </dd>
                                    </div>
                                    <div className="bg-purple-50 p-4 rounded-lg">
                                        <dt className="text-xs text-purple-600 font-medium">Std Dev</dt>
                                        <dd className="text-lg font-semibold text-purple-900">
                                            {heatLossAnalytics.heat_loss_statistics?.std_deviation?.toFixed(2) || 'N/A'}
                                        </dd>
                                    </div>
                                    <div className="bg-orange-50 p-4 rounded-lg">
                                        <dt className="text-xs text-orange-600 font-medium">Range</dt>
                                        <dd className="text-lg font-semibold text-orange-900">
                                            {heatLossAnalytics.heat_loss_statistics?.min != null && heatLossAnalytics.heat_loss_statistics?.max != null 
                                                ? `${heatLossAnalytics.heat_loss_statistics.min.toFixed(1)} - ${heatLossAnalytics.heat_loss_statistics.max.toFixed(1)}`
                                                : 'N/A'
                                            }
                                        </dd>
                                    </div>
                                </div>

                                {/* Building Comparison */}
                                {heatLossAnalytics.building_comparison && (
                                    <div className="bg-gray-50 p-4 rounded-lg mb-6">
                                        <h5 className="text-sm font-medium text-gray-700 mb-3">Your Building vs. Similar Buildings</h5>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div className="text-center">
                                                <dt className="text-xs text-gray-500">Percentile Rank</dt>
                                                <dd className={`text-2xl font-bold ${
                                                    heatLossAnalytics.building_comparison.current_building?.percentile_rank > 75 ? 'text-red-600' :
                                                    heatLossAnalytics.building_comparison.current_building?.percentile_rank > 50 ? 'text-yellow-600' :
                                                    'text-green-600'
                                                }`}>
                                                    {heatLossAnalytics.building_comparison.current_building?.percentile_rank?.toFixed(1)}%
                                                </dd>
                                                <dd className="text-xs text-gray-500 mt-1">
                                                    {heatLossAnalytics.building_comparison.current_building?.percentile_rank > 75 ? 'Higher than most' :
                                                     heatLossAnalytics.building_comparison.current_building?.percentile_rank > 50 ? 'Above average' :
                                                     'Below average'}
                                                </dd>
                                            </div>
                                            <div className="text-center">
                                                <dt className="text-xs text-gray-500">Deviation from Mean</dt>
                                                <dd className={`text-2xl font-bold ${
                                                    heatLossAnalytics.building_comparison.comparison_stats?.deviation_from_mean > 0 ? 'text-red-600' : 'text-green-600'
                                                }`}>
                                                    {heatLossAnalytics.building_comparison.comparison_stats?.deviation_from_mean > 0 ? '+' : ''}
                                                    {heatLossAnalytics.building_comparison.comparison_stats?.deviation_from_mean?.toFixed(2)}
                                                </dd>
                                            </div>
                                            <div className="text-center">
                                                <dt className="text-xs text-gray-500">Z-Score</dt>
                                                <dd className={`text-2xl font-bold ${
                                                    Math.abs(heatLossAnalytics.building_comparison.comparison_stats?.z_score) > 2 ? 'text-red-600' :
                                                    Math.abs(heatLossAnalytics.building_comparison.comparison_stats?.z_score) > 1 ? 'text-yellow-600' :
                                                    'text-green-600'
                                                }`}>
                                                    {heatLossAnalytics.building_comparison.comparison_stats?.z_score?.toFixed(2)}
                                                </dd>
                                                <dd className="text-xs text-gray-500 mt-1">
                                                    {Math.abs(heatLossAnalytics.building_comparison.comparison_stats?.z_score) > 2 ? 'Highly unusual' :
                                                     Math.abs(heatLossAnalytics.building_comparison.comparison_stats?.z_score) > 1 ? 'Somewhat unusual' :
                                                     'Normal range'}
                                                </dd>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Percentiles */}
                                {heatLossAnalytics.p25 != null && (
                                    <div>
                                        <h5 className="text-sm font-medium text-gray-700 mb-3">Percentile Breakdown</h5>
                                        <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                                            <div className="text-center p-3 bg-gray-100 rounded">
                                                <dt className="text-xs text-gray-500">25th</dt>
                                                <dd className="text-sm font-semibold">{heatLossAnalytics.p25.toFixed(2)}</dd>
                                            </div>
                                            <div className="text-center p-3 bg-gray-100 rounded">
                                                <dt className="text-xs text-gray-500">50th</dt>
                                                <dd className="text-sm font-semibold">{heatLossAnalytics.p50.toFixed(2)}</dd>
                                            </div>
                                            <div className="text-center p-3 bg-gray-100 rounded">
                                                <dt className="text-xs text-gray-500">75th</dt>
                                                <dd className="text-sm font-semibold">{heatLossAnalytics.p75.toFixed(2)}</dd>
                                            </div>
                                            <div className="text-center p-3 bg-gray-100 rounded">
                                                <dt className="text-xs text-gray-500">90th</dt>
                                                <dd className="text-sm font-semibold">{heatLossAnalytics.p90.toFixed(2)}</dd>
                                            </div>
                                            <div className="text-center p-3 bg-gray-100 rounded">
                                                <dt className="text-xs text-gray-500">95th</dt>
                                                <dd className="text-sm font-semibold">{heatLossAnalytics.p95.toFixed(2)}</dd>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default BuildingDetailsDrawer;