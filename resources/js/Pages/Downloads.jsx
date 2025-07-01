import React, { useState, useEffect } from 'react';
import DashboardLayout from '../components/DashboardLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Badge } from '../components/ui/badge';
import { Alert, AlertDescription } from '../components/ui/alert';
import { Download, FileText, Database, Map, Loader2, AlertCircle, CheckCircle } from 'lucide-react';
import toast, { Toaster } from 'react-hot-toast';
import { apiClient } from '../utils/api';
import { useAuth } from '../contexts/AuthContext';

export default function Downloads() {
    const { user, token } = useAuth();
    const [entitlements, setEntitlements] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [downloadingIds, setDownloadingIds] = useState(new Set());

    useEffect(() => {
        fetchEntitlements();
    }, []);

    const fetchEntitlements = async () => {
        try {
            setLoading(true);
            const response = await apiClient.get('/me/entitlements');
            setEntitlements(response.data.entitlements || []);
            setError(null);
        } catch (err) {
            console.error('Failed to fetch entitlements:', err);
            setError('Failed to load your data access permissions. Please try again.');
        } finally {
            setLoading(false);
        }
    };

    const handleDownload = async (entitlementId, format) => {
        const downloadId = `${entitlementId}-${format}`;
        setDownloadingIds(prev => new Set([...prev, downloadId]));
        setError(null);

        try {
            // Show initial feedback
            const toastId = toast.loading(`Preparing ${format.toUpperCase()} download...`);

            // Get the dataset ID from the entitlement
            const entitlement = entitlements.find(e => e.id === entitlementId);
            if (!entitlement || !entitlement.dataset) {
                throw new Error('Dataset not found for this entitlement');
            }
            
            const response = await apiClient.get(`/downloads/${entitlement.dataset.id}`, {
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
            let filename = `dataset_${entitlementId}.${format.toLowerCase()}`;
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
        switch (dataType?.toLowerCase()) {
            case 'building_data':
                return <Database className="h-5 w-5 text-blue-600" />;
            case 'thermal_analysis':
                return <Map className="h-5 w-5 text-red-600" />;
            case 'energy_performance':
                return <FileText className="h-5 w-5 text-green-600" />;
            default:
                return <FileText className="h-5 w-5 text-gray-600" />;
        }
    };

    const formatDataType = (dataType) => {
        return dataType?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || 'Unknown';
    };

    const getStatusBadge = (entitlement) => {
        const now = new Date();
        const expiresAt = entitlement.expires_at ? new Date(entitlement.expires_at) : null;
        
        if (expiresAt && expiresAt < now) {
            return <Badge variant="destructive">Expired</Badge>;
        }
        
        return <Badge variant="default" className="bg-green-100 text-green-800">Active</Badge>;
    };

    const isEntitlementActive = (entitlement) => {
        const now = new Date();
        const expiresAt = entitlement.expires_at ? new Date(entitlement.expires_at) : null;
        return !expiresAt || expiresAt > now;
    };

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
                                    Download building energy data and thermal analysis results based on your access permissions.
                                </p>
                            </div>

                            {error && (
                                <Alert className="mb-6">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            )}
                            
                            {/* Download Tips */}
                            <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                                <h3 className="text-sm font-medium text-blue-800 mb-2">💡 Download Tips</h3>
                                <ul className="text-sm text-blue-700 space-y-1">
                                    <li>• Large datasets may take several minutes to prepare and download</li>
                                    <li>• Progress indicators will show download status for your convenience</li>
                                    <li>• CSV format is recommended for data analysis and spreadsheet applications</li>
                                    <li>• GeoJSON format is ideal for GIS applications and mapping tools</li>
                                    <li>• Downloads will start automatically once processing is complete</li>
                                </ul>
                            </div>

                            {entitlements.length === 0 ? (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <AlertCircle className="h-5 w-5 text-amber-600" />
                                            No Data Access
                                        </CardTitle>
                                        <CardDescription>
                                            You don't have access to any datasets yet. Contact your administrator to request data access.
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            ) : (
                                <div className="space-y-4">
                                    {entitlements.map((entitlement) => (
                                        <Card key={entitlement.id}>
                                            <CardHeader>
                                                <div className="flex items-center justify-between">
                                                    <CardTitle className="flex items-center gap-2">
                                                        {getDataTypeIcon(entitlement.dataset?.data_type)}
                                                        {entitlement.dataset?.name || 'Unknown Dataset'}
                                                    </CardTitle>
                                                    {getStatusBadge(entitlement)}
                                                </div>
                                                <CardDescription>
                                                    Data Type: {formatDataType(entitlement.dataset?.data_type)}
                                                    {entitlement.expires_at && (
                                                        <span className="block mt-1">
                                                            Expires: {new Date(entitlement.expires_at).toLocaleDateString()}
                                                        </span>
                                                    )}
                                                </CardDescription>
                                            </CardHeader>
                                            <CardContent>
                                                <div className="flex gap-2 flex-wrap">
                                                    {entitlement.download_formats?.includes('csv') && (
                                                        <Button
                                                            onClick={() => handleDownload(entitlement.id, 'csv')}
                                                            disabled={!isEntitlementActive(entitlement) || downloadingIds.has(`${entitlement.id}-csv`)}
                                                            variant="outline"
                                                            size="sm"
                                                            className="cursor-pointer"
                                                        >
                                                            {downloadingIds.has(`${entitlement.id}-csv`) ? (
                                                                <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                            ) : (
                                                                <FileText className="h-4 w-4 mr-2" />
                                                            )}
                                                            Download as CSV
                                                        </Button>
                                                    )}
                                                    {entitlement.download_formats?.includes('geojson') && (
                                                        <Button
                                                            onClick={() => handleDownload(entitlement.id, 'geojson')}
                                                            disabled={!isEntitlementActive(entitlement) || downloadingIds.has(`${entitlement.id}-geojson`)}
                                                            variant="outline"
                                                            size="sm"
                                                            className="cursor-pointer"
                                                        >
                                                            {downloadingIds.has(`${entitlement.id}-geojson`) ? (
                                                                <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                            ) : (
                                                                <Map className="h-4 w-4 mr-2" />
                                                            )}
                                                            Download as GeoJSON
                                                        </Button>
                                                    )}
                                                    {(!entitlement.download_formats || entitlement.download_formats.length === 0) && (
                                                        <p className="text-sm text-gray-500">No download formats available</p>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}
                </div>
            </div>
        </DashboardLayout>
    );
}