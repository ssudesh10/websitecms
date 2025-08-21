<?php
/**
 * Clean URL Helper Functions
 * Add these functions to your config.php or create a separate file
 */

/**
 * Generate clean URL for a page
 * @param string $slug Page slug
 * @param bool $absolute Whether to return absolute URL
 * @return string Clean URL
 */
if (!function_exists('formatUrl')) {
    function formatUrl($slug, $absolute = false) {
        // Handle home page
        if ($slug === 'home') {
            return $absolute ? BASE_URL . '/' : '/';
        }
        
        // Generate clean URL
        $url = '/' . ltrim($slug, '/');
        
        if ($absolute) {
            return BASE_URL . $url;
        }
        
        return $url;
    }
}

/**
 * Get current page slug from clean URL
 * @return string Current page slug
 */
if (!function_exists('getCurrentSlug')) {
    function getCurrentSlug() {
        // Get the request URI
        $requestUri = $_SERVER['REQUEST_URI'];
        
        // Remove the base path if your site is in a subdirectory
        if (defined('BASE_PATH') && BASE_PATH !== '/') {
            $requestUri = str_replace(BASE_PATH, '', $requestUri);
        }
        
        // Remove query string
        $requestUri = strtok($requestUri, '?');
        
        // Remove leading and trailing slashes
        $requestUri = trim($requestUri, '/');
        
        // If empty, it's the home page
        if (empty($requestUri)) {
            return 'home';
        }
        
        return $requestUri;
    }
}

/**
 * Generate canonical URL for current page
 * @return string Canonical URL
 */
if (!function_exists('getCanonicalUrl')) {
    function getCanonicalUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];
        
        // Clean up the URI (remove query parameters that don't affect content)
        $uri = strtok($uri, '?');
        
        return $protocol . '://' . $host . $uri;
    }
}

/**
 * Generate navigation URLs with clean format
 * @param array $pages Array of pages
 * @return array Pages with formatted URLs
 */
if (!function_exists('formatNavigationUrls')) {
    function formatNavigationUrls($pages) {
        foreach ($pages as &$page) {
            $page['url'] = formatUrl($page['slug']);
            $page['absolute_url'] = formatUrl($page['slug'], true);
        }
        return $pages;
    }
}

/**
 * Check if current page matches given slug
 * @param string $slug Page slug to check
 * @return bool True if current page matches
 */
if (!function_exists('isCurrentPage')) {
    function isCurrentPage($slug) {
        $currentSlug = getCurrentSlug();
        return $currentSlug === $slug;
    }
}

/**
 * Generate breadcrumb array for current page
 * @param string $currentSlug Current page slug
 * @param string $currentTitle Current page title
 * @return array Breadcrumb items
 */
if (!function_exists('generateBreadcrumbs')) {
    function generateBreadcrumbs($currentSlug, $currentTitle) {
        $breadcrumbs = [
            ['name' => 'Home', 'url' => formatUrl('home', true)]
        ];
        
        if ($currentSlug !== 'home') {
            $breadcrumbs[] = [
                'name' => $currentTitle,
                'url' => formatUrl($currentSlug, true)
            ];
        }
        
        return $breadcrumbs;
    }
}

/**
 * Redirect to clean URL if accessed via old format
 * @param string $slug Page slug
 */
if (!function_exists('redirectToCleanUrl')) {
    function redirectToCleanUrl($slug) {
        // Check if accessed via page.php?slug= format
        if (isset($_GET['slug']) && $_SERVER['SCRIPT_NAME'] === '/websitecms/page.php') {
            $cleanUrl = formatUrl($_GET['slug'], true);
            header("Location: $cleanUrl", true, 301);
            exit;
        }
    }
}

/**
 * Generate sitemap URLs with clean format
 * @return array Sitemap URLs
 */
if (!function_exists('generateSitemapUrls')) {
    function generateSitemapUrls() {
        global $pdo;
        
        $urls = [];
        
        // Get all active pages
        $stmt = $pdo->query("SELECT slug, updated_at FROM pages WHERE is_active = 1");
        $pages = $stmt->fetchAll();
        
        foreach ($pages as $page) {
            $urls[] = [
                'url' => formatUrl($page['slug'], true),
                'lastmod' => date('Y-m-d', strtotime($page['updated_at'])),
                'changefreq' => 'weekly',
                'priority' => $page['slug'] === 'home' ? '1.0' : '0.8'
            ];
        }
        
        return $urls;
    }
}

/**
 * Handle 404 errors gracefully
 * @param string $slug Requested slug
 */
if (!function_exists('handle404')) {
    function handle404($slug) {
        // Log the 404 for analytics
        error_log("404 Error: Page not found - $slug");
        
        // Set proper 404 header
        header("HTTP/1.0 404 Not Found");
        
        // You can create a custom 404.php page
        if (file_exists('404.php')) {
            include '404.php';
        } else {
            echo '<h1>Page Not Found</h1>';
            echo '<p>The page you are looking for could not be found.</p>';
            echo '<a href="' . formatUrl('home', true) . '">Go Home</a>';
        }
        exit;
    }
}

/**
 * Check if URL structure needs updating (for migrations)
 * @return bool True if URLs need updating
 */
if (!function_exists('needsUrlUpdate')) {
    function needsUrlUpdate() {
        // Check if we're using old URL format
        return isset($_GET['slug']) && strpos($_SERVER['REQUEST_URI'], 'page.php') !== false;
    }
}

/**
 * Generate meta refresh for old URLs (temporary)
 * @param string $newUrl New clean URL
 */
if (!function_exists('metaRefreshRedirect')) {
    function metaRefreshRedirect($newUrl) {
        echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($newUrl) . '">';
        echo '<script>window.location.href = "' . htmlspecialchars($newUrl) . '";</script>';
    }
}

function isCurrentPage($pageSlug) {
    $currentSlug = getCurrentPageSlug();
    return $currentSlug === $pageSlug;
}

function getCurrentPageSlug() {
    // Get the current URL path
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    // Remove leading/trailing slashes and base path
    $path = trim(str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $path), '/');
    
    // If empty path, it's homepage
    if (empty($path) || $path === 'index.php') {
        return 'home';
    }
    
    // Remove .php extension if present
    $path = preg_replace('/\.php$/', '', $path);
    
    return $path;
}

?>