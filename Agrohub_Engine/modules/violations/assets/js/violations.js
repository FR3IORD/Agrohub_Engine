/**
 * Violations Module - Frontend JavaScript
 * Handles client-side logic and interactions
 */

const ViolationsModule = {
    authToken: null,
    currentUser: null,
    permissions: null,
    
    /**
     * Initialize module
     */
    init() {
        this.authToken = localStorage.getItem('auth_token');
        
        if (!this.authToken) {
            window.location.href = '../../index.html';
            return;
        }
        
        console.log('✅ Violations Module initialized');
    },
    
    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    },
    
    /**
     * Confirm dialog
     */
    confirm(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },
    
    /**
     * Format date
     */
    formatDate(dateString, format = 'Y-m-d') {
        if (!dateString) return 'N/A';
        
        const date = new Date(dateString);
        
        if (format === 'Y-m-d') {
            return date.toISOString().split('T')[0];
        } else if (format === 'Y-m-d H:i') {
            return date.toISOString().slice(0, 16).replace('T', ' ');
        } else if (format === 'human') {
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }
        
        return dateString;
    },
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * API call helper
     */
    async apiCall(endpoint, options = {}) {
        try {
            const defaultOptions = {
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.authToken}`
                }
            };
            
            const response = await fetch(endpoint, {
                ...defaultOptions,
                ...options,
                headers: {
                    ...defaultOptions.headers,
                    ...options.headers
                }
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
};

// Auto-initialize if on violations page
if (window.location.pathname.includes('/violations/')) {
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof ViolationsModule !== 'undefined') {
            ViolationsModule.init();
        }
    });
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);