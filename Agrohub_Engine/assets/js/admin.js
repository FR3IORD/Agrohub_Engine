// =====================================================
// Agrohub Admin Panel - Complete Version
// =====================================================

let authToken = null;
let currentUser = null;
let allUsers = [];
let allApps = [];

// Debug function to check API responses
async function debugFetch(url, options = {}) {
    console.log('🔍 API Request:', url, options);
    
    try {
        const response = await fetch(url, options);
        const contentType = response.headers.get('content-type');
        
        console.log('📥 Response Status:', response.status);
        console.log('📥 Content-Type:', contentType);
        
        const text = await response.text();
        console.log('📥 Raw Response:', text.substring(0, 500));
        
        // Check if response is JSON
        if (contentType && contentType.includes('application/json')) {
            try {
                const data = JSON.parse(text);
                console.log('✅ Parsed JSON:', data);
                return { response, data };
            } catch (e) {
                console.error('❌ JSON Parse Error:', e);
                showToast('Invalid JSON response from server', 'error');
                throw new Error('Invalid JSON: ' + text.substring(0, 100));
            }
        } else {
            console.error('❌ Not JSON! Content-Type:', contentType);
            console.error('❌ Response body:', text);
            showToast('Server returned HTML instead of JSON. Check console.', 'error');
            throw new Error('Expected JSON, got: ' + contentType);
        }
    } catch (error) {
        console.error('❌ Fetch Error:', error);
        throw error;
    }
}

// =====================================================
// INITIALIZATION
// =====================================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Panel Loading...');
    initAdmin();
});

async function initAdmin() {
    authToken = localStorage.getItem('auth_token');
    
    console.log('🔑 Auth Token:', authToken ? 'Present' : 'Missing');
    
    if (!authToken) {
        alert('Please login first');
        window.location.href = '/';
        return;
    }

    try {
        const { data } = await debugFetch('api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({ action: 'verify' })
        });
        
        if (!data.success || !data.data.user) {
            alert('Session expired');
            window.location.href = '/';
            return;
        }

        currentUser = data.data.user;
        
        if (currentUser.role !== 'Admin' && currentUser.role !== 'admin') {
            alert('Access denied. Admin privileges required.');
            window.location.href = '/';
            return;
        }

        document.getElementById('admin-user-name').textContent = currentUser.name;
        document.querySelector('#admin-user-info .gradient-bg').textContent = currentUser.name.charAt(0).toUpperCase();

        await loadUsers();
        await loadApps();
        
        console.log('✅ Admin Panel Loaded Successfully');
        
    } catch (error) {
        console.error('❌ Admin init error:', error);
        alert('Failed to initialize admin panel: ' + error.message);
    }
}

// =====================================================
// TAB SWITCHING
// =====================================================
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('tab-active', 'text-gray-900');
        btn.classList.add('text-gray-500');
    });
    
    document.getElementById(`tab-${tabName}`).classList.remove('hidden');
    event.target.classList.add('tab-active', 'text-gray-900');
    event.target.classList.remove('text-gray-500');
    
    switch(tabName) {
        case 'users':
            loadUsers();
            break;
        case 'apps':
            loadApps();
            break;
        case 'permissions':
            loadPermissionsUsers();
            break;
        case 'logs':
            loadActivityLogs();
            break;
    }
}

// =====================================================
// USERS MANAGEMENT
// =====================================================
async function loadUsers() {
    try {
        console.log('📋 Loading users...');
        const searchTerm = document.getElementById('search-users')?.value || '';
        const roleFilter = document.getElementById('filter-role')?.value || '';
        const statusFilter = document.getElementById('filter-status')?.value || '';
        
        const url = `api/admin.php?action=get_users&search=${searchTerm}&role=${roleFilter}&status=${statusFilter}`;
        
        const { data } = await debugFetch(url, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (data.success) {
            allUsers = data.data.users;
            renderUsersTable(data.data.users);
            console.log('✅ Users loaded:', allUsers.length);
        } else {
            showToast('Failed to load users: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Load users error:', error);
        showToast('Error loading users: ' + error.message, 'error');
    }
}

function renderUsersTable(users) {
    const tbody = document.getElementById('users-table-body');
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No users found</td></tr>';
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-medium">
                        ${user.name.charAt(0).toUpperCase()}
                    </div>
                    <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900">${user.name}</div>
                        <div class="text-sm text-gray-500">${user.company || 'No company'}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${user.email}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getRoleBadgeClass(user.role)}">
                    ${user.role}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <button onclick="toggleUserStatus(${user.id}, ${user.is_active ? 1 : 0})" 
                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'} cursor-pointer hover:opacity-80">
                    ${user.is_active ? 'Active' : 'Inactive'}
                </button>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never'}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button onclick="openAppAccessModal(${user.id})" class="text-blue-600 hover:text-blue-900 mr-3" title="App Access">
                    <i class="fas fa-th-large"></i>
                </button>
                <button onclick="editUser(${user.id})" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteUser(${user.id})" class="text-red-600 hover:text-red-900" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function getRoleBadgeClass(role) {
    const classes = {
        'admin': 'bg-purple-100 text-purple-800',
        'manager': 'bg-blue-100 text-blue-800',
        'user': 'bg-gray-100 text-gray-800'
    };
    return classes[role.toLowerCase()] || 'bg-gray-100 text-gray-800';
}

function openUserModal(userId = null) {
    const modal = document.getElementById('user-modal');
    const form = document.getElementById('user-form');
    const title = document.getElementById('user-modal-title');
    
    form.reset();
    
    if (userId) {
        title.textContent = 'Edit User';
        const user = allUsers.find(u => u.id === userId);
        if (user) {
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-name').value = user.name;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-company').value = user.company || '';
            document.getElementById('user-role').value = user.role;
            document.getElementById('user-status').value = user.is_active ? '1' : '0';
        }
    } else {
        title.textContent = 'Add New User';
        document.getElementById('user-id').value = '';
        document.getElementById('user-status').value = '1';
    }
    
    modal.classList.remove('hidden');
}

function closeUserModal() {
    document.getElementById('user-modal').classList.add('hidden');
}

async function saveUser() {
    const userId = document.getElementById('user-id').value;
    const name = document.getElementById('user-name').value;
    const email = document.getElementById('user-email').value;
    const password = document.getElementById('user-password').value;
    const company = document.getElementById('user-company').value;
    const role = document.getElementById('user-role').value;
    const status = document.getElementById('user-status').value;
    
    if (!name || !email || (!userId && !password)) {
        showToast('Please fill in all required fields', 'error');
        return;
    }
    
    try {
        const payload = {
            action: userId ? 'update_user' : 'create_user',
            id: userId || undefined,
            name,
            email,
            company,
            role,
            is_active: status
        };
        
        if (password) {
            payload.password = password;
        }
        
        const { data } = await debugFetch('api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify(payload)
        });
        
        if (data.success) {
            showToast(userId ? 'User updated successfully' : 'User created successfully', 'success');
            closeUserModal();
            loadUsers();
        } else {
            showToast('Failed to save user: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Save user error:', error);
        showToast('Error saving user: ' + error.message, 'error');
    }
}

function editUser(userId) {
    openUserModal(userId);
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) {
        return;
    }
    
    try {
        const { data } = await debugFetch('api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                action: 'delete_user',
                id: userId
            })
        });
        
        if (data.success) {
            showToast('User deleted successfully', 'success');
            loadUsers();
        } else {
            showToast('Failed to delete user: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Delete user error:', error);
        showToast('Error deleting user: ' + error.message, 'error');
    }
}

async function toggleUserStatus(userId, currentStatus) {
    try {
        const { data } = await debugFetch('api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                action: 'toggle_user_status',
                id: userId,
                is_active: currentStatus ? 0 : 1
            })
        });
        
        if (data.success) {
            showToast('User status updated', 'success');
            loadUsers();
        } else {
            showToast('Failed to update status: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Toggle status error:', error);
        showToast('Error updating status: ' + error.message, 'error');
    }
}

// =====================================================
// APPS MANAGEMENT
// =====================================================
async function loadApps() {
    try {
        console.log('🔧 Loading apps...');
        
        const { data } = await debugFetch('api/admin.php?action=get_all_apps', {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (data.success) {
            allApps = data.data.apps;
            renderAppsGrid(data.data.apps);
            console.log('✅ Apps loaded:', allApps.length);
        } else {
            showToast('Failed to load apps: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Load apps error:', error);
        showToast('Error loading apps: ' + error.message, 'error');
    }
}

function renderAppsGrid(apps) {
    const grid = document.getElementById('apps-grid');
    
    if (apps.length === 0) {
        grid.innerHTML = '<p class="text-gray-500 text-center col-span-full">No applications found</p>';
        return;
    }
    
    grid.innerHTML = apps.map(app => `
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-4">
                <div class="w-12 h-12 bg-gradient-to-r ${app.color || 'from-gray-400 to-gray-600'} rounded-lg flex items-center justify-center">
                    <i class="${app.icon} text-white text-xl"></i>
                </div>
                <button onclick="toggleAppStatus(${app.id}, ${app.is_active})" 
                        class="px-3 py-1 text-xs rounded-full ${app.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'} hover:opacity-80 transition">
                    ${app.is_active ? 'Active' : 'Inactive'}
                </button>
            </div>
            
            <h3 class="text-lg font-semibold text-gray-900 mb-2">${app.name}</h3>
            <p class="text-sm text-gray-600 mb-4 line-clamp-2">${app.description || 'No description'}</p>
            
            <div class="flex items-center justify-between text-sm border-t pt-3">
                <div class="flex items-center space-x-4">
                    <div>
                        <span class="text-gray-500">Users:</span>
                        <span class="font-medium text-gray-900 ml-1">${app.user_count || 0}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Access:</span>
                        <span class="font-medium text-gray-900 ml-1">${app.access_count || 0}</span>
                    </div>
                </div>
                <div>
                    <span class="text-xs text-gray-500">v${app.version || '1.0'}</span>
                </div>
            </div>
            
            <div class="mt-4 flex space-x-2">
                <button onclick="editApp(${app.id})" class="flex-1 px-3 py-2 text-sm bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition">
                    <i class="fas fa-edit mr-1"></i> Edit
                </button>
                <button onclick="deleteApp(${app.id})" class="px-3 py-2 text-sm bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

async function toggleAppStatus(appId, currentStatus) {
    try {
        const { data } = await debugFetch('api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                action: 'toggle_app_status',
                id: appId,
                is_active: currentStatus ? 0 : 1
            })
        });
        
        if (data.success) {
            showToast('App status updated', 'success');
            loadApps();
        } else {
            showToast('Failed to update app status: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Toggle app status error:', error);
        showToast('Error updating app status: ' + error.message, 'error');
    }
}

function openAppModal(appId = null) {
    const modal = document.getElementById('app-modal');
    const form = document.getElementById('app-form');
    const title = document.getElementById('app-modal-title');
    
    form.reset();
    
    if (appId) {
        title.textContent = 'Edit Application';
        const app = allApps.find(a => a.id === appId);
        if (app) {
            document.getElementById('app-id').value = app.id;
            document.getElementById('app-name').value = app.name;
            document.getElementById('app-description').value = app.description || '';
            document.getElementById('app-url').value = app.url || '';
            document.getElementById('app-icon').value = app.icon || 'fas fa-cube';
            document.getElementById('app-color').value = app.color || 'from-blue-400 to-blue-600';
            document.getElementById('app-version').value = app.version || '1.0';
            document.getElementById('app-status').value = app.is_active ? '1' : '0';
        }
    } else {
        title.textContent = 'Add New Application';
        document.getElementById('app-id').value = '';
        document.getElementById('app-icon').value = 'fas fa-cube';
        document.getElementById('app-color').value = 'from-blue-400 to-blue-600';
        document.getElementById('app-version').value = '1.0';
        document.getElementById('app-status').value = '1';
    }
    
    modal.classList.remove('hidden');
}

function closeAppModal() {
    document.getElementById('app-modal').classList.add('hidden');
}

async function saveApp() {
    const appId = document.getElementById('app-id').value;
    const name = document.getElementById('app-name').value;
    const description = document.getElementById('app-description').value;
    const url = document.getElementById('app-url').value;
    const icon = document.getElementById('app-icon').value;
    const color = document.getElementById('app-color').value;
    const version = document.getElementById('app-version').value;
    const status = document.getElementById('app-status').value;
    
    if (!name) {
        showToast('Please enter application name', 'error');
        return;
    }
    
    try {
        const { data } = await debugFetch('api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                action: appId ? 'update_app' : 'create_app',
                id: appId || undefined,
                name,
                description,
                url,
                icon,
                color,
                version,
                is_active: status
            })
        });
        
        if (data.success) {
            showToast(appId ? 'App updated successfully' : 'App created successfully', 'success');
            closeAppModal();
            loadApps();
        } else {
            showToast('Failed to save app: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Save app error:', error);
        showToast('Error saving app: ' + error.message, 'error');
    }
}

function editApp(appId) {
    openAppModal(appId);
}

async function deleteApp(appId) {
    if (!confirm('Are you sure you want to delete this application?')) {
        return;
    }
    
    try {
        const { data } = await debugFetch('api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                action: 'delete_app',
                id: appId
            })
        });
        
        if (data.success) {
            showToast('App deleted successfully', 'success');
            loadApps();
        } else {
            showToast('Failed to delete app: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Delete app error:', error);
        showToast('Error deleting app: ' + error.message, 'error');
    }
}

// =====================================================
// APP ACCESS MANAGEMENT
// =====================================================
async function openAppAccessModal(userId) {
    const modal = document.getElementById('app-access-modal');
    const user = allUsers.find(u => u.id === userId);
    
    if (!user) {
        showToast('User not found', 'error');
        return;
    }
    
    document.getElementById('app-access-user-name').textContent = user.name;
    document.getElementById('app-access-user-id').value = userId;
    
    try {
        const { data } = await debugFetch(`api/admin.php?action=get_user_apps&user_id=${userId}`, {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (data.success) {
            renderAppAccessList(data.data.apps, data.data.user_apps);
        } else {
            showToast('Failed to load user apps: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Load user apps error:', error);
        showToast('Error loading user apps: ' + error.message, 'error');
    }
    
    modal.classList.remove('hidden');
}

function renderAppAccessList(allApps, userApps) {
    const container = document.getElementById('app-access-list');
    const userAppIds = userApps.map(ua => ua.app_id);
    
    container.innerHTML = allApps.map(app => `
        <div class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-gradient-to-r ${app.color || 'from-gray-400 to-gray-600'} rounded-lg flex items-center justify-center">
                    <i class="${app.icon} text-white"></i>
                </div>
                <div>
                    <div class="font-medium text-gray-900">${app.name}</div>
                    <div class="text-xs text-gray-500">${app.description || ''}</div>
                </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" 
                       class="sr-only peer" 
                       ${userAppIds.includes(app.id) ? 'checked' : ''}
                       onchange="toggleUserApp(${app.id}, this.checked)">
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
        </div>
    `).join('');
}

function closeAppAccessModal() {
    document.getElementById('app-access-modal').classList.add('hidden');
}

async function toggleUserApp(appId, hasAccess) {
    const userId = document.getElementById('app-access-user-id').value;
    
    try {
        const { data } = await debugFetch('api/admin.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                action: hasAccess ? 'grant_app_access' : 'revoke_app_access',
                user_id: userId,
                app_id: appId
            })
        });
        
        if (data.success) {
            showToast(hasAccess ? 'Access granted' : 'Access revoked', 'success');
        } else {
            showToast('Failed to update access: ' + data.error, 'error');
            // Reload modal to reset state
            openAppAccessModal(parseInt(userId));
        }
    } catch (error) {
        console.error('❌ Toggle app access error:', error);
        showToast('Error updating access: ' + error.message, 'error');
        openAppAccessModal(parseInt(userId));
    }
}

// =====================================================
// PERMISSIONS MANAGEMENT
// =====================================================
async function loadPermissionsUsers() {
    try {
        console.log('🔐 Loading permissions...');
        
        const { data } = await debugFetch('api/admin.php?action=get_users', {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (data.success) {
            renderPermissionsGrid(data.data.users);
            console.log('✅ Permissions loaded');
        } else {
            showToast('Failed to load permissions: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Load permissions error:', error);
        showToast('Error loading permissions: ' + error.message, 'error');
    }
}

function renderPermissionsGrid(users) {
    const grid = document.getElementById('permissions-grid');
    
    if (users.length === 0) {
        grid.innerHTML = '<p class="text-gray-500 text-center col-span-full">No users found</p>';
        return;
    }
    
    grid.innerHTML = users.map(user => `
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center space-x-4 mb-4">
                <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-semibold text-lg">
                    ${user.name.charAt(0).toUpperCase()}
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-900">${user.name}</h3>
                    <p class="text-sm text-gray-500">${user.email}</p>
                </div>
            </div>
            
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Role</span>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadgeClass(user.role)}">
                        ${user.role}
                    </span>
                </div>
                
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Status</span>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full ${user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                        ${user.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>
                
                <div class="flex items-center justify-between pt-2 border-t">
                    <span class="text-sm text-gray-600">App Access</span>
                    <button onclick="openAppAccessModal(${user.id})" class="px-3 py-1 text-xs bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                        Manage
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// =====================================================
// ACTIVITY LOGS
// =====================================================
async function loadActivityLogs() {
    try {
        console.log('📊 Loading activity logs...');
        
        const { data } = await debugFetch('api/admin.php?action=get_activity_logs&limit=100', {
            headers: {
                'Authorization': `Bearer ${authToken}`
            }
        });
        
        if (data.success) {
            renderActivityLogs(data.data.logs);
            console.log('✅ Activity logs loaded:', data.data.logs.length);
        } else {
            showToast('Failed to load activity logs: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('❌ Load logs error:', error);
        showToast('Error loading activity logs: ' + error.message, 'error');
    }
}

function renderActivityLogs(logs) {
    const tbody = document.getElementById('logs-table-body');
    
    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No activity logs found</td></tr>';
        return;
    }
    
    tbody.innerHTML = logs.map(log => `
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 text-xs font-medium">
                        ${log.user_name ? log.user_name.charAt(0).toUpperCase() : '?'}
                    </div>
                    <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900">${log.user_name || 'Unknown'}</div>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getActionBadgeClass(log.action)}">
                    ${log.action}
                </span>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm text-gray-900">${log.description || '-'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${log.ip_address || '-'}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                ${formatDateTime(log.created_at)}
            </td>
        </tr>
    `).join('');
}

function getActionBadgeClass(action) {
    const classes = {
        'login': 'bg-green-100 text-green-800',
        'logout': 'bg-gray-100 text-gray-800',
        'create': 'bg-blue-100 text-blue-800',
        'update': 'bg-yellow-100 text-yellow-800',
        'delete': 'bg-red-100 text-red-800',
        'access': 'bg-purple-100 text-purple-800'
    };
    
    for (const [key, value] of Object.entries(classes)) {
        if (action.toLowerCase().includes(key)) {
            return value;
        }
    }
    
    return 'bg-gray-100 text-gray-800';
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// =====================================================
// SEARCH & FILTER
// =====================================================
function searchUsers() {
    loadUsers();
}

function filterUsers() {
    loadUsers();
}

// =====================================================
// LOGOUT
// =====================================================
function adminLogout() {
    if (confirm('Are you sure you want to logout?')) {
        localStorage.removeItem('auth_token');
        window.location.href = '/';
    }
}

// =====================================================
// UTILITY FUNCTIONS
// =====================================================
function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) {
        console.error('Toast container not found');
        return;
    }
    
    const toastId = 'toast-' + Date.now();
    
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3 animate-slide-in`;
    toast.innerHTML = `
        <i class="fas fa-${icons[type]}"></i>
        <span>${message}</span>
        <button onclick="document.getElementById('${toastId}').remove()" class="ml-4 hover:opacity-80">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        if (document.getElementById(toastId)) {
            document.getElementById(toastId).remove();
        }
    }, 5000);
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = ['user-modal', 'app-modal', 'app-access-modal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
};

// Handle escape key to close modals
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.add('hidden');
        });
    }
});

// Export functions for global access
window.switchTab = switchTab;
window.openUserModal = openUserModal;
window.closeUserModal = closeUserModal;
window.saveUser = saveUser;
window.editUser = editUser;
window.deleteUser = deleteUser;
window.toggleUserStatus = toggleUserStatus;
window.openAppModal = openAppModal;
window.closeAppModal = closeAppModal;
window.saveApp = saveApp;
window.editApp = editApp;
window.deleteApp = deleteApp;
window.toggleAppStatus = toggleAppStatus;
window.openAppAccessModal = openAppAccessModal;
window.closeAppAccessModal = closeAppAccessModal;
window.toggleUserApp = toggleUserApp;
window.searchUsers = searchUsers;
window.filterUsers = filterUsers;
window.adminLogout = adminLogout;

console.log('✅ Admin.js loaded successfully');