/**
 * Agrohub Authentication Handler - FIXED VERSION
 */

class AgrohubAuth {
    constructor() {
        this.initializeAuth();
        this.setupEventListeners();
        this.checkExistingAuth();
    }

    initializeAuth() {
        this.loginForm = document.getElementById('loginForm');
        this.registerForm = document.getElementById('registrationForm');
        this.dashboardContainer = document.getElementById('dashboardContainer');
        
        // Form elements
        this.identifierInput = document.getElementById('identifier');
        this.passwordInput = document.getElementById('password');
        this.loginButton = document.getElementById('loginButton');
        
        // Error/Success messages
        this.errorMessage = document.getElementById('errorMessage');
        this.successMessage = document.getElementById('successMessage');
        this.errorText = document.getElementById('errorText');
        this.successText = document.getElementById('successText');

        // Loading states
        this.loginButtonText = document.getElementById('loginButtonText');
        this.loginButtonLoading = document.getElementById('loginButtonLoading');
        this.registerButtonText = document.getElementById('registerButtonText');
        this.registerButtonLoading = document.getElementById('registerButtonLoading');
    }

    setupEventListeners() {
        // Login form submission
        if (this.loginForm) {
            this.loginForm.addEventListener('submit', (e) => this.handleLogin(e));
        }

        // Register form submission
        if (this.registerForm) {
            this.registerForm.addEventListener('submit', (e) => this.handleRegister(e));
        }

        // Toggle between login and register
        const showRegister = document.getElementById('showRegister');
        const showLogin = document.getElementById('showLogin');
        
        if (showRegister) {
            showRegister.addEventListener('click', () => this.showRegisterForm());
        }
        
        if (showLogin) {
            showLogin.addEventListener('click', () => this.showLoginForm());
        }

        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        if (togglePassword) {
            togglePassword.addEventListener('click', () => this.togglePasswordVisibility());
        }

        // Clear messages when user starts typing
        if (this.identifierInput) {
            this.identifierInput.addEventListener('input', () => this.clearMessages());
        }
        if (this.passwordInput) {
            this.passwordInput.addEventListener('input', () => this.clearMessages());
        }
    }

    async handleLogin(event) {
        event.preventDefault();
        
        this.clearMessages();
        this.showLoading(true);

        const formData = new FormData(this.loginForm);
        const identifier = formData.get('identifier');
        const password = formData.get('password');

        // Basic validation
        if (!identifier || !password) {
            this.showError('გთხოვთ შეავსოთ ყველა ველი');
            this.showLoading(false);
            return;
        }

        try {
            const result = await AgrohubUtils.request('/auth.php?action=login', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'login',
                    identifier: identifier,
                    password: password
                })
            });

            console.log('Login result:', result);

            if (result.success && result.data.token) {
                // Login successful
                AgrohubUtils.setToken(result.data.token);
                AgrohubUtils.setUserData(result.data.user);
                
                this.showSuccess('წარმატებით შეხვედით! იტვირთება დაშბორდი...');
                
                // Small delay before loading dashboard
                setTimeout(() => {
                    this.loadDashboard(result.data.user);
                }, 1000);
                
            } else {
                // Login failed
                const errorMsg = result.data?.message || 'შესვლა ვერ მოხერხდა. შეამოწმეთ თქვენი მონაცემები.';
                this.showError(errorMsg);
            }

        } catch (error) {
            console.error('Login error:', error);
            this.showError('კავშირის შეცდომა. გთხოვთ ხელახლა სცადოთ.');
        }

        this.showLoading(false);
    }

    async handleRegister(event) {
        event.preventDefault();
        
        this.clearMessages();
        this.showRegisterLoading(true);

        const formData = new FormData(this.registerForm);
        const name = formData.get('name');
        const email = formData.get('email');
        const password = formData.get('password');
        const company = formData.get('company');

        // Basic validation
        if (!name || !email || !password) {
            this.showError('Please fill in all required fields');
            this.showRegisterLoading(false);
            return;
        }

        if (!AgrohubUtils.validateEmail(email)) {
            this.showError('Please enter a valid email address');
            this.showRegisterLoading(false);
            return;
        }

        if (password.length < 6) {
            this.showError('Password must be at least 6 characters long');
            this.showRegisterLoading(false);
            return;
        }

        try {
            const result = await AgrohubUtils.request('/auth.php?action=register', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'register',
                    name: name,
                    email: email,
                    password: password,
                    company: company
                })
            });

            if (result.success && result.data.token) {
                // Registration successful
                AgrohubUtils.setToken(result.data.token);
                AgrohubUtils.setUserData(result.data.user);
                
                this.showSuccess('Account created successfully! Loading dashboard...');
                
                setTimeout(() => {
                    this.loadDashboard(result.data.user);
                }, 1000);
                
            } else {
                const errorMsg = result.data?.message || result.data?.error || 'Registration failed. Please try again.';
                this.showError(errorMsg);
            }

        } catch (error) {
            console.error('Registration error:', error);
            this.showError('Connection error. Please try again.');
        }

        this.showRegisterLoading(false);
    }

    async checkExistingAuth() {
        const token = AgrohubUtils.getToken();
        if (token) {
            this.showLoading(true);
            
            try {
                const response = await fetch(AgrohubUtils.getApiUrl('/auth.php?action=verify'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'verify',
                        token: token
                    })
                });
                
                const result = await response.json();
                
                if (result.success && result.data.valid) {
                    // Valid token, load dashboard
                    AgrohubUtils.setUserData(result.data.user);
                    this.loadDashboard(result.data.user);
                } else {
                    // Invalid token, clear storage
                    AgrohubUtils.removeToken();
                    this.showLoginForm();
                }
            } catch (error) {
                console.error('Auth verification error:', error);
                AgrohubUtils.removeToken();
                this.showLoginForm();
            }
            
            this.showLoading(false);
        }
    }

    loadDashboard(user) {
        // Hide login forms
        const loginContainer = document.querySelector('.w-full.max-w-md');
        if (loginContainer) {
            loginContainer.style.display = 'none';
        }

        // Show and populate dashboard
        if (this.dashboardContainer) {
            this.dashboardContainer.style.display = 'block';
            this.dashboardContainer.innerHTML = this.generateDashboardHTML(user);
            
            // Initialize dashboard functionality
            this.initializeDashboard();
        }
    }

    generateDashboardHTML(user) {
        const avatar = user.avatar || AgrohubUtils.generateAvatar(user.name);
        const modules = user.modules || [];
        
        return `
            <div class="min-h-screen bg-gray-50">
                <!-- Header -->
                <header class="bg-white shadow-sm border-b">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between items-center h-16">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 gradient-bg rounded-lg flex items-center justify-center">
                                    <i class="fas fa-leaf text-white"></i>
                                </div>
                                <div>
                                    <h1 class="text-xl font-bold text-gray-900">Agrohub ERP</h1>
                                    <p class="text-xs text-gray-500">Enterprise Platform</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-4">
                                <button onclick="window.agrohubAuth.loadApps()" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md">
                                    <i class="fas fa-th-large mr-2"></i>Apps
                                </button>
                                ${user.role === 'admin' ? '<button onclick="window.agrohubAuth.loadAdmin()" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md"><i class="fas fa-cog mr-2"></i>Admin</button>' : ''}
                                
                                <div class="relative">
                                    <button id="userMenuBtn" class="flex items-center space-x-2 bg-gray-100 rounded-lg px-3 py-2 hover:bg-gray-200">
                                        <div class="w-8 h-8 gradient-bg rounded-full flex items-center justify-center text-white text-sm font-medium">
                                            ${avatar}
                                        </div>
                                        <span class="text-sm font-medium">${user.name}</span>
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </button>
                                    
                                    <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border hidden z-50">
                                        <div class="py-1">
                                            <div class="px-4 py-2 border-b">
                                                <p class="text-sm font-medium text-gray-900">${user.name}</p>
                                                <p class="text-xs text-gray-500">${user.email}</p>
                                                <p class="text-xs text-blue-600">${user.role}</p>
                                            </div>
                                            <button onclick="window.agrohubAuth.loadProfile()" class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-user mr-2"></i>Profile
                                            </button>
                                            <hr>
                                            <button onclick="window.agrohubAuth.logout()" class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Main Content -->
                <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div class="text-center py-8">
                        <div class="w-16 h-16 gradient-bg rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-home text-white text-2xl"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">კეთილი იყოს თქვენი მობრძანება, ${user.name}!</h2>
                        <p class="text-gray-600 mb-8">თქვენი საწარმოო რესურსების მართვის პლატფორმა</p>
                        
                        <!-- Available Modules -->
                        ${modules.length > 0 ? `
                            <div class="mb-8">
                                <h3 class="text-xl font-semibold text-gray-800 mb-4">ხელმისაწვდომი მოდულები (${modules.length})</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    ${modules.map(module => `
                                        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer" onclick="window.agrohubAuth.openModule('${module.technical_name}')">
                                            <div class="w-12 h-12 ${module.color || 'bg-blue-500'} rounded-lg flex items-center justify-center mx-auto mb-4">
                                                <i class="${module.icon || 'fas fa-cube'} text-white text-xl"></i>
                                            </div>
                                            <h4 class="text-lg font-semibold text-gray-900 mb-2">${module.name}</h4>
                                            <p class="text-gray-600 text-sm">${module.description || ''}</p>
                                            <p class="text-xs text-green-600 mt-2">დაყენებულია</p>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        <!-- General Actions -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-4xl mx-auto">
                            <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer" onclick="window.agrohubAuth.loadApps()">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-th-large text-blue-600 text-xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">აპლიკაციები</h3>
                                <p class="text-gray-600">იხილეთ და დააყენეთ ახალი მოდულები</p>
                            </div>
                            
                            ${user.role === 'admin' ? `
                            <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer" onclick="window.agrohubAuth.loadAdmin()">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-users-cog text-purple-600 text-xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">ადმინისტრირება</h3>
                                <p class="text-gray-600">მომხმარებლები და სისტემის კონფიგურაცია</p>
                            </div>
                            ` : ''}
                            
                            <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer" onclick="window.agrohubAuth.loadProfile()">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-user text-green-600 text-xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">პროფილი</h3>
                                <p class="text-gray-600">თქვენი ანგარიშის პარამეტრები</p>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        `;
    }

    initializeDashboard() {
        // User menu dropdown
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userMenuBtn && userDropdown) {
            userMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('hidden');
            });
            
            document.addEventListener('click', () => {
                userDropdown.classList.add('hidden');
            });
        }

        // Navigation buttons
        const appsBtn = document.getElementById('appsBtn');
        const adminBtn = document.getElementById('adminBtn');
        const profileBtn = document.getElementById('profileBtn');
        const logoutBtn = document.getElementById('logoutBtn');

        if (appsBtn) {
            appsBtn.addEventListener('click', () => this.loadApps());
        }
        
        if (adminBtn) {
            adminBtn.addEventListener('click', () => this.loadAdmin());
        }
        
        if (profileBtn) {
            profileBtn.addEventListener('click', () => this.loadProfile());
        }
        
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.logout());
        }

        // Initialize dashboard object for global access
        window.agrohubDashboard = {
            loadApps: () => this.loadApps(),
            loadAdmin: () => this.loadAdmin(),
            loadProfile: () => this.loadProfile()
        };
    }

    openModule(technicalName) {
        if (technicalName === 'violations') {
            // Get current token
            const token = AgrohubUtils.getToken();
            // Redirect to violations module with token
            window.location.href = `/Agrohub_Engine/modules/violations/?auth_token=${token}`;
        } else {
            // Handle other modules
            AgrohubUtils.showNotification(`Opening ${technicalName} module...`, 'info');
        }
    }

    loadApps() {
        window.location.href = '/Agrohub_Engine/apps.html';
    }

    loadAdmin() {
        window.location.href = '/Agrohub_Engine/admin.html';
    }

    loadProfile() {
        AgrohubUtils.showNotification('Profile page coming soon', 'info');
    }

    logout() {
        AgrohubUtils.removeToken();
        location.reload();
    }

    // UI Helper Methods
    showLoading(show) {
        if (this.loginButtonText && this.loginButtonLoading) {
            this.loginButtonText.classList.toggle('loading', show);
            this.loginButtonLoading.classList.toggle('active', show);
            this.loginButton.disabled = show;
        }
    }

    showRegisterLoading(show) {
        if (this.registerButtonText && this.registerButtonLoading) {
            this.registerButtonText.classList.toggle('loading', show);
            this.registerButtonLoading.classList.toggle('active', show);
        }
    }

    showError(message) {
        if (this.errorText && this.errorMessage) {
            this.errorText.textContent = message;
            this.errorMessage.classList.add('show');
            this.successMessage?.classList.remove('show');
        }
    }

    showSuccess(message) {
        if (this.successText && this.successMessage) {
            this.successText.textContent = message;
            this.successMessage.classList.add('show');
            this.errorMessage?.classList.remove('show');
        }
    }

    clearMessages() {
        this.errorMessage?.classList.remove('show');
        this.successMessage?.classList.remove('show');
    }

    showLoginForm() {
        const registerFormDiv = document.getElementById('registerForm');
        if (registerFormDiv) {
            registerFormDiv.style.display = 'none';
        }
    }

    showRegisterForm() {
        const registerFormDiv = document.getElementById('registerForm');
        if (registerFormDiv) {
            registerFormDiv.style.display = 'block';
        }
    }

    togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('#togglePassword i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            toggleIcon.className = 'fas fa-eye';
        }
    }
}

// Initialize authentication when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.agrohubAuth = new AgrohubAuth();
});