import React from 'react';

export function Alert({ children, variant = 'default', className = '', ...props }) {
    const baseClasses = 'relative w-full rounded-lg border p-4';
    
    const variants = {
        default: 'bg-white border-gray-200 text-gray-900',
        destructive: 'bg-red-50 border-red-200 text-red-900',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-900',
        success: 'bg-green-50 border-green-200 text-green-900'
    };
    
    const classes = `${baseClasses} ${variants[variant]} ${className}`;
    
    return (
        <div className={classes} {...props}>
            {children}
        </div>
    );
}

export function AlertDescription({ children, className = '', ...props }) {
    return (
        <div className={`text-sm ${className}`} {...props}>
            {children}
        </div>
    );
}