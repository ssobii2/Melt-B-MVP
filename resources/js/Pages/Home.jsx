import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function Home() {
    const { isAuthenticated, loading } = useAuth();
    const navigate = useNavigate();

    useEffect(() => {
        if (!loading) {
            if (isAuthenticated) {
                // Redirect to dashboard if authenticated
                navigate('/dashboard');
            } else {
                // Redirect to login if not authenticated
                navigate('/login');
            }
        }
    }, [isAuthenticated, loading, navigate]);

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen bg-gradient-to-r from-blue-500 to-purple-600">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-white mx-auto mb-4"></div>
                    <h1 className="text-2xl font-bold text-white mb-2">MELT-B</h1>
                    <p className="text-lg text-white opacity-90">Loading...</p>
                </div>
            </div>
        );
    }

    // This will briefly show while redirecting
    return (
        <div className="flex items-center justify-center min-h-screen bg-gradient-to-r from-blue-500 to-purple-600">
            <div className="text-center">
                <h1 className="text-4xl font-bold text-white mb-4">
                    🚀 MELT-B
                </h1>
                <p className="text-lg text-white opacity-90">Thermal Analysis Platform</p>
                <p className="text-sm text-white opacity-75 mt-2">Redirecting...</p>
            </div>
        </div>
    );
}
