import React, { useState, useEffect } from 'react';
import { apiClient } from '../utils/api';

const ContextPanel = ({ selectedBuilding, onBuildingSelect, onBuildingHighlight }) => {
    const [isOpen, setIsOpen] = useState(true);
    const [buildings, setBuildings] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [buildingTypeFilter, setBuildingTypeFilter] = useState('');
    const [tliRangeFilter, setTliRangeFilter] = useState('');
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

    // TLI range options
    const tliRanges = [
        { value: '', label: 'All TLI Ranges' },
        { value: '0-20', label: 'Low (0-20)' },
        { value: '20-40', label: 'Medium Low (20-40)' },
        { value: '40-60', label: 'Medium (40-60)' },
        { value: '60-80', label: 'Medium High (60-80)' },
        { value: '80-100', label: 'High (80+)' }
    ];

    // Load buildings with filters
    const loadBuildings = async (page = 1) => {
        setIsLoading(true);
        try {
            const params = {
                page,
                per_page: 10,
                sort_by: 'thermal_loss_index_tli',
                sort_order: 'desc',
                include_geometry: 1 // Include geometry for zooming functionality
            };

            if (searchTerm.trim()) {
                params.search = searchTerm.trim();
            }

            if (buildingTypeFilter) {
                params.type = buildingTypeFilter;
            }

            if (tliRangeFilter) {
                const [min, max] = tliRangeFilter.split('-');
                params.tli_min = parseInt(min);
                if (max !== '100') {
                    params.tli_max = parseInt(max);
                }
            }

            const response = await apiClient.get('/buildings', { params });
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
    }, [searchTerm, buildingTypeFilter, tliRangeFilter]);

    // Load buildings when page changes
    useEffect(() => {
        loadBuildings(currentPage);
    }, [currentPage]);

    const handleBuildingClick = (building) => {
        onBuildingSelect(building);
    };

    const handleBuildingHover = (building) => {
        if (onBuildingHighlight) {
            onBuildingHighlight(building);
        }
    };

    const getTliColor = (tli) => {
        if (tli <= 30) return '#10b981'; // green-500
        if (tli <= 60) return '#f59e0b'; // amber-500  
        if (tli <= 90) return '#f97316'; // orange-500
        return '#ef4444'; // red-500
    };

    const getTliColorClass = (tli) => {
        if (tli <= 30) return 'bg-green-500';
        if (tli <= 60) return 'bg-amber-500';
        if (tli <= 90) return 'bg-orange-500';
        return 'bg-red-500';
    };

    const getTliLabel = (tli) => {
        if (tli <= 30) return 'Low';
        if (tli <= 60) return 'Medium';
        if (tli <= 90) return 'High';
        return 'Very High';
    };

    return (
        <div className={`bg-white shadow-xl border border-gray-200 rounded-lg transition-all duration-300 ${isOpen ? 'w-96 h-[calc(100vh-22rem)]' : 'w-10 h-10'} backdrop-blur-sm bg-white/95`}>
            {/* Toggle Button */}
            <button
                onClick={() => setIsOpen(!isOpen)}
                className={`w-full h-10 flex items-center ${isOpen ? 'justify-between px-4' : 'justify-center'} ${isOpen ? 'border-b border-gray-200' : ''}`}
                title={isOpen ? 'Close panel' : 'Open Building Explorer'}
            >
                {isOpen && (
                    <h3 className="text-lg font-medium text-gray-900 mt-1">Building Explorer</h3>
                )}
                <div className="text-gray-400 hover:text-gray-600">
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
                                    className="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    {buildingTypes.map(type => (
                                        <option key={type.value} value={type.value}>
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* TLI Range Filter */}
                            <div className="flex-1">
                                <select
                                    value={tliRangeFilter}
                                    onChange={(e) => setTliRangeFilter(e.target.value)}
                                    className="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    {tliRanges.map(range => (
                                        <option key={range.value} value={range.value}>
                                            {range.label}
                                        </option>
                                    ))}
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
                                                        style={{ backgroundColor: building.tli_color }}
                                                    >
                                                        {building.thermal_loss_index_tli}
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
                                                : 'text-blue-600 hover:bg-blue-50'
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
                                                : 'text-blue-600 hover:bg-blue-50'
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