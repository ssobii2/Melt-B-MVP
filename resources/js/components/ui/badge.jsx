import React from 'react';

export function Badge({ children, variant = 'default', className = '', ...props }) {
    const baseClasses = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium';
    
    const variants = {
        default: 'bg-gray-100 text-gray-800',
        destructive: 'bg-red-100 text-red-800',
        success: 'bg-green-100 text-green-800',
        warning: 'bg-yellow-100 text-yellow-800',
        info: 'bg-blue-100 text-blue-800'
    };
    
    const classes = `${baseClasses} ${variants[variant]} ${className}`;
    
    return (
        <span className={classes} {...props}>
            {children}
        </span>
    );
}