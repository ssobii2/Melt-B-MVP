import React, { useState, useEffect } from 'react';
import { apiClient } from '../utils/api';
import { useAuth } from '../contexts/AuthContext';

const ContextPanel = ({ selectedBuilding, onBuildingSelect, onBuildingHighlight }) => {
    const { isAdmin } = useAuth();
    const [isOpen, setIsOpen] = useState(true);
    const [buildings, setBuildings] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [buildingTypeFilter, setBuildingTypeFilter] = useState('');
    const [anomalyFilter, setAnomalyFilter] = useState('all'); // 'all', 'anomaly', 'normal'
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    // Building type options
    const buildingTypes = [
        { value: '', label: 'All Types' },
        { value: 'residential', label: 'Residential' },
        { value: 'commercial', label: 'Commercial' },
        { value: 'industrial', label: 'Industrial' },
        { value: 'public', label: 'Public' }
    ];

    // Anomaly filter options
    const anomalyOptions = [
        { value: '', label: 'All Buildings' },
        { value: 'true', label: 'Anomalies Only' },
        { value: 'false', label: 'Normal Only' }
    ];

    // Load buildings with filters
    const loadBuildings = async (page = 1) => {
        setIsLoading(true);
        try {
            const params = {
                page,
                per_page: 10,
                sort_by: 'is_anomaly',
                sort_order: 'desc',
                include_geometry: 1 // Include geometry for zooming functionality
            };

            if (searchTerm.trim()) {
                params.search = searchTerm.trim();
            }

            if (buildingTypeFilter) {
                params.type = buildingTypeFilter;
            }

            if (anomalyFilter !== 'all') {
                // Convert frontend values to backend expected values
                if (anomalyFilter === 'anomaly') {
                    params.anomaly_filter = 'true';
                } else if (anomalyFilter === 'normal') {
                    params.anomaly_filter = 'false';
                }
            }

            // Use admin endpoint for admin users to bypass entitlement restrictions
            const endpoint = isAdmin ? '/admin/buildings' : '/buildings';
            const response = await apiClient.get(endpoint, { params });
            setBuildings(response.data.data);
            setCurrentPage(response.data.meta.current_page);
            setTotalPages(response.data.meta.last_page || Math.ceil(response.data.meta.total / response.data.meta.per_page));
        } catch (error) {
            console.error('Failed to load buildings:', error);
            setBuildings([]);
        } finally {
            setIsLoading(false);
        }
    };

    // Load buildings when filters change
    useEffect(() => {
        const timeoutId = setTimeout(() => {
            setCurrentPage(1);
            loadBuildings(1);
        }, 300); // Debounce search

        return () => clearTimeout(timeoutId);
    }, [searchTerm, buildingTypeFilter, anomalyFilter]);

    // Load buildings when page changes
    useEffect(() => {
        loadBuildings(currentPage);
    }, [currentPage]);

    const handleBuildingClick = (building) => {
        onBuildingSelect(building);
    };

    // Find and navigate to the page containing the selected building
    const findBuildingPage = async (selectedBuildingGid) => {
        if (!selectedBuildingGid) return;
        
        try {
            // Check if the building is already on the current page
            const buildingOnCurrentPage = buildings.find(b => b.gid === selectedBuildingGid);
            if (buildingOnCurrentPage) {
                return; // Building is already visible, no need to change page
            }

            // Search for the building across all pages
            const params = {
                per_page: 10,
                sort_by: 'is_anomaly',
                sort_order: 'desc',
                include_geometry: 1,
                search_building_gid: selectedBuildingGid // Add a special search parameter
            };

            if (searchTerm.trim()) {
                params.search = searchTerm.trim();
            }

            if (buildingTypeFilter) {
                params.type = buildingTypeFilter;
            }

            if (anomalyFilter !== 'all') {
                // Convert frontend values to backend expected values
                if (anomalyFilter === 'anomaly') {
                    params.anomaly_filter = 'true';
                } else if (anomalyFilter === 'normal') {
                    params.anomaly_filter = 'false';
                }
            }

            // Try to find the building by searching through pages
            for (let page = 1; page <= totalPages; page++) {
                const response = await apiClient.get(isAdmin ? '/admin/buildings' : '/buildings', { 
                    params: { ...params, page } 
                });
                
                const foundBuilding = response.data.data.find(b => b.gid === selectedBuildingGid);
                if (foundBuilding) {
                    setCurrentPage(page);
                    return;
                }
            }
        } catch (error) {
            console.error('Failed to find building page:', error);
        }
    };

    // Watch for selectedBuilding changes from map clicks
    useEffect(() => {
        if (selectedBuilding && selectedBuilding.gid) {
            findBuildingPage(selectedBuilding.gid);
        }
    }, [selectedBuilding?.gid]);

    const handleBuildingHover = (building) => {
        if (onBuildingHighlight) {
            onBuildingHighlight(building);
        }
    };

    const getAnomalyColor = (isAnomaly) => {
        return isAnomaly ? '#ef4444' : '#3b82f6'; // red-500 : blue-500
    };

    const getAnomalyColorClass = (isAnomaly) => {
        if (isAnomaly === true) return 'bg-red-500';
        if (isAnomaly === false) return 'bg-blue-500';
        return 'bg-gray-400';
    };

    const getAnomalyLabel = (isAnomaly) => {
        if (isAnomaly === true) return 'Anomaly';
        if (isAnomaly === false) return 'Normal';
        return 'No Data';
    };

    return (
        <div className={`bg-white shadow-xl border border-gray-200 rounded-lg transition-all duration-300 ${isOpen ? 'w-96 h-[calc(100vh-22rem)]' : 'w-10 h-10'} backdrop-blur-sm bg-white/95`}>
            {/* Toggle Button */}
            <button
                onClick={() => setIsOpen(!isOpen)}
                className={`w-full h-10 flex items-center ${isOpen ? 'justify-between px-4' : 'justify-center'} ${isOpen ? 'border-b border-gray-200' : ''} cursor-pointer hover:bg-gray-50`}
                title={isOpen ? 'Close panel' : 'Open Building Explorer'}
            >
                {isOpen && (
                    <h3 className="text-lg font-medium text-gray-900 mt-1">Building Explorer</h3>
                )}
                <div className="text-gray-400 hover:text-gray-600 cursor-pointer">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={isOpen ? "M9 5l7 7-7 7" : "M15 19l-7-7 7-7"} />
                    </svg>
                </div>
            </button>

            {isOpen && (
                <div className="flex flex-col h-[calc(100%-2.5rem)]">
                    {/* Search and Filters - More Compact */}
                    <div className="p-3 border-b border-gray-200 space-y-2 flex-shrink-0">
                        {/* Search Input */}
                        <div>
                            <input
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                placeholder="Search by address..."
                                className="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>

                        <div className="flex gap-2">
                            {/* Building Type Filter */}
                            <div className="flex-1">
                                <select
                                    value={buildingTypeFilter}
                                    onChange={(e) => setBuildingTypeFilter(e.target.value)}
                                    className="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
                                >
                                    {buildingTypes.map(type => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Anomaly Filter */}
                            <div className="flex-1">
                                <select
                                    value={anomalyFilter}
                                    onChange={(e) => setAnomalyFilter(e.target.value)}
                                    className="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer"
                                >
                                    <option value="all">All Buildings</option>
                                    <option value="anomaly">Anomalies Only</option>
                                    <option value="normal">Normal Only</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {/* Buildings List */}
                    <div className="flex-1 overflow-y-auto">
                        <div className="p-3">
                            <h4 className="text-sm font-medium text-gray-700 mb-2">
                                Buildings ({buildings.length})
                            </h4>
                            <div className="space-y-2">
                                {isLoading ? (
                                    <div className="flex justify-center items-center py-4">
                                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                                    </div>
                                ) : buildings.length > 0 ? (
                                    buildings.map(building => (
                                        <div
                                            key={building.gid}
                                            className={`p-3 rounded-lg border transition-colors cursor-pointer relative group ${
                                                selectedBuilding?.gid === building.gid
                                                    ? 'bg-blue-50 border-blue-200'
                                                    : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                            onClick={() => handleBuildingClick(building)}
                                            onMouseEnter={() => handleBuildingHover(building)}
                                            onMouseLeave={() => handleBuildingHover(null)}
                                        >
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1 min-w-0">
                                                    <h3 className="text-sm font-medium text-gray-900 truncate">
                                                        {building.address || 
                                                         `${building.building_type_classification || 'Building'} ${building.gid?.slice(-6) || ''}`}
                                                    </h3>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        {building.building_type_classification || 'Unknown Type'}
                                                    </p>
                                                    <p className="text-xs text-gray-400 mt-0.5">
                                                        ID: {building.gid}
                                                    </p>
                                                </div>
                                                <div className="ml-2 flex flex-col items-end">
                                                    <span
                                                        className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium text-white"
                                                        style={{ backgroundColor: getAnomalyColor(building.is_anomaly) }}
                                                    >
                                                        {building.is_anomaly ? 'Anomaly' : 'Normal'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="text-center py-4 text-gray-500 text-sm">
                                        No buildings found
                                    </div>
                                )}
                            </div>

                            {/* Pagination */}
                            {totalPages > 1 && (
                                <div className="flex justify-between items-center mt-4 px-2">
                                    <button
                                        onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                                        disabled={currentPage === 1}
                                        className={`px-2 py-1 text-sm rounded ${
                                            currentPage === 1
                                                ? 'text-gray-400 cursor-not-allowed'
                                                : 'text-blue-600 hover:bg-blue-50 cursor-pointer'
                                        }`}
                                    >
                                        Previous
                                    </button>
                                    <span className="text-sm text-gray-600">
                                        Page {currentPage} of {totalPages}
                                    </span>
                                    <button
                                        onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                                        disabled={currentPage === totalPages}
                                        className={`px-2 py-1 text-sm rounded ${
                                            currentPage === totalPages
                                                ? 'text-gray-400 cursor-not-allowed'
                                                : 'text-blue-600 hover:bg-blue-50 cursor-pointer'
                                        }`}
                                    >
                                        Next
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default ContextPanel;