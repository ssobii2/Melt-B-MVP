import axios from 'axios';
import Cookies from 'js-cookie';
import toast from 'react-hot-toast';

// Create axios instance with proper configuration
const api = axios.create({
    baseURL: '/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    timeout: 20000, // Increased from 10s to 20s for building data requests
});

// Request interceptor for adding auth token
api.interceptors.request.use(
    (config) => {
        const token = Cookies.get('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Global state for preventing multiple logout attempts and race conditions
let logoutInProgress = false;
let sessionExpiredShown = false;

/**
 * Perform secure logout with proper cleanup
 * Centralized function to prevent race conditions and ensure consistent behavior
 */
function performSecureLogout() {
    // Prevent multiple simultaneous logout attempts
    if (logoutInProgress) {
        return;
    }
    logoutInProgress = true;
    
    // Clear all client-side storage immediately for security
    Cookies.remove('auth_token');
    if (typeof(Storage) !== "undefined") {
        localStorage.clear();
        sessionStorage.clear();
    }
    
    // Force redirect to login page
    // Use replace to prevent back button navigation to authenticated pages
    window.location.replace('/login');
}

// Response interceptor for handling global errors
api.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        // Handle 401 Unauthorized globally with enhanced security
        if (error.response?.status === 401 && !sessionExpiredShown && !logoutInProgress) {
            sessionExpiredShown = true;
            
            // Only show alert if not already on login page to avoid infinite loops
            if (!window.location.pathname.includes('/login')) {
                // Show toast notification
                toast.error('Your session has expired. You will be redirected to the login page.', {
                    duration: 3000,
                    position: 'top-right'
                });
            }
            
            // Immediate logout for security - no delays that can be bypassed
            performSecureLogout();
        }
        
        // Handle other error types
        if (error.response?.status === 403) {
            console.warn('Access denied:', error.response.data?.message || 'Insufficient permissions');
        }
        
        return Promise.reject(error);
    }
);

// API functions
export const apiClient = {
    get: (url, config = {}) => api.get(url, config),
    post: (url, data = {}, config = {}) => api.post(url, data, config),
    put: (url, data = {}, config = {}) => api.put(url, data, config),
    delete: (url, config = {}) => api.delete(url, config),
    patch: (url, data = {}, config = {}) => api.patch(url, data, config),
};

export default api;