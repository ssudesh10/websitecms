<?php
// Define config file path
$config_path = '../config.php';

// Check if config file exists and load it
if (file_exists($config_path)) {
    require_once $config_path;
    
    // Use BASE_URL if defined, otherwise fallback to root
    $redirect_url = defined('BASE_URL') ? BASE_URL : '/';
} else {
    // Fallback if config file not found
    $redirect_url = '/';
}

// Redirect to main site
header("Location: " . $redirect_url);
exit();
?>