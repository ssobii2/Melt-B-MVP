import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

// Import page components
import Home from '../Pages/Home';
import Login from '../Pages/Auth/Login';
// import Register from '../Pages/Auth/Register'; // Commented out - registration disabled
import EmailVerificationResult from '../Pages/Auth/EmailVerificationResult';
import Dashboard from '../Pages/Dashboard';
import Profile from '../Pages/Profile';
import Downloads from '../Pages/Downloads';
import Feedback from '../Pages/Feedback';
import NotFound from '../Pages/NotFound';

// Protected Route Component
const ProtectedRoute = ({ children }) => {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace />;
    }

    return children;
};

// Public Route Component (redirects to dashboard if already authenticated)
const PublicRoute = ({ children }) => {
    const { isAuthenticated, loading } = useAuth();

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    if (isAuthenticated) {
        return <Navigate to="/dashboard" replace />;
    }

    return children;
};

// Main Router Component
const Router = () => {
    return (
        <Routes>
            {/* Public Routes */}
            <Route path="/" element={<Home />} />
            <Route 
                path="/login" 
                element={
                    <PublicRoute>
                        <Login />
                    </PublicRoute>
                } 
            />
            {/* Registration route commented out to disable signup */}
            {/* <Route 
                path="/register" 
                element={
                    <PublicRoute>
                        <Register />
                    </PublicRoute>
                } 
            /> */}
            <Route path="/email-verification-result" element={<EmailVerificationResult />} />

            {/* Protected Routes */}
            <Route 
                path="/dashboard" 
                element={
                    <ProtectedRoute>
                        <Dashboard />
                    </ProtectedRoute>
                } 
            />
            <Route 
                path="/profile" 
                element={
                    <ProtectedRoute>
                        <Profile />
                    </ProtectedRoute>
                } 
            />
            <Route 
                path="/downloads" 
                element={
                    <ProtectedRoute>
                        <Downloads />
                    </ProtectedRoute>
                } 
            />
            <Route 
                path="/feedback" 
                element={
                    <ProtectedRoute>
                        <Feedback />
                    </ProtectedRoute>
                } 
            />

            {/* 404 Route */}
            <Route path="*" element={<NotFound />} />
        </Routes>
    );
};

export default Router;