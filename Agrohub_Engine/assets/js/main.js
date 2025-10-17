// Agrohub ERP Platform - Main Application

class AgrohubApp {
    constructor() {
        this.isInitialized = false;
        this.currentTheme = 'light';
        this.currentLanguage = 'en';
        this.init();
    }

    async init() {
        if (this.isInitialized) return;

        try {
            // Show loading
            this.showInitialLoading();

            // Initialize core components
            await this.initializeCore();

            // Setup global event listeners
            this.setupGlobalEventListeners();

            // Initialize UI
            this.initializeUI();

            // Load user session
            await this.loadUserSession();

            // Initialize modules
            await this.initializeModules();

            // Hide loading
            this.hideInitialLoading();

            this.isInitialized = true;
            
            if (CONFIG.DEBUG) {
                console.log('Agrohub ERP Platform initialized successfully');
            }

        } catch (error) {
            console.error('Failed to initialize Agrohub ERP Platform:', error);
            this.handleInitializationError(error);
        }
    }

    // Show initial loading
    showInitialLoading() {
        const loadingHTML = `
            <div id="initial-loading" class="fixed inset-0 bg-white z-50 flex items-center justify-center">
                <div class="text-center">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-seedling text-white text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Agrohub ERP</h1>
                    <p class="text-gray-600 mb-6">Loading your business platform...</p>
                    <div class="w-48 h-2 bg-gray-200 rounded-full mx-auto">
                        <div class="h-2 bg-primary rounded-full animate-pulse" style="width: 70%"></div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('afterbegin', loadingHTML);
    }

    // Hide initial loading
    hideInitialLoading() {
        const loading = document.getElementById('initial-loading');
        if (loading) {
            loading.style.opacity = '0';
            setTimeout(() => {
                loading.remove();
            }, 300);
        }
    }

    // Initialize core components
    async initializeCore() {
        // Load saved preferences
        this.loadSavedPreferences();

        // Apply theme
        this.applyTheme(this.currentTheme);

        // Set language
        this.setLanguage(this.currentLanguage);

        // Initialize error handling
        this.setupErrorHandling();

        // Setup performance monitoring
        if (CONFIG.DEBUG) {
            this.setupPerformanceMonitoring();
        }
    }

    // Load saved user preferences
    loadSavedPreferences() {
        // Load theme
        const savedTheme = localStorage.getItem(CONFIG.STORAGE_KEYS.THEME);
        if (savedTheme && CONFIG.THEMES[savedTheme]) {
            this.currentTheme = savedTheme;
        }

        // Load language
        const savedLanguage = localStorage.getItem(CONFIG.STORAGE_KEYS.LANGUAGE);
        if (savedLanguage && CONFIG.LANGUAGES[savedLanguage]) {
            this.currentLanguage = savedLanguage;
        }
    }

    // Apply theme
    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(CONFIG.STORAGE_KEYS.THEME, theme);
        this.currentTheme = theme;
    }

    // Set language
    setLanguage(language) {
        document.documentElement.setAttribute('lang', language);
        localStorage.setItem(CONFIG.STORAGE_KEYS.LANGUAGE, language);
        this.currentLanguage = language;
    }

    // Setup error handling
    setupErrorHandling() {
        window.addEventListener('error', (event) => {
            console.error('Global error:', event.error);
            if (!CONFIG.DEBUG) {
                this.logError(event.error);
            }
        });

        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            if (!CONFIG.DEBUG) {
                this.logError(event.reason);
            }
        });
    }

    // Setup performance monitoring
    setupPerformanceMonitoring() {
        if ('performance' in window) {
            const navigationTiming = performance.getEntriesByType('navigation')[0];
            if (navigationTiming) {
                console.log('Page Load Performance:', {
                    domContentLoaded: navigationTiming.domContentLoadedEventEnd - navigationTiming.navigationStart,
                    fullyLoaded: navigationTiming.loadEventEnd - navigationTiming.navigationStart,
                    firstPaint: performance.getEntriesByName('first-paint')[0]?.startTime || 'N/A',
                    firstContentfulPaint: performance.getEntriesByName('first-contentful-paint')[0]?.startTime || 'N/A'
                });
            }
        }
    }

    // Setup global event listeners
    setupGlobalEventListeners() {
        // Handle online/offline status
        window.addEventListener('online', () => {
            showToast('Connection restored', 'success');
            this.handleOnlineStatus(true);
        });

        window.addEventListener('offline', () => {
            showToast('Connection lost - working offline', 'warning');
            this.handleOnlineStatus(false);
        });

        // Handle visibility change
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && window.authManager && window.authManager.isAuthenticated()) {
                // Refresh session when page becomes visible
                window.authManager.refreshSession();
            }
        });

        // Handle keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            this.handleKeyboardShortcuts(e);
        });

        // Handle page resize
        window.addEventListener('resize', debounce(() => {
            this.handleResize();
        }, 250));

        // Handle beforeunload
        window.addEventListener('beforeunload', (e) => {
            this.handleBeforeUnload(e);
        });
    }

    // Initialize UI
    initializeUI() {
        // Add loading states to buttons
        this.setupButtonLoadingStates();

        // Initialize tooltips
        this.initializeTooltips();

        // Setup smooth scrolling
        this.setupSmoothScrolling();

        // Initialize keyboard navigation
        this.setupKeyboardNavigation();
    }

    // Load user session
    async loadUserSession() {
        // Check if user is already authenticated
        if (window.authManager && window.authManager.isAuthenticated()) {
            // Load user modules
            if (window.appsManager) {
                await window.appsManager.loadUserModules();
            }

            // Show dashboard if user prefers it
            const showDashboard = localStorage.getItem('show_dashboard_on_load');
            if (showDashboard === 'true' && window.dashboardManager) {
                window.dashboardManager.show();
            }
        }
    }

    // Initialize modules
    async initializeModules() {
        // Initialize apps manager
        if (window.appsManager) {
            await window.appsManager.init();
        }

        // Initialize dashboard manager
        if (window.dashboardManager) {
            window.dashboardManager.init();
        }

        // Load feature flags
        this.loadFeatureFlags();
    }

    // Handle initialization error
    handleInitializationError(error) {
        console.error('Initialization error:', error);
        
        // Show error message to user
        const errorHTML = `
            <div class="fixed inset-0 bg-red-50 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Initialization Failed</h2>
                    <p class="text-gray-600 mb-6">There was an error loading the application. Please refresh the page and try again.</p>
                    <div class="space-y-2">
                        <button onclick="window.location.reload()" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 transition-colors">
                            Reload Page
                        </button>
                        ${CONFIG.DEBUG ? `
                            <button onclick="console.log('Error:', ${JSON.stringify(error.message)})" class="w-full bg-gray-600 text-white py-2 px-4 rounded-md hover:bg-gray-700 transition-colors">
                                Show Error Details
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', errorHTML);
    }

    // Handle online/offline status
    handleOnlineStatus(isOnline) {
        document.body.classList.toggle('offline', !isOnline);
        
        if (isOnline && window.authManager && window.authManager.isAuthenticated()) {
            // Sync any pending data when back online
            this.syncPendingData();
        }
    }

    // Handle keyboard shortcuts
    handleKeyboardShortcuts(event) {
        // Ctrl+K for search
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            this.showSearch();
        }

        // Esc to close modals
        if (event.key === 'Escape') {
            this.closeAllModals();
        }

        // Alt+D for dashboard
        if (event.altKey && event.key === 'd') {
            event.preventDefault();
            if (window.authManager && window.authManager.isAuthenticated()) {
                if (window.dashboardManager.isVisible()) {
                    window.dashboardManager.hide();
                } else {
                    window.dashboardManager.show();
                }
            }
        }
    }

    // Handle page resize
    handleResize() {
        // Adjust mobile menu
        const width = window.innerWidth;
        if (width >= 768) {
            const mobileMenu = document.getElementById('mobile-menu');
            if (mobileMenu) {
                mobileMenu.classList.add('hidden');
            }
        }

        // Trigger custom resize event
        window.dispatchEvent(new CustomEvent('agrohubResize', { detail: { width } }));
    }

    // Handle before unload
    handleBeforeUnload(event) {
        // Save current state
        if (window.authManager && window.authManager.isAuthenticated()) {
            localStorage.setItem('show_dashboard_on_load', window.dashboardManager.isVisible().toString());
        }

        // Check for unsaved changes
        if (this.hasUnsavedChanges()) {
            event.preventDefault();
            event.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return event.returnValue;
        }
    }

    // Setup button loading states
    setupButtonLoadingStates() {
        document.addEventListener('click', (event) => {
            const button = safeClosest(event.target, 'button');
            if (button && button.type === 'submit') {
                button.classList.add('loading');
                
                // Remove loading state after form submission
                setTimeout(() => {
                    button.classList.remove('loading');
                }, 3000);
            }
        });
    }

    // Initialize tooltips
    initializeTooltips() {
        // Simple tooltip implementation (use safeClosest to avoid errors on text nodes)
        document.addEventListener('mouseenter', (event) => {
            const element = safeClosest(event.target, '[data-tooltip]');
            if (element) {
                this.showTooltip(element, element.dataset.tooltip);
            }
        }, true); // useCapture to catch enter/leave reliably

        document.addEventListener('mouseleave', (event) => {
            const element = safeClosest(event.target, '[data-tooltip]');
            if (element) {
                this.hideTooltip();
            }
        }, true);
    }

    // Setup smooth scrolling
    setupSmoothScrolling() {
        document.addEventListener('click', (event) => {
            const link = safeClosest(event.target, 'a[href^="#"]');
            if (link) {
                event.preventDefault();
                const target = document.querySelector(link.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    }

    // Setup keyboard navigation
    setupKeyboardNavigation() {
        // Add focus styles for keyboard navigation
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });

        document.addEventListener('mousedown', () => {
            document.body.classList.remove('keyboard-navigation');
        });
    }

    // Load feature flags
    loadFeatureFlags() {
        Object.keys(CONFIG.FEATURES).forEach(feature => {
            const enabled = CONFIG.FEATURES[feature];
            document.body.classList.toggle(`feature-${feature.toLowerCase()}`, enabled);
        });
    }

    // Show search
    showSearch() {
        // Implement global search functionality
        showToast('Search functionality coming soon!', 'info');
    }

    // Close all modals
    closeAllModals() {
        const modals = document.querySelectorAll('.fixed.inset-0:not(#initial-loading)');
        modals.forEach(modal => {
            if (modal.classList.contains('flex')) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    }

    // Show tooltip
    showTooltip(element, text) {
        this.hideTooltip(); // Hide any existing tooltip

        const tooltip = document.createElement('div');
        tooltip.id = 'global-tooltip';
        tooltip.className = 'absolute bg-gray-900 text-white text-xs rounded py-1 px-2 z-50 pointer-events-none';
        tooltip.textContent = text;

        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.bottom + 5 + 'px';
    }

    // Hide tooltip
    hideTooltip() {
        const tooltip = document.getElementById('global-tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }

    // Check for unsaved changes
    hasUnsavedChanges() {
        // Implement logic to check for unsaved changes
        return false;
    }

    // Sync pending data
    async syncPendingData() {
        // Implement data synchronization logic
        if (CONFIG.DEBUG) {
            console.log('Syncing pending data...');
        }
    }

    // Log error
    logError(error) {
        // Implement error logging
        const errorData = {
            message: error.message || 'Unknown error',
            stack: error.stack,
            timestamp: new Date().toISOString(),
            url: window.location.href,
            userAgent: navigator.userAgent
        };

        // Send to error tracking service
        console.error('Logged error:', errorData);
    }

    // Get app instance
    static getInstance() {
        if (!window._agrohubApp) {
            window._agrohubApp = new AgrohubApp();
        }
        return window._agrohubApp;
    }

    // Utility methods
    getVersion() {
        return CONFIG.APP_VERSION;
    }

    isDebugMode() {
        return CONFIG.DEBUG;
    }

    getCurrentTheme() {
        return this.currentTheme;
    }

    getCurrentLanguage() {
        return this.currentLanguage;
    }
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Utility: safeClosest - ensures we call closest on an Element
function safeClosest(node, selector) {
    if (!node) return null;
    // If it's not an element (e.g., text node), climb to parentElement
    if (node.nodeType !== 1) {
        node = node.parentElement;
    }
    try {
        return node ? node.closest(selector) : null;
    } catch (e) {
        return null;
    }
}

function throttle(func, wait) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, wait);
        }
    };
}
