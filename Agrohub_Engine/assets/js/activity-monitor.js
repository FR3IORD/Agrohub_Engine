/**
 * Agrohub ERP - User Activity Monitor
 * 
 * Tracks user activity and session management
 */

class ActivityMonitor {
    constructor(options = {}) {
        this.options = {
            inactivityTimeout: 30 * 60 * 1000, // 30 minutes in milliseconds
            checkInterval: 60 * 1000, // 1 minute check interval
            warningTime: 5 * 60 * 1000, // Warning 5 minutes before timeout
            apiEndpoint: '/Agrohub_Engine/api/auth.php?action=extend',
            debug: false,
            ...options
        };
        
        this.lastActivity = Date.now();
        this.timerId = null;
        this.warningShown = false;
        this.warningDialog = null;
        
        this.init();
    }
    
    init() {
        // Attach activity listeners
        document.addEventListener('mousemove', this.resetTimer.bind(this));
        document.addEventListener('mousedown', this.resetTimer.bind(this));
        document.addEventListener('keypress', this.resetTimer.bind(this));
        document.addEventListener('touchmove', this.resetTimer.bind(this));
        document.addEventListener('scroll', this.resetTimer.bind(this));
        
        // Start monitoring
        this.startTimer();
        
        this.log('Activity monitor initialized');
    }
    
    resetTimer() {
        this.lastActivity = Date.now();
        
        if (this.warningShown) {
            this.hideWarning();
        }
        
        this.log('Activity detected, timer reset');
    }
    
    startTimer() {
        // Clear existing timer
        if (this.timerId) {
            clearInterval(this.timerId);
        }
        
        // Start new timer
        this.timerId = setInterval(() => this.checkInactivity(), this.options.checkInterval);
        this.log('Timer started');
    }
    
    checkInactivity() {
        const now = Date.now();
        const elapsed = now - this.lastActivity;
        
        this.log(`Checking inactivity: ${Math.round(elapsed / 1000)}s elapsed`);
        
        // Show warning when approaching timeout
        if (elapsed >= (this.options.inactivityTimeout - this.options.warningTime) && !this.warningShown) {
            this.showWarning();
        }
        
        // Logout if inactive for too long
        if (elapsed >= this.options.inactivityTimeout) {
            this.logout();
        }
    }
    
    showWarning() {
        this.warningShown = true;
        
        // Create warning dialog
        this.warningDialog = document.createElement('div');
        this.warningDialog.className = 'fixed inset-0 flex items-center justify-center z-50 bg-black bg-opacity-50';
        this.warningDialog.innerHTML = `
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full">
                <div class="text-xl font-bold mb-4 text-red-600">Session Expiring Soon</div>
                <p class="text-gray-700 mb-6">Your session will expire due to inactivity. Click "Continue" to stay logged in.</p>
                <div class="flex justify-end space-x-4">
                    <button id="session-logout" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400 transition-colors">Logout</button>
                    <button id="session-continue" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">Continue</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.warningDialog);
        
        // Add button event listeners
        document.getElementById('session-continue').addEventListener('click', () => {
            this.extendSession();
            this.hideWarning();
        });
        
        document.getElementById('session-logout').addEventListener('click', () => {
            this.logout();
        });
        
        this.log('Warning displayed');
    }
    
    hideWarning() {
        if (this.warningDialog && this.warningDialog.parentNode) {
            this.warningDialog.parentNode.removeChild(this.warningDialog);
            this.warningDialog = null;
            this.warningShown = false;
            this.log('Warning hidden');
        }
    }
    
    async extendSession() {
        try {
            const token = localStorage.getItem('auth_token');
            
            if (!token) {
                this.log('No auth token found');
                return;
            }
            
            const response = await fetch(this.options.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });
            
            if (response.ok) {
                this.log('Session extended successfully');
                this.resetTimer();
            } else {
                this.log('Failed to extend session');
                this.logout();
            }
            
        } catch (error) {
            this.log('Error extending session:', error);
            this.logout();
        }
    }
    
    logout() {
        this.log('Logging out due to inactivity');
        
        // Clear local storage
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user_data');
        
        // Remove auth cookie
        document.cookie = 'auth_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
        
        // Show notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span>Session expired due to inactivity</span>
            </div>
        `;
        document.body.appendChild(notification);
        
        // Redirect to login after a short delay
        setTimeout(() => {
            window.location.href = '/Agrohub_Engine/index.html';
        }, 2000);
    }
    
    log(...args) {
        if (this.options.debug) {
            console.log('[ActivityMonitor]', ...args);
        }
    }
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if user is logged in
    if (localStorage.getItem('auth_token')) {
        window.activityMonitor = new ActivityMonitor({
            debug: true, // Set to false in production
            inactivityTimeout: 30 * 60 * 1000 // 30 minutes
        });
    }
});
