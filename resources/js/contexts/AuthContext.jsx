import React, { createContext, useContext, useState, useEffect } from 'react';
import Cookies from 'js-cookie';
import { apiClient } from '../utils/api';

const AuthContext = createContext({});

export const useAuth = () => {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
};

export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [token, setTokenState] = useState(Cookies.get('auth_token'));

    // API client is now configured in utils/api.js with proper interceptors
    // Token management happens automatically through the apiClient

    // Check authentication status on load
    useEffect(() => {
        if (token) {
            fetchUser();
        } else {
            setLoading(false);
        }
    }, [token]);

    const setToken = (newToken) => {
        if (newToken) {
            Cookies.set('auth_token', newToken, { expires: 7 }); // 7 days
            setTokenState(newToken);
        } else {
            Cookies.remove('auth_token');
            setTokenState(null);
        }
    };

    const fetchUser = async () => {
        try {
            const response = await apiClient.get('/user');
            setUser(response.data.user);
        } catch (error) {
            console.error('Failed to fetch user:', error);
            setToken(null);
            setUser(null);
        } finally {
            setLoading(false);
        }
    };

    const login = async (credentials) => {
        try {
            const response = await apiClient.post('/login', credentials);
            const { user: userData, token: newToken } = response.data;
            
            setToken(newToken);
            setUser(userData);
            
            return { success: true, user: userData };
        } catch (error) {
            console.error('Login error:', error);
            const message = error.response?.data?.message || 'Login failed';
            return { success: false, error: message };
        }
    };

    const register = async (userData) => {
        try {
            const response = await apiClient.post('/register', userData);
            return { success: true, message: response.data.message };
        } catch (error) {
            const message = error.response?.data?.message || 'Registration failed';
            const errors = error.response?.data?.errors || {};
            return { success: false, error: message, errors };
        }
    };

    const logout = async () => {
        try {
            if (token) {
                await apiClient.post('/logout');
            }
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            setToken(null);
            setUser(null);
        }
    };

    const value = {
        user,
        token,
        loading,
        login,
        register,
        logout,
        isAuthenticated: !!user,
        isAdmin: user?.role === 'admin'
    };

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}; 