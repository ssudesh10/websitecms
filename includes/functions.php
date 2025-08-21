<?php
// Include configuration
require_once '../config.php';

// Global functions for the CMS

/**
 * Get a setting value from the database
 */
function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Update or insert a setting
 */
function updateSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, value, updated_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE value = ?, updated_at = NOW()
        ");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get page by slug
 */
function getPageBySlug($slug) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND status = 'published'");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get page sections
 */
function getPageSections($page_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT section_key, section_title, content 
            FROM page_sections 
            WHERE page_id = ? 
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$page_id]);
        $sections = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sections[$row['section_key']] = [
                'title' => $row['section_title'],
                'content' => $row['content']
            ];
        }
        return $sections;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get all published pages for navigation
 */
function getNavigationPages() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT title, slug, sort_order 
            FROM pages 
            WHERE status = 'published' 
            ORDER BY sort_order ASC, title ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Generate proper URLs using base URL
 */
function page_url($slug) {
    if ($slug === 'home') {
        return base_url();
    }
    return base_url('page.php?slug=' . urlencode($slug));
}

function admin_page_url($page) {
    return admin_url($page . '.php');
}

/**
 * Get current URL
 */
function current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    return $protocol . '://' . $host . $uri;
}

/**
 * Check if current page is active
 */
function is_active_page($slug) {
    $current_slug = $_GET['slug'] ?? 'home';
    
    // Handle home page
    if ($slug === 'home') {
        return ($current_slug === 'home' || 
                (!isset($_GET['slug']) && basename($_SERVER['PHP_SELF']) === 'index.php'));
    }
    
    return $current_slug === $slug;
}

/**
 * Sanitize HTML content
 */
function sanitizeHtml($content) {
    $allowed_tags = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a><img><div><span><blockquote><table><tr><td><th><thead><tbody>';
    return strip_tags($content, $allowed_tags);
}

/**
 * Generate excerpt from content
 */
function generateExcerpt($content, $length = 150) {
    $content = strip_tags($content);
    if (strlen($content) <= $length) {
        return $content;
    }
    return substr($content, 0, $length) . '...';
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Redirect to admin login if not authenticated
 */
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . admin_url('login.php'));
        exit();
    }
}

/**
 * Redirect helper with base URL support
 */
function redirect($url, $permanent = false) {
    // If URL doesn't start with http, treat as relative
    if (!preg_match('/^https?:\/\//', $url)) {
        $url = base_url($url);
    }
    
    $status_code = $permanent ? 301 : 302;
    header("Location: $url", true, $status_code);
    exit();
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Clean URL slug
 */
function cleanSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/\s+/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Send contact form email
 */
function sendContactEmail($name, $email, $subject, $message) {
    $to = getSetting('contact_email', 'contact@example.com');
    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $body = "
    <html>
    <body>
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Message:</strong></p>
        <p>" . nl2br(htmlspecialchars($message)) . "</p>
        <p><strong>Submitted from:</strong> " . base_url() . "</p>
    </body>
    </html>
    ";
    
    return mail($to, "Contact Form: $subject", $body, $headers);
}

/**
 * Log admin activity
 */
function logAdminActivity($action, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_id, action, details, ip_address, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $admin_id = $_SESSION['admin_id'] ?? 1;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->execute([$admin_id, $action, $details, $ip_address]);
    } catch (Exception $e) {
        error_log('Failed to log admin activity: ' . $e->getMessage());
    }
}

/**
 * Get breadcrumb navigation
 */
function getBreadcrumbs($current_page) {
    $breadcrumbs = [
        ['title' => 'Home', 'url' => base_url()]
    ];
    
    if ($current_page && $current_page['slug'] !== 'home') {
        $breadcrumbs[] = [
            'title' => $current_page['title'],
            'url' => page_url($current_page['slug'])
        ];
    }
    
    return $breadcrumbs;
}

/**
 * Get theme color classes
 */
function getThemeColors($color = null) {
    if (!$color) {
        $color = getSetting('theme_color', 'blue');
    }
    
    $colors = [
        'blue' => [
            'primary' => 'bg-blue-500 hover:bg-blue-600',
            'text' => 'text-blue-600',
            'border' => 'border-blue-500'
        ],
        'green' => [
            'primary' => 'bg-green-500 hover:bg-green-600',
            'text' => 'text-green-600',
            'border' => 'border-green-500'
        ],
        'purple' => [
            'primary' => 'bg-purple-500 hover:bg-purple-600',
            'text' => 'text-purple-600',
            'border' => 'border-purple-500'
        ],
        'red' => [
            'primary' => 'bg-red-500 hover:bg-red-600',
            'text' => 'text-red-600',
            'border' => 'border-red-500'
        ],
        'gray' => [
            'primary' => 'bg-gray-500 hover:bg-gray-600',
            'text' => 'text-gray-600',
            'border' => 'border-gray-500'
        ]
    ];
    
    return $colors[$color] ?? $colors['blue'];
}

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if string is valid email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

/**
 * Debug helper
 */
function dd($var) {
    if (DEBUG_MODE) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
        die();
    }
}
?>