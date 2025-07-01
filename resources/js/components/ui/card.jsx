import React from 'react';

export function Card({ children, className = '', ...props }) {
    return (
        <div className={`bg-white border border-gray-200 rounded-lg shadow-sm ${className}`} {...props}>
            {children}
        </div>
    );
}

export function CardHeader({ children, className = '', ...props }) {
    return (
        <div className={`px-6 py-4 border-b border-gray-200 ${className}`} {...props}>
            {children}
        </div>
    );
}

export function CardTitle({ children, className = '', ...props }) {
    return (
        <h3 className={`text-lg font-semibold text-gray-900 ${className}`} {...props}>
            {children}
        </h3>
    );
}

export function CardDescription({ children, className = '', ...props }) {
    return (
        <p className={`text-sm text-gray-600 mt-1 ${className}`} {...props}>
            {children}
        </p>
    );
}

export function CardContent({ children, className = '', ...props }) {
    return (
        <div className={`px-6 py-4 ${className}`} {...props}>
            {children}
        </div>
    );
}