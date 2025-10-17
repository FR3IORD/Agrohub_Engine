<?php
/**
 * Agrohub ERP Platform - Configuration
 * 
 * Main configuration file for the application
 */

// Prevent direct access
if (!defined('AGROHUB_CONFIG')) {
    define('AGROHUB_CONFIG', true);
}

// Environment
define('ENVIRONMENT', 'development'); // development, production, testing

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'agrohub_erp');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

// PHPLogin Database Configuration (Violations Module)
define('PHPL_DB_HOST', 'localhost');
define('PHPL_DB_NAME', 'phplogin');
define('PHPL_DB_USER', 'root');
define('PHPL_DB_PASS', 'root');
define('PHPL_DB_CHARSET', 'utf8mb4');
define('PHPL_DB_TABLE_ACCOUNTS', 'accounts');   // Table that stores users

// Application Configuration
define('APP_NAME', 'Agrohub ERP Platform');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');
define('API_URL', APP_URL . '/api');

// Security Configuration
define('JWT_SECRET', 'your-super-secret-jwt-key-change-this-in-production');
define('JWT_ALGORITHM', 'HS256');
define('SESSION_DURATION', 3600); // 1 hour in seconds
define('REMEMBER_ME_DURATION', 604800); // 7 days in seconds
define('PASSWORD_MIN_LENGTH', 6);

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 10485760); // 10MB in bytes
define('UPLOAD_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx');

// Email Configuration (for future use)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');
define('FROM_EMAIL', 'noreply@agrohub.local');
define('FROM_NAME', 'Agrohub ERP Platform');

// Pagination
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// Cache Configuration
define('CACHE_ENABLED', false);
define('CACHE_DRIVER', 'file'); // file, redis, memcached
define('CACHE_DURATION', 3600); // 1 hour

// Logging Configuration
define('LOG_ENABLED', true);
define('LOG_LEVEL', 'info'); // debug, info, warning, error
define('LOG_FILE', '../logs/app.log');

// Feature Flags
define('FEATURE_EMAIL_VERIFICATION', false);
define('FEATURE_TWO_FACTOR_AUTH', false);
define('FEATURE_API_RATE_LIMITING', false);
define('FEATURE_MAINTENANCE_MODE', false);

// API Rate Limiting (requests per minute)
define('RATE_LIMIT_REQUESTS', 60);
define('RATE_LIMIT_WINDOW', 60);

// Default Module Categories
$MODULE_CATEGORIES = [
    'Website',
    'Sales',
    'Finance',
    'Services',
    'Human Resources',
    'Marketing',
    'Productivity',
    'Supply Chain',
    'Customizations'
];

// Default User Roles and Permissions
$USER_ROLES = [
    'admin' => [
        'name' => 'Administrator',
        'permissions' => [
            'users.create',
            'users.read',
            'users.update',
            'users.delete',
            'modules.create',
            'modules.read',
            'modules.update',
            'modules.delete',
            'modules.install',
            'modules.uninstall',
            'settings.read',
            'settings.update',
            'system.backup',
            'system.restore',
            'system.reset'
        ]
    ],
    'manager' => [
        'name' => 'Manager',
        'permissions' => [
            'users.read',
            'users.update',
            'modules.read',
            'modules.install',
            'modules.uninstall',
            'settings.read'
        ]
    ],
    'user' => [
        'name' => 'User',
        'permissions' => [
            'modules.read',
            'modules.install',
            'modules.uninstall',
            'profile.read',
            'profile.update'
        ]
    ]
];

// Error Messages
$ERROR_MESSAGES = [
    'auth_required' => 'Authentication required',
    'invalid_credentials' => 'Invalid email or password',
    'user_not_found' => 'User not found',
    'email_exists' => 'Email already registered',
    'invalid_email' => 'Invalid email format',
    'password_too_short' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters',
    'access_denied' => 'Access denied',
    'invalid_token' => 'Invalid or expired token',
    'module_not_found' => 'Module not found',
    'module_already_installed' => 'Module already installed',
    'missing_dependencies' => 'Missing required dependencies',
    'file_too_large' => 'File too large',
    'invalid_file_type' => 'Invalid file type',
    'upload_failed' => 'File upload failed',
    'database_error' => 'Database error occurred',
    'validation_error' => 'Validation error',
    'internal_error' => 'Internal server error'
];

// Success Messages
$SUCCESS_MESSAGES = [
    'login_success' => 'Login successful',
    'logout_success' => 'Logout successful',
    'register_success' => 'Registration successful',
    'profile_updated' => 'Profile updated successfully',
    'password_changed' => 'Password changed successfully',
    'module_installed' => 'Module installed successfully',
    'module_uninstalled' => 'Module uninstalled successfully',
    'settings_saved' => 'Settings saved successfully',
    'user_created' => 'User created successfully',
    'user_updated' => 'User updated successfully',
    'user_deleted' => 'User deleted successfully',
    'file_uploaded' => 'File uploaded successfully'
];

// Time Zones
$TIMEZONES = [
    'UTC' => 'UTC',
    'America/New_York' => 'Eastern Time',
    'America/Chicago' => 'Central Time',
    'America/Denver' => 'Mountain Time',
    'America/Los_Angeles' => 'Pacific Time',
    'Europe/London' => 'London',
    'Europe/Paris' => 'Paris',
    'Europe/Berlin' => 'Berlin',
    'Asia/Tokyo' => 'Tokyo',
    'Asia/Shanghai' => 'Shanghai',
    'Asia/Kolkata' => 'India',
    'Asia/Tbilisi' => 'Tbilisi',
    'Australia/Sydney' => 'Sydney'
];

// Languages
$LANGUAGES = [
    'en' => ['name' => 'English', 'flag' => '🇺🇸'],
    'ka' => ['name' => 'Georgian', 'flag' => '🇬🇪'],
    'es' => ['name' => 'Spanish', 'flag' => '🇪🇸'],
    'fr' => ['name' => 'French', 'flag' => '🇫🇷'],
    'de' => ['name' => 'German', 'flag' => '🇩🇪'],
    'ru' => ['name' => 'Russian', 'flag' => '🇷🇺'],
    'zh' => ['name' => 'Chinese', 'flag' => '🇨🇳'],
    'ja' => ['name' => 'Japanese', 'flag' => '🇯🇵']
];

// Currencies
$CURRENCIES = [
    'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
    'EUR' => ['name' => 'Euro', 'symbol' => '€'],
    'GEL' => ['name' => 'Georgian Lari', 'symbol' => '₾'],
    'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
    'RUB' => ['name' => 'Russian Ruble', 'symbol' => '₽'],
    'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥'],
    'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥']
];

// Module Icons and Colors
$MODULE_STYLES = [
    'Website' => ['icon' => 'fas fa-globe', 'color' => 'bg-blue-500'],
    'Sales' => ['icon' => 'fas fa-chart-line', 'color' => 'bg-green-500'],
    'Finance' => ['icon' => 'fas fa-calculator', 'color' => 'bg-red-500'],
    'Services' => ['icon' => 'fas fa-cogs', 'color' => 'bg-purple-500'],
    'Human Resources' => ['icon' => 'fas fa-users', 'color' => 'bg-orange-500'],
    'Marketing' => ['icon' => 'fas fa-bullhorn', 'color' => 'bg-pink-500'],
    'Productivity' => ['icon' => 'fas fa-tasks', 'color' => 'bg-indigo-500'],
    'Supply Chain' => ['icon' => 'fas fa-truck', 'color' => 'bg-yellow-500'],
    'Customizations' => ['icon' => 'fas fa-code', 'color' => 'bg-gray-500']
];

// Environment-specific settings
if (ENVIRONMENT === 'development') {
    // Development settings
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Enable CORS for development
    define('CORS_ENABLED', true);
    define('CORS_ORIGIN', '*');
    
} elseif (ENVIRONMENT === 'production') {
    // Production settings
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    
    // Disable CORS or set specific origins
    define('CORS_ENABLED', true);
    define('CORS_ORIGIN', 'https://yourdomain.com');
    
    // Enable additional security headers
    define('SECURITY_HEADERS', true);
    
} else {
    // Testing settings
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    define('CORS_ENABLED', true);
    define('CORS_ORIGIN', '*');
}

// Set default timezone
date_default_timezone_set('UTC');

// Helper function to get configuration value
function config($key, $default = null) {
    global $CONFIG;
    
    $keys = explode('.', $key);
    $value = $CONFIG ?? [];
    
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default;
        }
    }
    
    return $value;
}

// Helper function to check if feature is enabled
function feature_enabled($feature) {
    $constant = 'FEATURE_' . strtoupper($feature);
    return defined($constant) && constant($constant);
}

// Helper function to get error message
function get_error_message($key) {
    global $ERROR_MESSAGES;
    return $ERROR_MESSAGES[$key] ?? 'Unknown error';
}

// Helper function to get success message
function get_success_message($key) {
    global $SUCCESS_MESSAGES;
    return $SUCCESS_MESSAGES[$key] ?? 'Operation successful';
}

// Create logs directory if it doesn't exist
$logDir = dirname(LOG_FILE);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    
    // Create subdirectories
    $subdirs = ['avatars', 'documents', 'images'];
    foreach ($subdirs as $subdir) {
        $path = $uploadDir . '/' . $subdir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}

// Set security headers for production
if (ENVIRONMENT === 'production' && defined('SECURITY_HEADERS') && SECURITY_HEADERS) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// CORS headers
if (defined('CORS_ENABLED') && CORS_ENABLED) {
	$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
	// If an origin is provided, echo it back (allow credentials). Otherwise allow all (dev fallback).
	if ($origin) {
		// Allow specific origin and credentials
		header('Access-Control-Allow-Origin: ' . $origin);
		header('Access-Control-Allow-Credentials: true');
	} else {
		// No origin provided (CLI requests etc.) — safer to allow all in development
		header('Access-Control-Allow-Origin: *');
		// Do not advertise credentials when origin is wildcard
		header('Access-Control-Allow-Credentials: false');
	}
	header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
	header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
}