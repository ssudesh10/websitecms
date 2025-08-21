<?php
// config.php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cmstestpwrest');

// Base URL configuration
define('BASE_URL', 'http://localhost/cmsfortestpwreset'); // Change this to your actual domain

// Serial Number (will be updated during installation)
define('SERIAL_NUMBER', 'Leased-984d477a26e767df611f');

// Upload configuration
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Database connection
$db_connected = false;
$db_error_message = '';

// Database connection with improved error handling
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db_connected = true;
} catch(PDOException $e) {
    $db_connected = false;
    $db_error_message = $e->getMessage();
    
    // Log the error (optional - for debugging)
    error_log("Database connection failed: " . $e->getMessage());
}

// GENERAL EMAIL CONFIGURATION WITH SMTP SECURITY
// =======================================

// Basic Email Settings
define('EMAIL_ENABLED', false); // Set to false to disable all emails
define('SITE_NAME', 'CMS Admin Panel'); // Site name for emails

// admin SMTP CONFIGURATION FOR SECURITY
// ================================
// Set SMTP_ENABLED to true for secure email sending
define('SMTP_ENABLED', false); // Set to false to use less secure PHP mail()

define('EMAIL_FROM_ADDRESS', ''); // From email address
define('EMAIL_FROM_NAME', 'CMS Admin'); // Sender name

// SMTP Server Settings (Configure based on your email provider)
define('SMTP_HOST', ''); // SMTP server
define('SMTP_PORT', 465); // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_ENCRYPTION', ''); // 'tls', 'ssl', or '' for no encryption
define('SMTP_USERNAME', ''); // Your email address
define('SMTP_PASSWORD', ''); // Your email password or app password
define('SMTP_AUTH', false); // SMTP authentication required

// Password Reset Settings
define('PASSWORD_RESET_EXPIRY', 3600); // Password reset link expiry time in seconds (1 hour)

// Security Settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds


?>