/**
 * Secure Admin Token Handler
 * 
 * This module provides a secure way to handle admin tokens without exposing them globally.
 * Replace the global window.adminToken exposure with this approach.
 */

class SecureTokenHandler {
    constructor() {
        this.token = null;
        this.initialized = false;
    }

    /**
     * Initialize the token handler with the admin token
     * Call this once when the page loads
     */
    init(token) {
        if (this.initialized) {
            console.warn('Token handler already initialized');
            return;
        }
        
        // Store token securely (not on window object)
        this.token = token;
        this.initialized = true;
        
        // Clear the token from any global scope immediately
        if (window.adminToken) {
            delete window.adminToken;
        }
    }

    /**
     * Get the token for API requests
     * Only accessible through this controlled interface
     */
    getToken() {
        if (!this.initialized || !this.token) {
            throw new Error('Token handler not properly initialized');
        }
        return this.token;
    }

    /**
     * Get authorization header for API requests
     */
    getAuthHeader() {
        return {
            'Authorization': `Bearer ${this.getToken()}`,
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        };
    }

    /**
     * Make authenticated AJAX request
     */
    makeAuthenticatedRequest(options) {
        const defaultOptions = {
            headers: this.getAuthHeader(),
            timeout: 10000
        };
        
        return $.ajax($.extend(true, defaultOptions, options));
    }

    /**
     * Clear the token (call on logout)
     */
    clear() {
        this.token = null;
        this.initialized = false;
    }

    /**
     * Check if token is available
     */
    isAvailable() {
        return this.initialized && this.token !== null;
    }
}

// Create singleton instance and make it globally available
// This ensures compatibility with existing admin JS files
const adminTokenHandler = new SecureTokenHandler();

// Make available globally for existing admin files
window.adminTokenHandler = adminTokenHandler;

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = adminTokenHandler;
}
