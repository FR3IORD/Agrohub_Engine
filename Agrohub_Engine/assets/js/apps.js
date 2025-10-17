// Agrohub ERP Platform - Apps Manager

class AppsManager {
    constructor() {
        this.modules = [];
        this.userModules = [];
        this.categories = {};
        this.init();
    }

    async init() {
        await this.loadModules();
        this.renderAppsGrid();
    }

    // Load modules from API
    async loadModules() {
        try {
            const response = await moduleService.getModules();

            // Normalize response shapes:
            // Accept: Array, { modules: [...] }, { data: [...] }, { success: true, modules: [...] }
            let modulesArray = [];
            if (Array.isArray(response)) {
                modulesArray = response;
            } else if (response && Array.isArray(response.modules)) {
                modulesArray = response.modules;
            } else if (response && Array.isArray(response.data)) {
                modulesArray = response.data;
            } else if (response && response.success && Array.isArray(response.modules)) {
                modulesArray = response.modules;
            } else {
                modulesArray = [];
            }

            // Ensure consistent field names: provide both id and technical_name
            modulesArray = modulesArray.map(m => {
                // clone to avoid mutating original
                const mod = Object.assign({}, m);
                if (!mod.technical_name && typeof mod.id === 'string') {
                    mod.technical_name = mod.id;
                }
                if (!mod.id && mod.technical_name) {
                    mod.id = mod.technical_name;
                }
                // Backwards compat: normalize category capitalization if needed
                if (mod.category && typeof mod.category === 'string') {
                    // keep original case for display, apps.js expects category names like 'Website' etc.
                    mod.category = mod.category;
                }
                return mod;
            });

            this.modules = modulesArray;
            this.categorizeModules();
        } catch (error) {
            console.error('Error loading modules:', error);
            // Fallback to local data for development
            if (CONFIG.DEBUG) {
                this.loadFallbackModules();
            }
        }
    }

    // Load user's installed modules
    async loadUserModules() {
        if (!authManager.isAuthenticated()) return;

        try {
            const response = await moduleService.getUserModules();

            let userModulesArray = [];
            if (Array.isArray(response)) {
                userModulesArray = response;
            } else if (response && Array.isArray(response.modules)) {
                userModulesArray = response.modules;
            } else if (response && Array.isArray(response.data)) {
                userModulesArray = response.data;
            } else if (response && response.success && Array.isArray(response.data)) {
                userModulesArray = response.data;
            } else {
                userModulesArray = [];
            }

            // Normalize installed modules fields
            userModulesArray = userModulesArray.map(m => {
                const mod = Object.assign({}, m);
                if (!mod.technical_name && typeof mod.id === 'string') mod.technical_name = mod.id;
                if (!mod.id && mod.technical_name) mod.id = mod.technical_name;
                return mod;
            });

            this.userModules = userModulesArray;
        } catch (error) {
            console.error('Error loading user modules:', error);
            // Load from localStorage for development
            if (CONFIG.DEBUG) {
                const stored = localStorage.getItem('user_modules');
                this.userModules = stored ? JSON.parse(stored) : [];
            }
        }
    }

    // Categorize modules by category
    categorizeModules() {
        this.categories = {};
        this.modules.forEach(module => {
            if (!this.categories[module.category]) {
                this.categories[module.category] = [];
            }
            this.categories[module.category].push(module);
        });
    }

    // Load fallback modules for development
    loadFallbackModules() {
        this.modules = [
            // Website
            { id: 1, name: 'Website', technical_name: 'website', category: 'Website', description: 'Build beautiful websites with drag-and-drop builder', icon: 'fas fa-globe', color: 'bg-blue-500', dependencies: [], features: ['Drag & Drop Builder', 'SEO Optimization', 'Mobile Responsive', 'Multi-language'] },
            { id: 2, name: 'eCommerce', technical_name: 'ecommerce', category: 'Website', description: 'Online store with payment processing', icon: 'fas fa-shopping-cart', color: 'bg-purple-500', dependencies: ['website'], features: ['Product Catalog', 'Payment Gateway', 'Inventory Integration', 'Order Management'] },
            { id: 3, name: 'Blog', technical_name: 'blog', category: 'Website', description: 'Share your thoughts and engage with your audience', icon: 'fas fa-blog', color: 'bg-green-500', dependencies: ['website'], features: ['Blog Posts', 'Comments', 'SEO', 'Social Sharing'] },
            { id: 4, name: 'Forum', technical_name: 'forum', category: 'Website', description: 'Create community discussions and knowledge sharing', icon: 'fas fa-comments', color: 'bg-indigo-500', dependencies: ['website'], features: ['Discussion Forums', 'User Moderation', 'Categories', 'Search'] },
            { id: 5, name: 'eLearning', technical_name: 'elearning', category: 'Website', description: 'Create and sell online courses and training', icon: 'fas fa-graduation-cap', color: 'bg-orange-500', dependencies: ['website'], features: ['Course Builder', 'Video Streaming', 'Quizzes', 'Certificates'] },
            { id: 6, name: 'Events', technical_name: 'events', category: 'Website', description: 'Organize and manage events with ticketing', icon: 'fas fa-calendar', color: 'bg-red-500', dependencies: ['website'], features: ['Event Management', 'Ticketing', 'Registration', 'Check-in'] },

            // Sales
            { id: 7, name: 'CRM', technical_name: 'crm', category: 'Sales', description: 'Customer relationship management', icon: 'fas fa-users', color: 'bg-teal-500', dependencies: [], features: ['Lead Management', 'Contact Database', 'Sales Pipeline', 'Activity Tracking'] },
            { id: 8, name: 'Sales', technical_name: 'sales', category: 'Sales', description: 'Sales management and quotations', icon: 'fas fa-chart-line', color: 'bg-yellow-500', dependencies: ['crm'], features: ['Quotations', 'Sales Orders', 'Commission Tracking', 'Sales Analytics'] },
            { id: 9, name: 'Point of Sale', technical_name: 'pos', category: 'Sales', description: 'Modern POS system for retail and restaurants', icon: 'fas fa-cash-register', color: 'bg-pink-500', dependencies: [], features: ['Touch Interface', 'Barcode Scanner', 'Receipt Printer', 'Inventory Integration'] },

            // Finance
            { id: 10, name: 'Invoicing', technical_name: 'invoicing', category: 'Finance', description: 'Create and send professional invoices', icon: 'fas fa-file-invoice', color: 'bg-blue-500', dependencies: [], features: ['Invoice Templates', 'Automatic Numbering', 'Payment Tracking', 'Recurring Invoices'] },
            { id: 11, name: 'Accounting', technical_name: 'accounting', category: 'Finance', description: 'Complete accounting and financial management', icon: 'fas fa-calculator', color: 'bg-red-500', dependencies: ['invoicing'], features: ['Chart of Accounts', 'Journal Entries', 'Financial Reports', 'Tax Management'] },
            { id: 12, name: 'Expenses', technical_name: 'expenses', category: 'Finance', description: 'Track and manage business expenses', icon: 'fas fa-receipt', color: 'bg-green-500', dependencies: ['accounting'], features: ['Expense Reports', 'Receipt Scanning', 'Approval Workflow', 'Reimbursements'] },

            // Services
            { id: 13, name: 'Project', technical_name: 'project', category: 'Services', description: 'Manage projects and track progress', icon: 'fas fa-tasks', color: 'bg-green-500', dependencies: [], features: ['Project Planning', 'Task Management', 'Gantt Charts', 'Time Tracking'] },
            { id: 14, name: 'Timesheets', technical_name: 'timesheets', category: 'Services', description: 'Track time and manage timesheets', icon: 'fas fa-clock', color: 'bg-blue-500', dependencies: ['project'], features: ['Time Tracking', 'Timesheet Approval', 'Billing Integration', 'Reports'] },
            { id: 15, name: 'Helpdesk', technical_name: 'helpdesk', category: 'Services', description: 'Customer support and ticket management', icon: 'fas fa-headset', color: 'bg-purple-500', dependencies: ['crm'], features: ['Ticket Management', 'Knowledge Base', 'Live Chat', 'SLA Management'] },

            // Human Resources
            { id: 16, name: 'Employees', technical_name: 'employees', category: 'Human Resources', description: 'Employee database and management', icon: 'fas fa-user-friends', color: 'bg-blue-500', dependencies: [], features: ['Employee Profiles', 'Organization Chart', 'Document Management', 'Performance Tracking'] },
            { id: 17, name: 'Recruitment', technical_name: 'recruitment', category: 'Human Resources', description: 'Streamline your hiring process', icon: 'fas fa-search', color: 'bg-green-500', dependencies: ['employees'], features: ['Job Postings', 'Application Tracking', 'Interview Scheduling', 'Candidate Portal'] },
            { id: 18, name: 'Payroll', technical_name: 'payroll', category: 'Human Resources', description: 'Payroll processing and management', icon: 'fas fa-money-check-alt', color: 'bg-red-500', dependencies: ['employees', 'accounting'], features: ['Salary Calculation', 'Tax Deductions', 'Payslips', 'Bank Integration'] },

            // Supply Chain
            { id: 19, name: 'Inventory', technical_name: 'inventory', category: 'Supply Chain', description: 'Inventory management and tracking', icon: 'fas fa-boxes', color: 'bg-red-500', dependencies: [], features: ['Stock Management', 'Barcode Scanning', 'Inventory Reports', 'Automated Reordering'] },
            { id: 20, name: 'Purchase', technical_name: 'purchase', category: 'Supply Chain', description: 'Purchase orders and procurement', icon: 'fas fa-shopping-bag', color: 'bg-purple-500', dependencies: ['inventory'], features: ['Purchase Orders', 'Vendor Management', 'Price Comparison', 'Approval Workflow'] },
            { id: 21, name: 'Manufacturing', technical_name: 'manufacturing', category: 'Supply Chain', description: 'Manufacturing operations and planning', icon: 'fas fa-industry', color: 'bg-blue-500', dependencies: ['inventory'], features: ['Bill of Materials', 'Work Orders', 'Quality Control', 'Production Planning'] },

            // Marketing
            { id: 22, name: 'Email Marketing', technical_name: 'email_marketing', category: 'Marketing', description: 'Create and send email campaigns', icon: 'fas fa-envelope', color: 'bg-blue-500', dependencies: ['crm'], features: ['Campaign Builder', 'Email Templates', 'Analytics', 'Automation'] },
            { id: 23, name: 'SMS Marketing', technical_name: 'sms_marketing', category: 'Marketing', description: 'Send SMS campaigns and notifications', icon: 'fas fa-sms', color: 'bg-green-500', dependencies: ['crm'], features: ['SMS Campaigns', 'Bulk Messaging', 'Delivery Reports', 'Two-way SMS'] },

            // Productivity
            { id: 24, name: 'Documents', technical_name: 'documents', category: 'Productivity', description: 'Document management and collaboration', icon: 'fas fa-file-alt', color: 'bg-blue-500', dependencies: [], features: ['File Storage', 'Version Control', 'Collaboration', 'Search'] },

            // Customizations
            { id: 25, name: 'Studio', technical_name: 'studio', category: 'Customizations', description: 'Custom app builder and modifications', icon: 'fas fa-code', color: 'bg-gray-500', dependencies: [], features: ['Drag & Drop Builder', 'Custom Fields', 'Workflows', 'Reports'] }
        ];
        this.categorizeModules();
    }

    // Render apps grid
    renderAppsGrid() {
        const container = document.getElementById('apps-grid');
        const loadingElement = document.getElementById('loading-apps');
        
        if (loadingElement) {
            loadingElement.style.display = 'none';
        }

        if (!container) return;

        let html = '';

        CONFIG.MODULE_CATEGORIES.forEach(categoryName => {
            const modules = this.categories[categoryName] || [];
            if (modules.length === 0) return;

            html += `
                <div class="mb-12">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-8 category-title" style="font-style: italic;">${categoryName}</h2>
                    <div class="apps-grid">
                        ${modules.map(module => this.renderModuleCard(module)).join('')}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Render individual module card
    renderModuleCard(module) {
        const isInstalled = this.isModuleInstalled(module.technical_name);
        const installButtonText = isInstalled ? 'Installed' : 'Install';
        const installButtonClass = isInstalled ? 'bg-green-500 cursor-not-allowed' : 'bg-primary hover:bg-primary-dark';
        const installButtonAction = isInstalled ? '' : `onclick="appsManager.installModule('${module.technical_name}', '${module.name}')"`;

        return `
            <div class="bg-white rounded-lg border border-gray-200 p-6 hover:shadow-lg transition-shadow cursor-pointer app-card">
                <div class="flex items-center mb-4">
                    <div class="w-10 h-10 ${module.color} rounded-lg flex items-center justify-center mr-4">
                        <i class="${module.icon} text-white"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-gray-900">${module.name}</h3>
                        <span class="text-xs text-gray-500">${module.category}</span>
                    </div>
                </div>
                <p class="text-gray-600 text-sm mb-4">${module.description}</p>
                
                ${module.features && module.features.length > 0 ? `
                    <div class="mb-4">
                        <h4 class="text-xs font-medium text-gray-700 mb-2">Features:</h4>
                        <div class="flex flex-wrap gap-1">
                            ${module.features.slice(0, 3).map(feature => `
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    ${feature}
                                </span>
                            `).join('')}
                            ${module.features.length > 3 ? 
                                `<span class="text-xs text-gray-500">+${module.features.length - 3} more</span>` : ''
                            }
                        </div>
                    </div>
                ` : ''}
                
                <div class="flex items-center justify-between mt-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">v${module.version}</span>
                        ${installButtonAction ? `
                            <span class="text-xs px-2 py-1 rounded-full ${isInstalled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}">
                                ${isInstalled ? 'Installed' : 'Available'}
                            </span>
                        ` : ''}
                    </div>
                    
                    ${installButtonAction ? `
                        <button 
                            ${installButtonAction}
                            class="px-4 py-2 rounded-lg font-medium transition-colors ${isInstalled ? 'bg-red-500 hover:bg-red-600 text-white' : 'bg-blue-500 hover:bg-blue-600 text-white'}"
                        >
                            ${isInstalled ? 'Uninstall' : 'Install'}
                        </button>
                    ` : `
                        <span class="text-sm text-gray-500">Contact admin to install</span>
                    `}
                </div>
            </div>
        `;
    }

    // Check if module is installed
    isModuleInstalled(technicalName) {
        return this.userModules.some(module => module.technical_name === technicalName);
    }

    // Install module
    async installModule(technicalName, moduleName) {
        if (!authManager.isAuthenticated()) {
            showToast('Please login to install modules', 'warning');
            authManager.showLogin();
            return;
        }

        const module = this.modules.find(m => m.technical_name === technicalName);
        if (!module) {
            showToast('Module not found', 'error');
            return;
        }

        // Check if already installed
        if (this.isModuleInstalled(technicalName)) {
            showToast('Module is already installed', 'warning');
            return;
        }

        // Check dependencies
        const missingDeps = this.checkDependencies(module.dependencies);
        if (missingDeps.length > 0) {
            const depNames = missingDeps.map(dep => {
                const depModule = this.modules.find(m => m.technical_name === dep);
                return depModule ? depModule.name : dep;
            });
            
            if (confirm(`This module requires: ${depNames.join(', ')}. Install dependencies first?`)) {
                await this.installDependencies(missingDeps);
            } else {
                return;
            }
        }

        this.showInstallModal(moduleName);
        
        try {
            await this.performInstallation(module);
            this.hideInstallModal();
            showToast(`${moduleName} installed successfully!`, 'success');
            
            // Update UI
            await this.loadUserModules();
            this.renderAppsGrid();
            
            // Update dashboard if visible
            if (window.dashboardManager && window.dashboardManager.isVisible()) {
                window.dashboardManager.updateStats();
                window.dashboardManager.updateInstalledApps();
            }
            
            // Log activity
            this.logActivity(`Installed ${moduleName}`, 'Module Installation');
            
        } catch (error) {
            this.hideInstallModal();
            console.error('Installation error:', error);
            showToast(`Failed to install ${moduleName}: ${error.message}`, 'error');
        }
    }

    // Check module dependencies
    checkDependencies(dependencies) {
        if (!dependencies || dependencies.length === 0) return [];
        
        return dependencies.filter(dep => !this.isModuleInstalled(dep));
    }

    // Install dependencies
    async installDependencies(dependencies) {
        for (const dep of dependencies) {
            const depModule = this.modules.find(m => m.technical_name === dep);
            if (depModule && !this.isModuleInstalled(dep)) {
                await this.installModule(dep, depModule.name);
            }
        }
    }

    // Show installation modal
    showInstallModal(moduleName) {
        const modal = document.getElementById('install-modal');
        const nameElement = document.getElementById('install-app-name');
        const progressElement = document.getElementById('install-progress');
        const statusElement = document.getElementById('install-status');
        
        if (modal && nameElement && progressElement && statusElement) {
            nameElement.textContent = `Installing ${moduleName}...`;
            progressElement.style.width = '0%';
            statusElement.textContent = 'Preparing installation...';
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    // Hide installation modal
    hideInstallModal() {
        const modal = document.getElementById('install-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // Perform module installation
    async performInstallation(module) {
        const progressElement = document.getElementById('install-progress');
        const statusElement = document.getElementById('install-status');
        
        const steps = CONFIG.INSTALLATION_STEPS;
        
        for (let i = 0; i < steps.length; i++) {
            const progress = ((i + 1) / steps.length) * 100;
            
            if (progressElement) {
                progressElement.style.width = `${progress}%`;
            }
            
            if (statusElement) {
                statusElement.textContent = steps[i];
            }
            
            // Simulate installation time
            await this.delay(500 + Math.random() * 1000);
        }

        // Try to install via API
        try {
            await moduleService.installModule(module.id);
            
            // Add to user modules
            this.userModules.push({
                ...module,
                status: 'active',
                installed_at: new Date().toISOString()
            });
            
        } catch (error) {
            // Fallback for development mode
            if (CONFIG.DEBUG) {
                this.userModules.push({
                    ...module,
                    status: 'active',
                    installed_at: new Date().toISOString()
                });
                localStorage.setItem('user_modules', JSON.stringify(this.userModules));
            } else {
                throw error;
            }
        }
    }

    // Uninstall module
    async uninstallModule(technicalName, moduleName, event) {
        if (event) event.stopPropagation();
        
        if (!confirm(`Are you sure you want to uninstall ${moduleName}?`)) {
            return;
        }

        const module = this.userModules.find(m => m.technical_name === technicalName);
        if (!module) {
            showToast('Module not found', 'error');
            return;
        }

        try {
            showLoading();
            
            // Check if other modules depend on this one
            const dependentModules = this.userModules.filter(m => 
                m.dependencies && m.dependencies.includes(technicalName)
            );
            
            if (dependentModules.length > 0) {
                const depNames = dependentModules.map(m => m.name);
                showToast(`Cannot uninstall. Required by: ${depNames.join(', ')}`, 'error');
                return;
            }

            // Try to uninstall via API
            try {
                await moduleService.uninstallModule(module.id);
            } catch (error) {
                if (!CONFIG.DEBUG) throw error;
            }

            // Remove from user modules
            this.userModules = this.userModules.filter(m => m.technical_name !== technicalName);
            
            if (CONFIG.DEBUG) {
                localStorage.setItem('user_modules', JSON.stringify(this.userModules));
            }

            showToast(`${moduleName} uninstalled successfully!`, 'success');
            
            // Update UI
            this.renderAppsGrid();
            
            // Update dashboard if visible
            if (window.dashboardManager && window.dashboardManager.isVisible()) {
                window.dashboardManager.updateStats();
                window.dashboardManager.updateInstalledApps();
            }
            
            // Log activity
            this.logActivity(`Uninstalled ${moduleName}`, 'Module Removal');
            
        } catch (error) {
            console.error('Uninstallation error:', error);
            showToast(`Failed to uninstall ${moduleName}: ${error.message}`, 'error');
        } finally {
            hideLoading();
        }
    }

    // Open module
    openModule(technicalName) {
        const module = this.userModules.find(m => m.technical_name === technicalName);
        if (!module) {
            showToast('Module not found or not installed', 'error');
            return;
        }

        // For now, show a placeholder
        showToast(`Opening ${module.name} module...`, 'info');
        
        // In a real application, this would redirect to the module's interface
        // window.location.href = `/modules/${technicalName}`;
        
        // Log activity
        this.logActivity(`Opened ${module.name}`, 'Module Access');
    }

    // Show module details
    showModuleDetails(technicalName) {
        const module = this.modules.find(m => m.technical_name === technicalName);
        if (!module) {
            showToast('Module not found', 'error');
            return;
        }

        // Create modal for module details
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 ${module.color} rounded-lg flex items-center justify-center mr-4">
                                <i class="${module.icon} text-white text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900">${module.name}</h2>
                                <p class="text-gray-600">${module.category}</p>
                            </div>
                        </div>
                        <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Description</h3>
                            <p class="text-gray-600">${module.description}</p>
                        </div>

                        ${module.features && module.features.length > 0 ? `
                            <div>
                                <h3 class="text-lg font-semibold mb-2">Features</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    ${module.features.map(feature => `
                                        <div class="flex items-center">
                                            <i class="fas fa-check text-green-500 mr-2"></i>
                                            <span class="text-gray-700">${feature}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}

                        ${module.dependencies && module.dependencies.length > 0 ? `
                            <div>
                                <h3 class="text-lg font-semibold mb-2">Dependencies</h3>
                                <div class="space-y-2">
                                    ${module.dependencies.map(dep => {
                                        const depModule = this.modules.find(m => m.technical_name === dep);
                                        const depName = depModule ? depModule.name : dep;
                                        const isInstalled = this.isModuleInstalled(dep);
                                        return `
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                                <span class="text-gray-700">${depName}</span>
                                                <span class="text-sm ${isInstalled ? 'text-green-600' : 'text-red-600'}">
                                                    ${isInstalled ? 'Installed' : 'Not Installed'}
                                                </span>
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        ` : ''}

                        <div class="flex justify-end space-x-3">
                            <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                                Close
                            </button>
                            ${!this.isModuleInstalled(technicalName) ? `
                                <button onclick="appsManager.installModule('${technicalName}', '${module.name}'); this.closest('.fixed').remove();" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">
                                    Install Module
                                </button>
                            ` : `
                                <button onclick="appsManager.openModule('${technicalName}'); this.closest('.fixed').remove();" class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark">
                                    Open Module
                                </button>
                            `}
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    }

    // Log activity
    logActivity(description, type) {
        if (window.dashboardManager) {
            window.dashboardManager.addActivity(description, type);
        }
    }

    // Utility function for delays
    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Get installed modules
    getInstalledModules() {
        return this.userModules;
    }

    // Get all modules
    getAllModules() {
        return this.modules;
    }

    // Search modules
    searchModules(query) {
        const lowercaseQuery = query.toLowerCase();
        return this.modules.filter(module => 
            module.name.toLowerCase().includes(lowercaseQuery) ||
            module.description.toLowerCase().includes(lowercaseQuery) ||
            module.category.toLowerCase().includes(lowercaseQuery)
        );
    }

    // Filter modules by category
    filterByCategory(category) {
        return this.categories[category] || [];
    }
}

// Initialize apps manager
const appsManager = new AppsManager();

// Make it globally available
window.appsManager = appsManager;

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AppsManager;
}