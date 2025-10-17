/**
 * Agrohub Dashboard Handler
 */

class AgrohubDashboard {
    constructor() {
        this.currentUser = null;
        this.modules = [];
    }

    init() {
        this.currentUser = AgrohubUtils.getUserData();
        if (this.currentUser) {
            this.loadModules();
        }
    }

    async loadModules() {
        try {
            const result = await AgrohubUtils.request('/modules.php');
            
            if (result.success && result.data.modules) {
                this.modules = result.data.modules;
                this.renderModules();
            } else {
                console.error('Failed to load modules:', result.data);
            }
        } catch (error) {
            console.error('Error loading modules:', error);
        }
    }

    renderModules() {
        const mainContent = document.getElementById('mainContent');
        if (!mainContent) return;

        const modulesByCategory = this.groupModulesByCategory();
        
        let html = `
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Available Applications</h2>
                <p class="text-gray-600">Choose from our collection of business applications</p>
            </div>
        `;

        Object.keys(modulesByCategory).forEach(category => {
            html += `
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">${category}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        ${modulesByCategory[category].map(module => this.renderModuleCard(module)).join('')}
                    </div>
                </div>
            `;
        });

        mainContent.innerHTML = html;
        this.attachModuleEventListeners();
    }

    groupModulesByCategory() {
        const grouped = {};
        
        this.modules.forEach(module => {
            const category = module.category || 'Other';
            if (!grouped[category]) {
                grouped[category] = [];
            }
            grouped[category].push(module);
        });

        return grouped;
    }

    renderModuleCard(module) {
        const isInstalled = module.installed;
        
        return `
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition-shadow p-6 cursor-pointer module-card" 
                 data-module-id="${module.id}">
                <div class="flex items-start justify-between mb-4">
                    <div class="w-12 h-12 ${module.color} rounded-lg flex items-center justify-center">
                        <i class="${module.icon} text-white text-xl"></i>
                    </div>
                    ${isInstalled ? 
                        '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Installed</span>' : 
                        '<span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">Available</span>'
                    }
                </div>
                
                <h4 class="text-lg font-semibold text-gray-900 mb-2">${module.name}</h4>
                <p class="text-gray-600 text-sm mb-4">${module.description}</p>
                
                <div class="flex flex-wrap gap-1 mb-4">
                    ${module.features?.slice(0, 3).map(feature => 
                        `<span class="bg-blue-50 text-blue-700 text-xs px-2 py-1 rounded">${feature}</span>`
                    ).join('') || ''}
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500">v${module.version}</span>
                    <button class="module-action-btn ${isInstalled ? 'bg-red-500 hover:bg-red-600' : 'bg-blue-500 hover:bg-blue-600'} 
                                   text-white px-4 py-2 rounded text-sm font-medium transition-colors"
                            data-module-id="${module.id}" 
                            data-action="${isInstalled ? 'uninstall' : 'install'}">
                        ${isInstalled ? 'Uninstall' : 'Install'}
                    </button>
                </div>
            </div>
        `;
    }

    attachModuleEventListeners() {
        // Module card clicks
        document.querySelectorAll('.module-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (!e.target.classList.contains('module-action-btn')) {
                    const moduleId = card.dataset.moduleId;
                    this.showModuleDetails(moduleId);
                }
            });
        });

        // Action button clicks
        document.querySelectorAll('.module-action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const moduleId = btn.dataset.moduleId;
                const action = btn.dataset.action;
                
                if (action === 'install') {
                    this.installModule(moduleId);
                } else {
                    this.uninstallModule(moduleId);
                }
            });
        });
    }

    async installModule(moduleId) {
        try {
            const result = await AgrohubUtils.request('/modules.php', {
                method: 'POST',
                body: JSON.stringify({ module_id: moduleId })
            });

            if (result.success) {
                AgrohubUtils.showNotification('Module installed successfully!', 'success');
                this.loadModules(); // Reload to update UI
            } else {
                AgrohubUtils.showNotification(result.data?.error || 'Installation failed', 'error');
            }
        } catch (error) {
            console.error('Install error:', error);
            AgrohubUtils.showNotification('Installation failed', 'error');
        }
    }

    async uninstallModule(moduleId) {
        if (!confirm('Are you sure you want to uninstall this module?')) {
            return;
        }

        try {
            const result = await AgrohubUtils.request(`/modules.php?module_id=${moduleId}`, {
                method: 'DELETE'
            });

            if (result.success) {
                AgrohubUtils.showNotification('Module uninstalled successfully!', 'success');
                this.loadModules(); // Reload to update UI
            } else {
                AgrohubUtils.showNotification(result.data?.error || 'Uninstallation failed', 'error');
            }
        } catch (error) {
            console.error('Uninstall error:', error);
            AgrohubUtils.showNotification('Uninstallation failed', 'error');
        }
    }

    showModuleDetails(moduleId) {
        const module = this.modules.find(m => m.id === moduleId);
        if (!module) return;

        // Create modal or navigate to details page
        AgrohubUtils.showNotification(`Details for ${module.name}`, 'info');
    }
}

// Initialize dashboard when needed
window.AgrohubDashboard = AgrohubDashboard;