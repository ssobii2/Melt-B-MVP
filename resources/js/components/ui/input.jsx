import React from 'react';

export function Input({ 
    type = 'text', 
    className = '', 
    disabled = false,
    ...props 
}) {
    const baseClasses = 'flex h-10 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:cursor-not-allowed disabled:opacity-50';
    
    const classes = `${baseClasses} ${className}`;
    
    return (
        <input 
            type={type} 
            className={classes} 
            disabled={disabled} 
            {...props} 
        />
    );
}