<?php
// File: install/config_updater.php (Config file updater for installer)

/**
 * Updates the config.php file with new database settings
 * Called by the installer to update configuration values
 */
function updateConfigFile($config_values) {
    $config_file = '../config.php';
    
    if (!file_exists($config_file)) {
        return ['success' => false, 'error' => 'Config file not found'];
    }
    
    try {
        $content = file_get_contents($config_file);
        
        // Update database configuration
        if (isset($config_values['db_host'])) {
            $content = preg_replace(
                "/define\('DB_HOST',\s*'[^']*'\);/",
                "define('DB_HOST', '" . addslashes($config_values['db_host']) . "');",
                $content
            );
        }
        
        if (isset($config_values['db_name'])) {
            $content = preg_replace(
                "/define\('DB_NAME',\s*'[^']*'\);/",
                "define('DB_NAME', '" . addslashes($config_values['db_name']) . "');",
                $content
            );
        }
        
        if (isset($config_values['db_user'])) {
            $content = preg_replace(
                "/define\('DB_USER',\s*'[^']*'\);/",
                "define('DB_USER', '" . addslashes($config_values['db_user']) . "');",
                $content
            );
        }
        
        if (isset($config_values['db_pass'])) {
            $content = preg_replace(
                "/define\('DB_PASS',\s*'[^']*'\);/",
                "define('DB_PASS', '" . addslashes($config_values['db_pass']) . "');",
                $content
            );
        }
        
        // Update base URL - handle both BASE_URL and SITE_URL
        if (isset($config_values['base_url'])) {
            $content = preg_replace(
                "/define\('BASE_URL',\s*'[^']*'\);/",
                "define('BASE_URL', '" . addslashes($config_values['base_url']) . "');",
                $content
            );
            
            // Also update SITE_URL if it exists
            $content = preg_replace(
                "/define\('SITE_URL',\s*'[^']*'\);/",
                "define('SITE_URL', '" . addslashes($config_values['base_url']) . "');",
                $content
            );
        }
        if (isset($config_values['serial_number'])) {
            $content = preg_replace(
                "/define\s*\(\s*['\"]SERIAL_NUMBER['\"]\s*,\s*[^)]+\)/", 
                "define('SERIAL_NUMBER', '" . addslashes($config_values['serial_number']) . "')", 
                $content
            );
        }
        
        // Write the updated content back to the file
        if (file_put_contents($config_file, $content)) {
            return ['success' => true, 'message' => 'Config file updated successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to write config file'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Error updating config file: ' . $e->getMessage()];
    }
}

/**
 * Validate config file structure
 */
function validateConfigFile() {
    $config_file = '../config.php';
    
    if (!file_exists($config_file)) {
        return ['valid' => false, 'error' => 'Config file does not exist'];
    }
    
    if (!is_readable($config_file)) {
        return ['valid' => false, 'error' => 'Config file is not readable'];
    }
    
    if (!is_writable($config_file)) {
        return ['valid' => false, 'error' => 'Config file is not writable'];
    }
    
    $content = file_get_contents($config_file);
    
    // Check for required constants
    $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'BASE_URL'];
    $missing_constants = [];
    
    foreach ($required_constants as $constant) {
        if (!preg_match("/define\('$constant',/", $content)) {
            $missing_constants[] = $constant;
        }
    }
    
    if (!empty($missing_constants)) {
        return [
            'valid' => false, 
            'error' => 'Missing required constants: ' . implode(', ', $missing_constants)
        ];
    }
    
    return ['valid' => true, 'message' => 'Config file is valid'];
}

?>