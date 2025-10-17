/**
 * Agrohub ERP Platform Configuration
 */

// API Configuration
window.AGROHUB_CONFIG = {
    API_BASE_URL: '/Agrohub_Engine/api',
    APP_NAME: 'Agrohub ERP Platform',
    VERSION: '1.0.0',
    
    // API Endpoints
    ENDPOINTS: {
        LOGIN: '/auth.php?action=login',
        REGISTER: '/auth.php?action=register',
        VERIFY: '/auth.php?action=verify',
        LOGOUT: '/auth.php?action=logout',
        MODULES: '/modules.php',
        USERS: '/users.php',
        ADMIN: '/admin.php'
    },

    // Storage Keys
    STORAGE_KEYS: {
        AUTH_TOKEN: 'auth_token',
        USER_DATA: 'user_data',
        PREFERENCES: 'user_preferences'
    },

    // Default Settings
    DEFAULTS: {
        THEME: 'light',
        LANGUAGE: 'en',
        TIMEZONE: 'UTC'
    }
};

// Helper Functions
window.AgrohubUtils = {
    // Get full API URL
    getApiUrl: function(endpoint) {
        return window.AGROHUB_CONFIG.API_BASE_URL + endpoint;
    },

    // Storage helpers
    setToken: function(token) {
        localStorage.setItem(window.AGROHUB_CONFIG.STORAGE_KEYS.AUTH_TOKEN, token);
        // Also set as cookie for cross-domain compatibility
        document.cookie = `auth_token=${token}; path=/; max-age=${7 * 24 * 60 * 60}; SameSite=Lax`;
    },

    getToken: function() {
        return localStorage.getItem(window.AGROHUB_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
    },

    removeToken: function() {
        localStorage.removeItem(window.AGROHUB_CONFIG.STORAGE_KEYS.AUTH_TOKEN);
        localStorage.removeItem(window.AGROHUB_CONFIG.STORAGE_KEYS.USER_DATA);
        // Clear cookie
        document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
    },

    setUserData: function(userData) {
        localStorage.setItem(window.AGROHUB_CONFIG.STORAGE_KEYS.USER_DATA, JSON.stringify(userData));
    },

    getUserData: function() {
        const data = localStorage.getItem(window.AGROHUB_CONFIG.STORAGE_KEYS.USER_DATA);
        return data ? JSON.parse(data) : null;
    },

    // HTTP Request helper with authentication
    request: async function(endpoint, options = {}) {
        const url = this.getApiUrl(endpoint);
        const token = this.getToken();

        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            credentials: 'include'
        };

        // Add authorization header if token exists
        if (token) {
            defaultOptions.headers['Authorization'] = `Bearer ${token}`;
        }

        // Merge options
        const finalOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, finalOptions);
            
            // Handle different response types - FIXED: only read body once
            const contentType = response.headers.get('content-type');
            let data;
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                // Handle non-JSON responses
                const text = await response.text();
                console.warn('Non-JSON response:', text.substring(0, 200));
                
                // Try to extract JSON from HTML response
                const jsonMatch = text.match(/\{[\s\S]*\}/);
                if (jsonMatch) {
                    try {
                        data = JSON.parse(jsonMatch[0]);
                    } catch (e) {
                        console.error('Failed to parse JSON from response:', e);
                        data = { success: false, error: 'Invalid response format', raw: text.substring(0, 500) };
                    }
                } else {
                    data = { success: false, error: 'Invalid response format', raw: text.substring(0, 500) };
                }
            }

            return {
                success: response.ok && (data.success !== false),
                status: response.status,
                data: data,
                response: response
            };

        } catch (error) {
            console.error('Request error:', error);
            return {
                success: false,
                status: 0,
                data: { error: error.message },
                response: null
            };
        }
    },

    // Show notification
    showNotification: function(message, type = 'info', duration = 3000) {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white transform translate-x-full transition-transform duration-300`;
        
        // Set color based on type
        switch (type) {
            case 'success':
                notification.classList.add('bg-green-500');
                break;
            case 'error':
                notification.classList.add('bg-red-500');
                break;
            case 'warning':
                notification.classList.add('bg-yellow-500');
                break;
            default:
                notification.classList.add('bg-blue-500');
        }

        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : type === 'warning' ? 'exclamation' : 'info'}-circle mr-2"></i>
                ${message}
            </div>
        `;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        // Auto remove
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, duration);
    },

    // Format date
    formatDate: function(date, format = 'short') {
        const d = new Date(date);
        if (format === 'short') {
            return d.toLocaleDateString();
        } else if (format === 'long') {
            return d.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        } else if (format === 'datetime') {
            return d.toLocaleString();
        }
        return d.toString();
    },

    // Validate email
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    // Generate avatar from name
    generateAvatar: function(name) {
        if (!name) return 'U';
        return name.charAt(0).toUpperCase();
    }
};

console.log('Agrohub Config Loaded', window.AGROHUB_CONFIG);

// Backwards-compatibility alias for older scripts that expect a global `CONFIG` object
// This maps minimal keys used by legacy code (api.js, apps.js) to the new AGROHUB_CONFIG
window.CONFIG = {
    API_BASE_URL: window.AGROHUB_CONFIG.API_BASE_URL || '/Agrohub_Engine/api',
    API_TIMEOUT: 15000,
    DEBUG: typeof window.AGROHUB_CONFIG.DEBUG !== 'undefined' ? window.AGROHUB_CONFIG.DEBUG : false,
    STORAGE_KEYS: {
        // map legacy USER_TOKEN to AUTH_TOKEN
        USER_TOKEN: (window.AGROHUB_CONFIG.STORAGE_KEYS && window.AGROHUB_CONFIG.STORAGE_KEYS.AUTH_TOKEN) || 'auth_token',
        USER_DATA: (window.AGROHUB_CONFIG.STORAGE_KEYS && window.AGROHUB_CONFIG.STORAGE_KEYS.USER_DATA) || 'user_data',
        PREFERENCES: (window.AGROHUB_CONFIG.STORAGE_KEYS && window.AGROHUB_CONFIG.STORAGE_KEYS.PREFERENCES) || 'user_preferences'
    },
    MODULE_CATEGORIES: window.AGROHUB_CONFIG.MODULE_CATEGORIES || ['Website','Sales','Finance','Services','Human Resources','Marketing','Productivity','Supply Chain','Customizations'],
    ERROR_MESSAGES: {
        TIMEOUT_ERROR: 'Request timed out',
        NETWORK_ERROR: 'Network error',
        PERMISSION_ERROR: 'Permission denied',
        NOT_FOUND_ERROR: 'Not found',
        SERVER_ERROR: 'Internal server error',
        CONFLICT_ERROR: 'Conflict'
    }
};