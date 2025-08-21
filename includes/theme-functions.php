<?php
// =============================================
// STEP 2: THEME FUNCTIONS (NO CSS FOR DEFAULT THEME)
// =============================================
// Create this file: includes/theme-functions.php

function getCurrentTheme() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'active_theme'");
        $stmt->execute();
        $activeThemeName = $stmt->fetchColumn() ?: 'default';
        
        $stmt = $pdo->prepare("SELECT * FROM themes WHERE theme_name = ?");
        $stmt->execute([$activeThemeName]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$theme) {
            // Fallback to default
            $stmt = $pdo->prepare("SELECT * FROM themes WHERE theme_name = 'default'");
            $stmt->execute();
            $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $theme;
    } catch (Exception $e) {
        error_log("Error getting current theme: " . $e->getMessage());
        return getDefaultTheme();
    }
}

function getDefaultTheme() {
    return [
        'id' => 0,
        'theme_name' => 'default',
        'theme_display_name' => 'Default Theme',
        'theme_config' => json_encode([
            'primary_color' => '#3b82f6',
            'secondary_color' => '#6b7280',
            'font_family' => 'Inter',
            'button_style' => 'rounded'
        ])
    ];
}

function getAllThemes() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM themes ORDER BY theme_display_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting themes: " . $e->getMessage());
        return [];
    }
}

function getThemeConfig($themeName = null) {
    if (!$themeName) {
        $theme = getCurrentTheme();
    } else {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM themes WHERE theme_name = ?");
        $stmt->execute([$themeName]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$theme || !$theme['theme_config']) {
        return [
            'primary_color' => '#3b82f6',
            'secondary_color' => '#6b7280',
            'font_family' => 'Inter',
            'button_style' => 'rounded'
        ];
    }
    
    return json_decode($theme['theme_config'], true);
}

function activateTheme($themeName) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Deactivate all themes
        $stmt = $pdo->prepare("UPDATE themes SET is_active = 0");
        $stmt->execute();
        
        // Activate selected theme
        $stmt = $pdo->prepare("UPDATE themes SET is_active = 1 WHERE theme_name = ?");
        $stmt->execute([$themeName]);
        
        // Update settings
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('active_theme', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$themeName, $themeName]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Error activating theme: " . $e->getMessage());
        return false;
    }
}

function generateThemeCSS($themeConfig = null) {
    // Get current theme to check if it's default
    $currentTheme = getCurrentTheme();
    
    // If current theme is 'default', return empty CSS
    if ($currentTheme['theme_name'] === 'default') {
        return "";
    }
    
    // For non-default themes, generate CSS as usual
    if (!$themeConfig) {
        $themeConfig = getThemeConfig();
    }
    
    $primaryColor = $themeConfig['primary_color'] ?? '#3b82f6';
    $secondaryColor = $themeConfig['secondary_color'] ?? '#6b7280';
    $fontFamily = $themeConfig['font_family'] ?? 'Inter';
    $buttonStyle = $themeConfig['button_style'] ?? 'rounded';
    
    // Convert hex to RGB for opacity variants
    $primaryRgb = hexToRgb($primaryColor);
    $secondaryRgb = hexToRgb($secondaryColor);
    
    $borderRadius = $buttonStyle === 'rounded' ? '0.5rem' : '0.25rem';
    
    return "
    <style>
    :root {
        --theme-primary: {$primaryColor};
        --theme-primary-rgb: {$primaryRgb};
        --theme-secondary: {$secondaryColor};
        --theme-secondary-rgb: {$secondaryRgb};
        --theme-font-family: '{$fontFamily}', sans-serif;
        --theme-border-radius: {$borderRadius};
    }
    
    /* Apply theme colors */
    .btn-primary, .hero-primary-button {
        background-color: var(--theme-primary) !important;
        border-color: var(--theme-primary) !important;
        border-radius: var(--theme-border-radius) !important;
    }
    
    .btn-primary:hover, .cta-primary-button:hover, .hero-primary-button:hover {
        background-color: color-mix(in srgb, var(--theme-primary) 80%, black) !important;
    }
    
    .text-blue-600, .text-blue-500 {
        color: var(--theme-primary) !important;
    }
    
    .bg-blue-600, .bg-blue-500 {
        background-color: var(--theme-primary) !important;
    }
    
    .border-blue-600, .border-blue-500 {
        border-color: var(--theme-primary) !important;
    }
    
    /* Apply theme font */
    body, .hero-title, .hero-subtitle, .hero-content,
    .cta-title, .cta-subtitle, .cta-content,
    .content-title, .content-subtitle, .content-text {
        font-family: var(--theme-font-family) !important;
    }
    
    /* Theme-specific button styles */
    .hero-secondary-button, .cta-secondary-button {
        border-radius: var(--theme-border-radius) !important;
    }
    
    /* Gradient backgrounds using theme colors */
    .bg-gradient-to-r {
        background: linear-gradient(to right, var(--theme-primary), var(--theme-secondary)) !important;
    }
    
    /* Form elements */
    .form-input:focus {
        border-color: var(--theme-primary) !important;
        box-shadow: 0 0 0 3px rgba(var(--theme-primary-rgb), 0.1) !important;
    }
    </style>";
}

function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    return "$r, $g, $b";
}

function createTheme($data) {
    global $pdo;
    
    try {
        $themeConfig = [
            'primary_color' => $data['primary_color'] ?? '#3b82f6',
            'secondary_color' => $data['secondary_color'] ?? '#6b7280',
            'font_family' => $data['font_family'] ?? 'Inter',
            'button_style' => $data['button_style'] ?? 'rounded'
        ];
        
        $stmt = $pdo->prepare("INSERT INTO themes (theme_name, theme_display_name, theme_description, theme_author, theme_config) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $data['theme_name'],
            $data['theme_display_name'],
            $data['theme_description'] ?? '',
            $data['theme_author'] ?? 'Custom',
            json_encode($themeConfig)
        ]);
        
        return $result;
    } catch (Exception $e) {
        error_log("Error creating theme: " . $e->getMessage());
        return false;
    }
}

function deleteTheme($themeName) {
    global $pdo;
    
    // Don't allow deletion of system themes
    $systemThemes = ['default', 'dark', 'corporate', 'creative', 'minimal'];
    if (in_array($themeName, $systemThemes)) {
        return false;
    }
    
    try {
        // Don't delete active theme
        $currentTheme = getCurrentTheme();
        if ($currentTheme['theme_name'] === $themeName) {
            return false;
        }
        
        $stmt = $pdo->prepare("DELETE FROM themes WHERE theme_name = ?");
        return $stmt->execute([$themeName]);
    } catch (Exception $e) {
        error_log("Error deleting theme: " . $e->getMessage());
        return false;
    }
}

// Optional: Helper function to check if default theme is active
function isDefaultThemeActive() {
    $currentTheme = getCurrentTheme();
    return $currentTheme['theme_name'] === 'default';
}

// Optional: Helper function to get theme status
function getThemeStatus() {
    $currentTheme = getCurrentTheme();
    return [
        'current_theme' => $currentTheme['theme_name'],
        'is_default' => $currentTheme['theme_name'] === 'default',
        'css_applied' => $currentTheme['theme_name'] !== 'default'
    ];
}
?>