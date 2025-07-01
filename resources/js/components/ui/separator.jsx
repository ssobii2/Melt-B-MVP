import React from 'react';

export function Separator({ orientation = 'horizontal', className = '', ...props }) {
    const baseClasses = 'shrink-0 bg-gray-200';
    
    const orientationClasses = {
        horizontal: 'h-[1px] w-full',
        vertical: 'h-full w-[1px]'
    };
    
    const classes = `${baseClasses} ${orientationClasses[orientation]} ${className}`;
    
    return (
        <div className={classes} {...props} />
    );
}