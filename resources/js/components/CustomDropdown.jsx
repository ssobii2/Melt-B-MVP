import React, { useState, useEffect, useRef } from 'react';
import { ChevronDown } from 'lucide-react';

export default function CustomDropdown({
    label,
    value,
    options = {},
    placeholder = 'Select an option',
    onChange,
    error,
    required = false,
    className = '',
    disabled = false
}) {
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    const handleSelect = (selectedValue) => {
        onChange(selectedValue);
        setIsOpen(false);
    };

    const toggleDropdown = () => {
        if (!disabled) {
            setIsOpen(!isOpen);
        }
    };

    const displayValue = value && options[value] ? options[value] : placeholder;
    const hasValue = value && options[value];

    return (
        <div className={`relative ${className}`} ref={dropdownRef}>
            {label && (
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    {label} {required && '*'}
                </label>
            )}
            <button
                type="button"
                onClick={toggleDropdown}
                disabled={disabled}
                className={`block w-full px-3 py-2 text-left bg-white border rounded-md shadow-sm focus:outline-none focus:ring-1 transition-colors duration-150 cursor-pointer ${
                    disabled
                        ? 'bg-gray-50 text-gray-500 cursor-not-allowed border-gray-200'
                        : error
                        ? 'border-red-300 focus:border-red-500 focus:ring-red-500 text-gray-900'
                        : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500 hover:border-gray-400 text-gray-900'
                }`}
            >
                <div className="flex items-center justify-between">
                    <span className={hasValue ? 'text-gray-900' : 'text-gray-500'}>
                        {displayValue}
                    </span>
                    <ChevronDown 
                        className={`h-4 w-4 text-gray-400 transition-transform duration-200 ${
                            isOpen ? 'rotate-180' : ''
                        } ${disabled ? 'opacity-50' : ''}`} 
                    />
                </div>
            </button>
            
            {isOpen && !disabled && (
                <div className="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg">
                    <div className="py-1">
                        {/* Show placeholder option if no value is selected and placeholder is provided */}
                        {!hasValue && placeholder && (
                            <button
                                type="button"
                                onClick={() => handleSelect('')}
                                className="block w-full text-left px-3 py-2 text-sm hover:bg-gray-100 cursor-pointer text-gray-500"
                            >
                                {placeholder}
                            </button>
                        )}
                        {Object.entries(options).map(([key, label]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => handleSelect(key)}
                                className="block w-full text-left px-3 py-2 text-sm hover:bg-gray-100 cursor-pointer"
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>
            )}
            
            {error && (
                <p className="mt-1 text-sm text-red-600">{error}</p>
            )}
        </div>
    );
}