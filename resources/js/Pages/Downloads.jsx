import React, { useState, useEffect } from 'react';
import DashboardLayout from '../components/DashboardLayout';
import { Download, FileText, Database, Map, Loader2, AlertCircle, CheckCircle } from 'lucide-react';
import toast, { Toaster } from 'react-hot-toast';
import { apiClient } from '../utils/api';
import { useAuth } from '../contexts/AuthContext';

export default function Downloads() {
    const { user, token } = useAuth();
    const [entitlements, setEntitlements] = useState([]);
    const [adminDatasets, setAdminDatasets] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [downloadingIds, setDownloadingIds] = useState(new Set());

    useEffect(() => {
        fetchEntitlements();
    }, []);

    const fetchEntitlements = async () => {
        try {
            setLoading(true);
            
            if (user?.role === 'admin') {
                // For admin users, fetch all datasets
                const response = await apiClient.get('/admin/downloads/datasets');
                setAdminDatasets(response.data.datasets || []);
            } else {
                // For regular users, fetch entitlements
                const response = await apiClient.get('/me/entitlements');
                setEntitlements(response.data.entitlements || []);
            }
            
            setError(null);
        } catch (err) {
            console.error('Failed to fetch data:', err);
            setError('Failed to load your data access permissions. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    // Get downloadable datasets - different logic for admin vs regular users
    const getDownloadableDatasets = () => {
        // For admin users, fetch all datasets directly
        if (user?.role === 'admin') {
            return adminDatasets || [];
        }

        // For regular users, filter entitlements and group by dataset
        const downloadableEntitlements = entitlements.filter(entitlement => {
            // Only show DS-ALL, DS-AOI, and DS-BLD entitlements
            const hasDownloadType = ['DS-ALL', 'DS-AOI', 'DS-BLD'].includes(entitlement.type);
            
            // Must have download formats assigned
            const hasDownloadFormats = entitlement.download_formats && 
                Array.isArray(entitlement.download_formats) && 
                entitlement.download_formats.length > 0;
            
            // Must be active (not expired)
            const isActive = isEntitlementActive(entitlement);
            
            return hasDownloadType && hasDownloadFormats && isActive;
        });

        // Group by dataset
        const datasetGroups = {};
        downloadableEntitlements.forEach(entitlement => {
            const datasetId = entitlement.dataset?.id;
            if (!datasetId) return;

            if (!datasetGroups[datasetId]) {
                datasetGroups[datasetId] = {
                    dataset: entitlement.dataset,
                    entitlements: [],
                    bestEntitlement: null
                };
            }

            datasetGroups[datasetId].entitlements.push(entitlement);

            // Determine the best entitlement for this dataset
            // Priority: DS-ALL > DS-AOI > DS-BLD
            const currentBest = datasetGroups[datasetId].bestEntitlement;
            if (!currentBest || 
                (entitlement.type === 'DS-ALL' && currentBest.type !== 'DS-ALL') ||
                (entitlement.type === 'DS-AOI' && currentBest.type === 'DS-BLD')) {
                datasetGroups[datasetId].bestEntitlement = entitlement;
            }
        });

        return Object.values(datasetGroups);
    };

    const handleDownload = async (datasetId, format) => {
        const downloadId = `${datasetId}-${format}`;
        setDownloadingIds(prev => new Set([...prev, downloadId]));
        setError(null);

        try {
            // Show initial feedback
            const toastId = toast.loading(`Preparing ${format.toUpperCase()} download...`);

            // Determine the endpoint based on user role
            const endpoint = user?.role === 'admin' ? `/admin/downloads/${datasetId}` : `/downloads/${datasetId}`;
            
            // For admin users, get dataset info from adminDatasets
            // For regular users, get dataset info from grouped data
            let datasetName = 'Unknown Dataset';
            if (user?.role === 'admin') {
                const dataset = adminDatasets.find(ds => ds.id === datasetId);
                if (!dataset) {
                    throw new Error('Dataset not found');
                }
                datasetName = dataset.name;
            } else {
                const datasetGroup = downloadableDatasets.find(group => group.dataset.id === datasetId);
                if (!datasetGroup || !datasetGroup.dataset) {
                    throw new Error('Dataset not found');
                }
                datasetName = datasetGroup.dataset.name;
            }
            
            const response = await apiClient.get(endpoint, {
                params: { format },
                responseType: 'blob',
                timeout: 300000, // 5 minutes timeout for large downloads
                onDownloadProgress: (progressEvent) => {
                    if (progressEvent.lengthComputable) {
                        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                        toast.loading(`Downloading ${format.toUpperCase()}: ${percentCompleted}%`, { id: toastId });
                    }
                }
            });

            // Update toast for file processing
            toast.loading(`Processing ${format.toUpperCase()} file...`, { id: toastId });

            // Create download link
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            
            // Get filename from response headers or create default
            const contentDisposition = response.headers['content-disposition'];
            let filename = `dataset_${datasetId}.${format.toLowerCase()}`;
            if (contentDisposition) {
                const filenameMatch = contentDisposition.match(/filename="(.+)"/i);
                if (filenameMatch) {
                    filename = filenameMatch[1];
                }
            }
            
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);

            // Show success notification
            toast.success(`${format.toUpperCase()} download completed!`, { id: toastId });

        } catch (err) {
            console.error('Download failed:', err);
            
            let errorMessage;
            if (err.code === 'ECONNABORTED') {
                errorMessage = 'Download timed out. The file might be too large. Please try again or contact support.';
            } else if (err.response?.status === 403) {
                errorMessage = 'You do not have permission to download this format.';
            } else if (err.response?.status === 404) {
                errorMessage = 'Dataset not found.';
            } else {
                errorMessage = 'Download failed. Please try again.';
            }
            
            toast.error(errorMessage, { id: toastId });
            setError(errorMessage);
        } finally {
            setDownloadingIds(prev => {
                const newSet = new Set(prev);
                newSet.delete(downloadId);
                return newSet;
            });
        }
    };

    const getDataTypeIcon = (dataType) => {
        return <FileText className="h-5 w-5 text-gray-600" />;
    };

    const formatDataType = (dataType) => {
        return dataType?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || 'Unknown';
    };

    const getEntitlementTypeLabel = (type) => {
        switch (type) {
            case 'DS-ALL':
                return 'Full Dataset Access';
            case 'DS-AOI':
                return 'Area of Interest';
            case 'DS-BLD':
                return 'Specific Buildings';
            default:
                return type;
        }
    };

    const getStatusBadge = (entitlement) => {
        const now = new Date();
        const expiresAt = entitlement.expires_at ? new Date(entitlement.expires_at) : null;
        
        if (expiresAt && expiresAt < now) {
            return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Expired</span>;
        }
        
        return <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>;
    };

    const isEntitlementActive = (entitlement) => {
        const now = new Date();
        const expiresAt = entitlement.expires_at ? new Date(entitlement.expires_at) : null;
        return !expiresAt || expiresAt > now;
    };

    const downloadableDatasets = getDownloadableDatasets();

    if (loading) {
        return (
            <DashboardLayout title="Download Center">
                <div className="flex items-center justify-center py-12">
                    <Loader2 className="h-8 w-8 animate-spin" />
                    <span className="ml-2">Loading your data access permissions...</span>
                </div>
            </DashboardLayout>
        );
    }

    return (
        <DashboardLayout title="Download Center">
            <Toaster position="top-right" />
            <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div className="p-6 text-gray-900">
                            <div className="mb-6">
                                <h3 className="text-lg font-medium text-gray-900 mb-2">
                                    Data Download Center
                                </h3>
                                <p className="text-sm text-gray-600">
                                    {user?.role === 'admin' 
                                        ? 'Download complete datasets with full building energy data and thermal analysis results.'
                                        : 'Download building energy data and thermal analysis results based on your access permissions. Downloads include all buildings you have permission to view on the map.'
                                    }
                                </p>
                            </div>

                            {error && (
                                <div className="mb-6 p-4 border border-red-200 bg-red-50 rounded-md flex items-start gap-3">
                                    <AlertCircle className="h-4 w-4 text-red-600 mt-0.5 flex-shrink-0" />
                                    <p className="text-sm text-red-800">{error}</p>
                                </div>
                            )}
                            
                            {downloadableDatasets.length === 0 ? (
                                <div className="bg-white border border-gray-200 rounded-lg shadow-sm">
                                    <div className="px-6 py-4">
                                        <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                            <AlertCircle className="h-5 w-5 text-amber-600" />
                                            No Downloadable Data Available
                                        </h3>
                                        <p className="text-sm text-gray-600 mt-1">
                                            You don't have access to any downloadable datasets. Contact your administrator to request data access with download permissions.
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {user?.role === 'admin' ? (
                                        // Admin view - show all datasets with both CSV and GeoJSON options
                                        downloadableDatasets.map((dataset) => (
                                            <div key={dataset.id} className="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                                <div className="px-6 py-4">
                                                    <div className="flex items-center justify-between">
                                                        <div>
                                                            <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                                                {getDataTypeIcon(dataset?.data_type)}
                                                                {dataset?.name || 'Unknown Dataset'}
                                                            </h3>
                                                            <p className="text-sm text-gray-500 mt-1">
                                                                Complete Dataset Access
                                                            </p>
                                                        </div>
                                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            Admin Access
                                                        </span>
                                                    </div>
                                                    <div className="text-sm text-gray-600 mt-2">
                                                        Data Type: {formatDataType(dataset?.data_type)}
                                                        {dataset?.version && (
                                                            <span className="block mt-1">
                                                                Version: {dataset.version}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="px-6 pb-4">
                                                    <div className="flex gap-2 flex-wrap">
                                                        <button
                                                            onClick={() => handleDownload(dataset.id, 'csv')}
                                                            disabled={downloadingIds.has(`${dataset.id}-csv`)}
                                                            className="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                                        >
                                                            {downloadingIds.has(`${dataset.id}-csv`) ? (
                                                                <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                            ) : (
                                                                <FileText className="h-4 w-4 mr-2" />
                                                            )}
                                                            Download as CSV
                                                        </button>
                                                        <button
                                                            onClick={() => handleDownload(dataset.id, 'geojson')}
                                                            disabled={downloadingIds.has(`${dataset.id}-geojson`)}
                                                            className="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                                        >
                                                            {downloadingIds.has(`${dataset.id}-geojson`) ? (
                                                                <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                            ) : (
                                                                <Map className="h-4 w-4 mr-2" />
                                                            )}
                                                            Download as GeoJSON
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        // Regular user view - show grouped entitlements
                                        downloadableDatasets.map((datasetGroup) => {
                                            const { dataset, bestEntitlement } = datasetGroup;
                                            const allFormats = new Set();
                                            
                                            // Collect all available formats from all entitlements for this dataset
                                            datasetGroup.entitlements.forEach(entitlement => {
                                                if (entitlement.download_formats) {
                                                    entitlement.download_formats.forEach(format => allFormats.add(format));
                                                }
                                            });

                                            return (
                                                <div key={dataset.id} className="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                                    <div className="px-6 py-4">
                                                        <div className="flex items-center justify-between">
                                                            <div>
                                                                <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                                                    {getDataTypeIcon(dataset?.data_type)}
                                                                    {dataset?.name || 'Unknown Dataset'}
                                                                </h3>
                                                                <p className="text-sm text-gray-500 mt-1">
                                                                    {datasetGroup.entitlements.length > 1 
                                                                        ? `${datasetGroup.entitlements.length} access permissions`
                                                                        : 'Single access permission'
                                                                    }
                                                                </p>
                                                            </div>
                                                            {getStatusBadge(bestEntitlement)}
                                                        </div>
                                                        <div className="text-sm text-gray-600 mt-2">
                                                            Data Type: {formatDataType(dataset?.data_type)}
                                                            {bestEntitlement.expires_at && (
                                                                <span className="block mt-1">
                                                                    Expires: {new Date(bestEntitlement.expires_at).toLocaleDateString()}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="px-6 pb-4">
                                                        <div className="flex gap-2 flex-wrap">
                                                            {Array.from(allFormats).includes('csv') && (
                                                                <button
                                                                    onClick={() => handleDownload(dataset.id, 'csv')}
                                                                    disabled={downloadingIds.has(`${dataset.id}-csv`)}
                                                                    className="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                                                >
                                                                    {downloadingIds.has(`${dataset.id}-csv`) ? (
                                                                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                                    ) : (
                                                                        <FileText className="h-4 w-4 mr-2" />
                                                                    )}
                                                                    Download as CSV
                                                                </button>
                                                            )}
                                                            {Array.from(allFormats).includes('geojson') && (
                                                                <button
                                                                    onClick={() => handleDownload(dataset.id, 'geojson')}
                                                                    disabled={downloadingIds.has(`${dataset.id}-geojson`)}
                                                                    className="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                                                                >
                                                                    {downloadingIds.has(`${dataset.id}-geojson`) ? (
                                                                        <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                                    ) : (
                                                                        <Map className="h-4 w-4 mr-2" />
                                                                    )}
                                                                    Download as GeoJSON
                                                                </button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })
                                    )}
                                </div>
                            )}
                </div>
            </div>
        </DashboardLayout>
    );
}