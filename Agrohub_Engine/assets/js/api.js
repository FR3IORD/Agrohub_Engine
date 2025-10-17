// Agrohub ERP Platform - API Client

class ApiClient {
    constructor() {
        this.baseURL = CONFIG.API_BASE_URL;
        this.timeout = CONFIG.API_TIMEOUT;
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    // Get authentication headers
    getAuthHeaders() {
        const token = localStorage.getItem(CONFIG.STORAGE_KEYS.USER_TOKEN);
        return token ? { 'Authorization': `Bearer ${token}` } : {};
    }

    // Make HTTP request
    async request(method, endpoint, data = null, options = {}) {
        // Handle endpoints that already contain query parameters
        const basePath = this.baseURL.replace(/\/$/, '');
        const cleaned = endpoint.replace(/^\//, '');
        
        // Check if endpoint already has .php extension or query parameters
        let finalUrl;
        if (cleaned.includes('.php') || cleaned.includes('?')) {
            // Endpoint already has .php or query params, use as-is
            finalUrl = `${basePath}/${cleaned}`;
        } else {
            // Try with .php extension first
            const urlWithPhp = `${basePath}/${cleaned}.php`;
            finalUrl = urlWithPhp;
        }

        const config = {
            method: method.toUpperCase(),
            credentials: 'include',
            headers: {
                ...this.defaultHeaders,
                ...this.getAuthHeaders(),
                ...options.headers
            },
            ...options
        };

        // Add data to request
        if (data) {
            if (method.toUpperCase() === 'GET') {
                // Convert data to query parameters
                const params = new URLSearchParams(data);
                const separator = finalUrl.includes('?') ? '&' : '?';
                finalUrl += separator + params.toString();
            } else {
                config.body = JSON.stringify(data);
            }
        }

        try {
            // Add timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.timeout);
            config.signal = controller.signal;

            let response = await fetch(finalUrl, config);
            
            // If 404 and we added .php, try without .php extension
            if (response.status === 404 && !cleaned.includes('.php') && !cleaned.includes('?')) {
                const urlWithoutPhp = `${basePath}/${cleaned}`;
                const finalUrlWithoutPhp = data && method.toUpperCase() === 'GET' 
                    ? urlWithoutPhp + '?' + new URLSearchParams(data).toString()
                    : urlWithoutPhp;
                response = await fetch(finalUrlWithoutPhp, config);
            }

            clearTimeout(timeoutId);
            return await this.handleResponse(response);
        } catch (error) {
            return this.handleError(error);
        }
    }

    // Handle API response
    async handleResponse(response) {
        const contentType = response.headers.get('content-type');
        
        let data;
        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            data = await response.text();
        }

        if (!response.ok) {
            throw new ApiError(data.message || 'API request failed', response.status, data);
        }

        return data;
    }

    // Handle API errors
    handleError(error) {
        if (error.name === 'AbortError') {
            throw new ApiError(CONFIG.ERROR_MESSAGES.TIMEOUT_ERROR, 408);
        }
        
        if (error instanceof ApiError) {
            throw error;
        }

        // Network or other errors
        console.error('API Error:', error);
        throw new ApiError(CONFIG.ERROR_MESSAGES.NETWORK_ERROR, 0);
    }

    // HTTP methods
    async get(endpoint, params = null, options = {}) {
        return this.request('GET', endpoint, params, options);
    }

    async post(endpoint, data = null, options = {}) {
        return this.request('POST', endpoint, data, options);
    }

    async put(endpoint, data = null, options = {}) {
        return this.request('PUT', endpoint, data, options);
    }

    async patch(endpoint, data = null, options = {}) {
        return this.request('PATCH', endpoint, data, options);
    }

    async delete(endpoint, options = {}) {
        return this.request('DELETE', endpoint, null, options);
    }

    // Upload file
    async upload(endpoint, file, data = {}, onProgress = null) {
        const formData = new FormData();
        formData.append('file', file);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        const config = {
            method: 'POST',
            headers: {
                ...this.getAuthHeaders()
                // Don't set Content-Type for FormData
            },
            body: formData
        };

        // Add progress tracking if supported
        if (onProgress && window.XMLHttpRequest) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        onProgress(percentComplete);
                    }
                });

                xhr.addEventListener('load', () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            resolve(response);
                        } catch (error) {
                            resolve(xhr.responseText);
                        }
                    } else {
                        reject(new ApiError('Upload failed', xhr.status));
                    }
                });

                xhr.addEventListener('error', () => {
                    reject(new ApiError('Upload failed', 0));
                });

                xhr.open('POST', this.baseURL + '/' + endpoint.replace(/^\//, ''));
                
                // Set auth header
                const token = localStorage.getItem(CONFIG.STORAGE_KEYS.USER_TOKEN);
                if (token) {
                    xhr.setRequestHeader('Authorization', `Bearer ${token}`);
                }
                
                xhr.send(formData);
            });
        }

        // Fallback to fetch
        const url = this.baseURL + '/' + endpoint.replace(/^\//, '');
        const response = await fetch(url, config);
        return this.handleResponse(response);
    }
}

// Custom API Error class
class ApiError extends Error {
    constructor(message, status = 0, data = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

// API Service classes
class AuthService {
    constructor(apiClient) {
        this.api = apiClient;
    }

    // Fix the login endpoint - remove .php from action parameter
    async login(identifier, password, rememberMe = false) {
        // Use correct endpoint: /api/auth.php?action=login (not login.php)
        return this.api.post('auth.php?action=login', { 
            identifier, 
            password, 
            remember_me: rememberMe 
        });
    }

    async register(name, email, company, password) {
        return this.api.post('auth.php?action=register', { 
            name, 
            email, 
            company, 
            password 
        });
    }

    async verify(token) {
        return this.api.post('auth.php?action=verify', { 
            token 
        });
    }

    async logout() {
        return this.api.post('auth.php?action=logout');
    }

    async forgotPassword(email) {
        return this.api.post('auth', { 
            action: 'forgot-password',
            email 
        });
    }

    async resetPassword(token, password) {
        return this.api.post('auth', { 
            action: 'reset-password',
            token, 
            password 
        });
    }

    async changePassword(currentPassword, newPassword) {
        return this.api.post('auth', { 
            action: 'change-password',
            current_password: currentPassword, 
            new_password: newPassword 
        });
    }
}

class UserService {
    constructor(apiClient) {
        this.api = apiClient;
    }

    async getProfile() {
        return this.api.get('users/profile');
    }

    async updateProfile(data) {
        return this.api.put('users/profile', data);
    }

    async getUsers(params = {}) {
        return this.api.get('users', params);
    }

    async getUser(id) {
        return this.api.get(`users/${id}`);
    }

    async createUser(data) {
        return this.api.post('users', data);
    }

    async updateUser(id, data) {
        return this.api.put(`users/${id}`, data);
    }

    async deleteUser(id) {
        return this.api.delete(`users/${id}`);
    }

    async uploadAvatar(file) {
        return this.api.upload('users/avatar', file);
    }
}

class ModuleService {
    constructor(apiClient) {
        this.api = apiClient;
    }

    async getModules(params = {}) {
        return this.api.get('modules', params);
    }

    async getModule(id) {
        return this.api.get(`modules/${id}`);
    }

    async getUserModules() {
        return this.api.get('modules/user');
    }

    async installModule(moduleId) {
        return this.api.post(`modules/${moduleId}/install`);
    }

    async uninstallModule(moduleId) {
        return this.api.post(`modules/${moduleId}/uninstall`);
    }

    async updateModule(moduleId, data) {
        return this.api.put(`modules/${moduleId}`, data);
    }

    async getModuleSettings(moduleId) {
        return this.api.get(`modules/${moduleId}/settings`);
    }

    async updateModuleSettings(moduleId, settings) {
        return this.api.put(`modules/${moduleId}/settings`, settings);
    }
}

class ActivityService {
    constructor(apiClient) {
        this.api = apiClient;
    }

    async getActivities(params = {}) {
        return this.api.get('activities', params);
    }

    async createActivity(data) {
        return this.api.post('activities', data);
    }

    async getActivity(id) {
        return this.api.get(`activities/${id}`);
    }

    async deleteActivity(id) {
        return this.api.delete(`activities/${id}`);
    }
}

class SettingsService {
    constructor(apiClient) {
        this.api = apiClient;
    }

    async getSettings() {
        return this.api.get('settings');
    }

    async updateSettings(settings) {
        return this.api.put('settings', settings);
    }

    async getSetting(key) {
        return this.api.get(`settings/${key}`);
    }

    async updateSetting(key, value) {
        return this.api.put(`settings/${key}`, { value });
    }
}

class CompanyService {
    constructor(apiClient) {
        this.api = apiClient;
    }

    async getCompany() {
        return this.api.get('company');
    }

    async updateCompany(data) {
        return this.api.put('company', data);
    }

    async uploadLogo(file) {
        return this.api.upload('company/logo', file);
    }
}

// Initialize API client and services
const apiClient = new ApiClient();
const authService = new AuthService(apiClient);
const userService = new UserService(apiClient);
const moduleService = new ModuleService(apiClient);
const activityService = new ActivityService(apiClient);
const settingsService = new SettingsService(apiClient);
const companyService = new CompanyService(apiClient);

// Utility functions
function showLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.classList.remove('hidden');
        loading.classList.add('flex');
    }
}

function hideLoading() {
    const loading = document.getElementById('loading');
    if (loading) {
        loading.classList.add('hidden');
        loading.classList.remove('flex');
    }
}

function showToast(message, type = 'info', duration = 5000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type} px-4 py-3 rounded-lg shadow-lg text-white mb-2`;
    toast.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    container.appendChild(toast);

    // Auto remove toast
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, duration);
}

// Global error handler for API calls
window.addEventListener('unhandledrejection', function(event) {
    if (event.reason instanceof ApiError) {
        console.error('API Error:', event.reason);
        
        // Handle specific error codes
        switch (event.reason.status) {
            case 401:
                // Unauthorized - redirect to login
                if (window.authManager) {
                    window.authManager.logout();
                    showToast('Please login to continue', 'warning');
                }
                break;
            case 403:
                showToast(CONFIG.ERROR_MESSAGES.PERMISSION_ERROR, 'error');
                break;
            case 404:
                showToast(CONFIG.ERROR_MESSAGES.NOT_FOUND_ERROR, 'error');
                break;
            case 409:
                showToast(CONFIG.ERROR_MESSAGES.CONFLICT_ERROR, 'error');
                break;
            case 500:
                showToast(CONFIG.ERROR_MESSAGES.SERVER_ERROR, 'error');
                break;
            default:
                showToast(event.reason.message || CONFIG.ERROR_MESSAGES.NETWORK_ERROR, 'error');
        }
        
        event.preventDefault();
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        ApiClient,
        ApiError,
        authService,
        userService,
        moduleService,
        activityService,
        settingsService,
        companyService
    };
}