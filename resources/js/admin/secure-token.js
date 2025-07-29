/**
 * Secure Admin Token Handler
 * 
 * This module provides a secure way to handle admin tokens without exposing them globally.
 * Replace the global window.adminToken exposure with this approach.
 */

class SecureTokenHandler {
    constructor() {
        this.#token = null;
        this.initialized = false;
    }

    // Private field for token
    #token;

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
        this.#token = token;
        this.initialized = true;
        
        // Clear the token from any global scope immediately
        if (window.adminToken) {
            delete window.adminToken;
        }
    }

    /**
     * Get authorization header for API requests
     * Private method - token is never exposed
     */
    getAuthHeader() {
        if (!this.initialized || !this.#token) {
            throw new Error('Token handler not properly initialized');
        }
        return {
            'Authorization': `Bearer ${this.#token}`,
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        };
    }

    /**
     * Make authenticated AJAX request
     * Note: 401 error handling is managed globally by the admin layout
     */
    makeAuthenticatedRequest(options) {
        const defaultOptions = {
            headers: this.getAuthHeader(),
            timeout: 10000
        };
        
        return $.ajax($.extend(true, defaultOptions, options));
    }

    /**
     * Convenience method for GET requests
     */
    get(url, options = {}) {
        return this.makeAuthenticatedRequest({
            url: url,
            method: 'GET',
            ...options
        });
    }

    /**
     * Convenience method for POST requests
     */
    post(url, data = {}, options = {}) {
        return this.makeAuthenticatedRequest({
            url: url,
            method: 'POST',
            data: data,
            ...options
        });
    }

    /**
     * Convenience method for PUT requests
     */
    put(url, data = {}, options = {}) {
        return this.makeAuthenticatedRequest({
            url: url,
            method: 'PUT',
            data: data,
            ...options
        });
    }

    /**
     * Convenience method for DELETE requests
     */
    delete(url, options = {}) {
        return this.makeAuthenticatedRequest({
            url: url,
            method: 'DELETE',
            ...options
        });
    }

    /**
     * Convenience method for PATCH requests
     */
    patch(url, data = {}, options = {}) {
        return this.makeAuthenticatedRequest({
            url: url,
            method: 'PATCH',
            data: data,
            ...options
        });
    }

    /**
     * Helper for paginated GET requests
     */
    getPaginated(url, params = {}, options = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        return this.get(fullUrl, options);
    }

    /**
     * Clear the token (call on logout)
     */
    clear() {
        this.#token = null;
        this.initialized = false;
    }

    /**
     * Check if token is available
     */
    isAvailable() {
        return this.initialized && this.#token !== null;
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
