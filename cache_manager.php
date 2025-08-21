<?php
// cache_manager.php - Add this as a new file for caching functionality
declare(strict_types=1);

class CacheManager {
    private $cacheDir;
    private $cacheEnabled;
    private $defaultExpiry;
    
    public function __construct($cacheEnabled = false, $cacheDir = 'cache', $defaultExpiry = 3600) {
        $this->cacheEnabled = $cacheEnabled;
        $this->cacheDir = __DIR__ . '/' . $cacheDir;
        $this->defaultExpiry = $defaultExpiry; // 1 hour default
        
        // Create cache directory if it doesn't exist
        if ($this->cacheEnabled && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
            // Add .htaccess to protect cache directory
            file_put_contents($this->cacheDir . '/.htaccess', "Deny from all\n");
        }
    }
    
    /**
     * Generate cache key for current page
     */
    private function getCacheKey($slug = '', $params = []) {
        $key = $slug ?: 'home';
        
        // Include URL parameters in cache key
        if (!empty($params)) {
            ksort($params);
            $key .= '_' . md5(serialize($params));
        }
        
        // Include user role in cache key (different cache for admin vs visitors)
        $userRole = $this->getUserRole();
        if ($userRole) {
            $key .= '_' . $userRole;
        }
        
        return md5($key) . '.cache';
    }
    
    /**
     * Get user role for cache segmentation
     */
    private function getUserRole() {
        if (function_exists('isLoggedIn') && isLoggedIn()) {
            return 'admin';
        }
        return 'visitor';
    }
    
    /**
     * Get cache file path
     */
    private function getCacheFilePath($cacheKey) {
        return $this->cacheDir . '/' . $cacheKey;
    }
    
    /**
     * Check if cache exists and is valid
     */
    public function isCacheValid($slug = '', $params = [], $expiry = null) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $cacheKey = $this->getCacheKey($slug, $params);
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        $expiry = $expiry ?: $this->defaultExpiry;
        $cacheTime = filemtime($cacheFile);
        
        return (time() - $cacheTime) < $expiry;
    }
    
    /**
     * Get cached content
     */
    public function getCache($slug = '', $params = []) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $cacheKey = $this->getCacheKey($slug, $params);
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if ($this->isCacheValid($slug, $params)) {
            return file_get_contents($cacheFile);
        }
        
        return false;
    }
    
    /**
     * Save content to cache
     */
    public function setCache($content, $slug = '', $params = []) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        $cacheKey = $this->getCacheKey($slug, $params);
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        return file_put_contents($cacheFile, $content) !== false;
    }
    
    /**
     * Clear specific cache
     */
    public function clearCache($slug = '', $params = []) {
        $cacheKey = $this->getCacheKey($slug, $params);
        $cacheFile = $this->getCacheFilePath($cacheKey);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }
    
    /**
     * Clear all cache files
     */
    public function clearAllCache() {
        if (!is_dir($this->cacheDir)) {
            return true;
        }
        
        $files = glob($this->cacheDir . '/*.cache');
        $cleared = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleared++;
            }
        }
        
        return $cleared;
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats() {
        if (!is_dir($this->cacheDir)) {
            return [
                'enabled' => $this->cacheEnabled,
                'files' => 0,
                'size' => 0,
                'oldest' => null,
                'newest' => null
            ];
        }
        
        $files = glob($this->cacheDir . '/*.cache');
        $totalSize = 0;
        $oldest = null;
        $newest = null;
        
        foreach ($files as $file) {
            $size = filesize($file);
            $time = filemtime($file);
            
            $totalSize += $size;
            
            if ($oldest === null || $time < $oldest) {
                $oldest = $time;
            }
            
            if ($newest === null || $time > $newest) {
                $newest = $time;
            }
        }
        
        return [
            'enabled' => $this->cacheEnabled,
            'files' => count($files),
            'size' => $totalSize,
            'size_formatted' => $this->formatBytes($totalSize),
            'oldest' => $oldest ? date('Y-m-d H:i:s', $oldest) : null,
            'newest' => $newest ? date('Y-m-d H:i:s', $newest) : null
        ];
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Start output buffering for cache
     */
    public function startCache($slug = '', $params = [], $expiry = null) {
        if (!$this->cacheEnabled) {
            return false;
        }
        
        // Check if we have valid cache
        $cachedContent = $this->getCache($slug, $params);
        if ($cachedContent !== false) {
            echo $cachedContent;
            return true; // Cache hit - stop execution
        }
        
        // Start output buffering for new cache
        ob_start();
        return false; // No cache - continue execution
    }
    
    /**
     * End output buffering and save cache
     */
    public function endCache($slug = '', $params = []) {
        if (!$this->cacheEnabled) {
            return;
        }
        
        $content = ob_get_contents();
        ob_end_flush();
        
        // Save to cache
        $this->setCache($content, $slug, $params);
    }
}

// Updated index.php implementation with caching
// Add this code to the top of your index.php after the license check

// Initialize cache manager
$cacheManager = new CacheManager(
    $cacheenabled === '1', // Enable caching based on setting
    'cache',               // Cache directory
    3600                   // Cache expiry (1 hour)
);

// Get current page parameters for cache key
$cacheParams = [];
if (isset($_GET) && !empty($_GET)) {
    // Only include safe GET parameters in cache key
    $safeCacheParams = ['page', 'category', 'tag']; // Add more as needed
    foreach ($_GET as $key => $value) {
        if (in_array($key, $safeCacheParams)) {
            $cacheParams[$key] = $value;
        }
    }
}

// Try to serve from cache first
if ($cacheManager->startCache($currentSlug, $cacheParams)) {
    exit(); // Cache hit - page served, stop execution
}

// Your existing page generation code goes here...
// (All the code from "Get homepage" onwards)

// At the very end of your HTML output, save to cache
$cacheManager->endCache($currentSlug, $cacheParams);

?>

<!-- Cache Management Interface -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Management</title>
    <style>
        .cache-admin {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .cache-stats {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .cache-actions {
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin: 5px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>

<?php
// Cache admin interface (add this to your admin panel)
if (isset($_GET['cache_admin']) && isLoggedIn()) {
    $stats = $cacheManager->getCacheStats();
    
    if ($_POST['action'] ?? '') {
        if ($_POST['action'] === 'clear_all') {
            $cleared = $cacheManager->clearAllCache();
            echo "<div class='alert alert-success'>Cleared {$cleared} cache files.</div>";
        }
    }
?>

<div class="cache-admin">
    <h2>🚀 Cache Management</h2>
    
    <div class="cache-stats">
        <h3>Cache Statistics</h3>
        <div class="stat-row">
            <strong>Status:</strong>
            <span><?= $stats['enabled'] ? '✅ Enabled' : '❌ Disabled' ?></span>
        </div>
        <div class="stat-row">
            <strong>Cached Files:</strong>
            <span><?= $stats['files'] ?></span>
        </div>
        <div class="stat-row">
            <strong>Total Size:</strong>
            <span><?= $stats['size_formatted'] ?></span>
        </div>
        <div class="stat-row">
            <strong>Oldest Cache:</strong>
            <span><?= $stats['oldest'] ?: 'N/A' ?></span>
        </div>
        <div class="stat-row">
            <strong>Newest Cache:</strong>
            <span><?= $stats['newest'] ?: 'N/A' ?></span>
        </div>
    </div>
    
    <div class="cache-actions">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="btn btn-danger" 
                    onclick="return confirm('Clear all cache files? This will temporarily slow down the site until cache rebuilds.')">
                🗑️ Clear All Cache
            </button>
        </form>
        
        <a href="<?= BASE_URL ?>/" class="btn">🏠 View Site</a>
        <a href="<?= BASE_URL ?>/admin/" class="btn">← Back to Admin</a>
    </div>
    
    <div class="cache-stats">
        <h3>How It Works</h3>
        <ul>
            <li>✅ Pages are cached for 1 hour by default</li>
            <li>✅ Different cache for admin vs visitors</li>
            <li>✅ Cache includes URL parameters</li>
            <li>✅ Automatic cache directory creation</li>
            <li>⚠️ Clear cache after major content updates</li>
        </ul>
    </div>
</div>

<?php } ?>

</body>
</html>