import React from 'react';

export function Button({ 
    children, 
    variant = 'default', 
    size = 'default', 
    disabled = false, 
    className = '', 
    ...props 
}) {
    const baseClasses = 'inline-flex items-center justify-center rounded-md font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none';
    
    const variants = {
        default: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
        outline: 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-blue-500',
        destructive: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        ghost: 'text-gray-700 hover:bg-gray-100 focus:ring-blue-500'
    };
    
    const sizes = {
        default: 'px-4 py-2 text-sm',
        sm: 'px-3 py-1.5 text-xs',
        lg: 'px-6 py-3 text-base'
    };
    
    const classes = `${baseClasses} ${variants[variant]} ${sizes[size]} ${className}`;
    
    return (
        <button className={classes} disabled={disabled} {...props}>
            {children}
        </button>
    );
}