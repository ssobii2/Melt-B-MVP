import React, { useState, useEffect, useRef } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function DashboardLayout({ children, title = "Dashboard" }) {
    const { user, logout } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [profileMenuOpen, setProfileMenuOpen] = useState(false);
    const profileMenuRef = useRef(null);

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    // Close profile menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (profileMenuRef.current && !profileMenuRef.current.contains(event.target)) {
                setProfileMenuOpen(false);
            }
        };

        if (profileMenuOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [profileMenuOpen]);

    // Helper function to determine if a link is active
    const isActivePath = (path) => {
        return location.pathname === path;
    };

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Top Navigation Bar */}
            <nav className="bg-white shadow-sm border-b border-gray-200">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16">
                        {/* Logo and Main Nav */}
                        <div className="flex">
                            <div className="flex-shrink-0 flex items-center">
                                <h1 className="text-xl font-bold text-gray-900">MELT-B</h1>
                                <span className="ml-2 text-sm text-gray-500">Thermal Analysis Platform</span>
                            </div>
                            
                            <div className="hidden sm:ml-6 sm:flex sm:space-x-8">
                                <Link
                                    to="/dashboard"
                                    className={`${
                                        isActivePath('/dashboard')
                                            ? 'border-blue-500 text-gray-900'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                                >
                                    Dashboard
                                </Link>
                                <Link
                                    to="/downloads"
                                    className={`${
                                        isActivePath('/downloads')
                                            ? 'border-blue-500 text-gray-900'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                                >
                                    Downloads
                                </Link>
                                <Link
                                    to="/feedback"
                                    className={`${
                                        isActivePath('/feedback')
                                            ? 'border-blue-500 text-gray-900'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    } inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium`}
                                >
                                    Feedback
                                </Link>
                            </div>
                        </div>

                        {/* User Profile Menu */}
                        <div className="flex items-center">
                            <div className="relative" ref={profileMenuRef}>
                                <button
                                    onClick={() => setProfileMenuOpen(!profileMenuOpen)}
                                    className="flex items-center text-sm rounded-full focus:outline-none cursor-pointer"
                                >
                                    <div className="flex items-center space-x-3">
                                        <div className="flex flex-col items-end">
                                            <div className="text-sm font-medium text-gray-700">{user?.name}</div>
                                            <div className="text-xs text-gray-500 capitalize">{user?.role}</div>
                                        </div>
                                        <div className="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center">
                                            <span className="text-sm font-medium text-white">
                                                {user?.name?.charAt(0).toUpperCase()}
                                            </span>
                                        </div>
                                        <svg className="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                </button>

                                {/* Profile Dropdown */}
                                {profileMenuOpen && (
                                    <div className="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div className="py-1">
                                            <Link
                                                to="/profile"
                                                className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer"
                                                onClick={() => setProfileMenuOpen(false)}
                                            >
                                                Your Profile
                                            </Link>

                                            {user?.role === 'admin' && (
                                                <a
                                                    href="/admin"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer"
                                                    onClick={() => setProfileMenuOpen(false)}
                                                >
                                                    Admin Dashboard
                                                </a>
                                            )}
                                            <hr className="my-1" />
                                            <button
                                                onClick={handleLogout}
                                                className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 cursor-pointer"
                                            >
                                                Sign out
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Page Header */}
            {title && (
                <header className="bg-white shadow-sm">
                    <div className="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                        <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                    </div>
                </header>
            )}

            {/* Main Content */}
            <main className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {children}
            </main>
        </div>
    );
}