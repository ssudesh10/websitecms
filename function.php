<?php

require_once __DIR__ . '/includes/theme-functions.php';

// Function to check if database is connected
function isDatabaseConnected() {
    global $db_connected;
    return $db_connected;
}

// Function to get database error message
function getDatabaseError() {
    global $db_error_message;
    return $db_error_message;
}

// Function to display database error page
function showDatabaseError($customMessage = '') {
    $errorTitle = "Database Connection Error";
    $errorMessage = $customMessage ?: "We're experiencing technical difficulties. Please try again later.";
    
    // Check if this is an AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed',
            'message' => $errorMessage
        ]);
        exit;
    }
    
    // For non-AJAX requests, redirect to error page
    $errorParams = http_build_query([
        'title' => $errorTitle,
        'message' => $errorMessage,
        'debug' => isset($_GET['debug']) ? $_GET['debug'] : ''
    ]);
    
    header("Location: error-page.php?" . $errorParams);
    exit;
}
// Helper functions
function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function getPageBySlug($slug) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

function getPageSections($pageId) {
    global $pdo;
    // ✅ FIXED: Use sort_order instead of section_order
    $stmt = $pdo->prepare("SELECT * FROM page_sections WHERE page_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$pageId]);
    return $stmt->fetchAll();
}

// Get all active pages ordered by sort_order
function getAllPages() {
    global $pdo;
    // Order by sort_order ASC (smallest to largest), handle NULL values
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE is_active = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Also update the admin version:
function getAllPagesAdmin() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM pages ORDER BY sort_order ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function formatUrl($slug) {
    if ($slug === 'home') {
        return BASE_URL . '/';
    }
    return BASE_URL . '/' . urlencode($slug);
}

// FIXED: Upload function that stores RELATIVE PATHS only
function uploadImage($file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    $fileSize = $file['size'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];
    
    if ($fileError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error'];
    }
    
    if ($fileSize > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File size too large (max 5MB)'];
    }
    
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Generate unique filename
    $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $targetPath = UPLOAD_DIR . $newFileName;
    
    if (move_uploaded_file($fileTmpName, $targetPath)) {
        global $pdo;
        try {
            // FIXED: Store RELATIVE path instead of full URL
            $relativePath = 'uploads/' . $newFileName;
            $fullUrl = UPLOAD_URL . $newFileName; // For response only
            
            $stmt = $pdo->prepare("INSERT INTO uploaded_images (filename, original_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $newFileName,
                $fileName,
                $relativePath, // STORE RELATIVE PATH: uploads/filename.jpg
                $fileSize,
                $file['type'],
                $_SESSION['admin_id'] ?? null
            ]);
            
            return [
                'success' => true,
                'url' => $fullUrl,  // Return full URL for immediate use
                'filename' => $newFileName,
                'relativePath' => $relativePath // Also return relative path
            ];
        } catch (Exception $e) {
            unlink($targetPath); // Remove file if database insert fails
            return ['success' => false, 'error' => 'Database error'];
        }
    }
    
    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}

// FIXED: Get uploaded images function that converts relative paths to full URLs for display
function getUploadedImages() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM uploaded_images ORDER BY created_at DESC");
    $images = $stmt->fetchAll();
    
    // Convert relative paths to full URLs for display
    foreach ($images as &$image) {
        // If file_path is relative, convert to full URL for display
        if (!str_starts_with($image['file_path'], 'http')) {
            $image['file_path'] = BASE_URL . '/' . ltrim($image['file_path'], '/');
        }
    }
    
    return $images;
}

// NEW: Helper function to convert full URLs to relative paths
function getRelativePath($fullUrl) {
    if (str_starts_with($fullUrl, BASE_URL . '/')) {
        return str_replace(BASE_URL . '/', '', $fullUrl);
    }
    if (str_starts_with($fullUrl, BASE_URL)) {
        return ltrim(str_replace(BASE_URL, '', $fullUrl), '/');
    }
    return $fullUrl; // Already relative or from different domain
}

// NEW: Helper function to convert relative paths to full URLs
function getFullUrl($relativePath) {
    if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
        return $relativePath; // Already a full URL
    }
    return BASE_URL . '/' . ltrim($relativePath, '/');
}

// NEW: Function to migrate existing full URLs to relative paths
function migrateImagePathsToRelative() {
    global $pdo;
    
    try {
        // Get all images with full URLs
        $stmt = $pdo->query("SELECT id, file_path FROM uploaded_images WHERE file_path LIKE '" . BASE_URL . "%'");
        $images = $stmt->fetchAll();
        
        $migrated = 0;
        foreach ($images as $image) {
            $relativePath = getRelativePath($image['file_path']);
            
            $updateStmt = $pdo->prepare("UPDATE uploaded_images SET file_path = ? WHERE id = ?");
            $updateStmt->execute([$relativePath, $image['id']]);
            $migrated++;
        }
        
        return ['success' => true, 'migrated' => $migrated];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// PAGE ORDERING FUNCTIONS

// Update page sort order
function updatePageSortOrder($pageId, $sortOrder) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ?");
    return $stmt->execute([$sortOrder, $pageId]);
}

// Get max sort order
function getMaxSortOrder() {
    global $pdo;
    $stmt = $pdo->query("SELECT MAX(sort_order) FROM pages");
    return $stmt->fetchColumn() ?: 0;
}

// Move page up in order
function movePageUp($pageId) {
    global $pdo;
    
    // Get current page info
    $stmt = $pdo->prepare("SELECT sort_order FROM pages WHERE id = ?");
    $stmt->execute([$pageId]);
    $currentOrder = $stmt->fetchColumn();
    
    if (!$currentOrder) return false;
    
    // Find the page with the next lower sort_order
    $stmt = $pdo->prepare("SELECT id, sort_order FROM pages WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1");
    $stmt->execute([$currentOrder]);
    $prevPage = $stmt->fetch();
    
    if ($prevPage) {
        // Swap sort orders
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ?");
            $stmt->execute([$prevPage['sort_order'], $pageId]);
            
            $stmt = $pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ?");
            $stmt->execute([$currentOrder, $prevPage['id']]);
            
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }
    
    return false;
}

// Move page down in order
function movePageDown($pageId) {
    global $pdo;
    
    // Get current page info
    $stmt = $pdo->prepare("SELECT sort_order FROM pages WHERE id = ?");
    $stmt->execute([$pageId]);
    $currentOrder = $stmt->fetchColumn();
    
    if (!$currentOrder) return false;
    
    // Find the page with the next higher sort_order
    $stmt = $pdo->prepare("SELECT id, sort_order FROM pages WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1");
    $stmt->execute([$currentOrder]);
    $nextPage = $stmt->fetch();
    
    if ($nextPage) {
        // Swap sort orders
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ?");
            $stmt->execute([$nextPage['sort_order'], $pageId]);
            
            $stmt = $pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ?");
            $stmt->execute([$currentOrder, $nextPage['id']]);
            
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }
    
    return false;
}

// Handle AJAX requests for moving pages (for simple up/down arrows)
if (isset($_POST['action']) && isset($_POST['page_id']) && 
    ($_POST['action'] === 'move_up' || $_POST['action'] === 'move_down')) {
    requireLogin();
    
    $pageId = (int)$_POST['page_id'];
    $success = false;
    
    if ($_POST['action'] === 'move_up') {
        $success = movePageUp($pageId);
    } elseif ($_POST['action'] === 'move_down') {
        $success = movePageDown($pageId);
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    } else {
        // Redirect back to admin page
        header('Location: index.php');
        exit;
    }
}

// NEW: Helper function to convert full URLs to relative paths
// function getRelativePath($fullUrl) {
//     if (str_starts_with($fullUrl, BASE_URL . '/')) {
//         return str_replace(BASE_URL . '/', '', $fullUrl);
//     }
//     if (str_starts_with($fullUrl, BASE_URL)) {
//         return ltrim(str_replace(BASE_URL, '', $fullUrl), '/');
//     }
//     return $fullUrl; // Already relative or from different domain
// }

// NEW: Helper function to convert relative paths to full URLs
// function getFullUrl($relativePath) {
//     if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
//         return $relativePath; // Already a full URL
//     }
//     return BASE_URL . '/' . ltrim($relativePath, '/');
// }

// NEW: Function to migrate existing full URLs to relative paths
// function migrateImagePathsToRelative() {
//     global $pdo;
    
//     try {
//         // Get all images with full URLs
//         $stmt = $pdo->query("SELECT id, file_path FROM uploaded_images WHERE file_path LIKE '" . BASE_URL . "%'");
//         $images = $stmt->fetchAll();
        
//         $migrated = 0;
//         foreach ($images as $image) {
//             $relativePath = getRelativePath($image['file_path']);
            
//             $updateStmt = $pdo->prepare("UPDATE uploaded_images SET file_path = ? WHERE id = ?");
//             $updateStmt->execute([$relativePath, $image['id']]);
//             $migrated++;
//         }
        
//         return ['success' => true, 'migrated' => $migrated];
//     } catch (Exception $e) {
//         return ['success' => false, 'error' => $e->getMessage()];
//     }
// }

// PROPERLY FIXED VERSION - Add this to your config.php

function getSetting($key, $default = '') {
    global $pdo;
    
    if (!$pdo) {
        return $default;
    }
    
    try {
        // First, check if the settings table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($tableCheck->rowCount() === 0) {
            // Table doesn't exist, return default without trying to query it
            return $default;
        }
        
        // Table exists, now get the setting
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        
        // Return the result if it exists, otherwise return default
        return ($result !== false) ? $result : $default;
        
    } catch (Exception $e) {
        // Any database error, return default
        return $default;
    }
}

function setSetting($key, $value) {
    global $pdo;
    
    if (!$pdo) {
        return false;
    }
    
    try {
        // Check if table exists first
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($tableCheck->rowCount() === 0) {
            return false; // Can't set setting if table doesn't exist
        }
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
        
    } catch (Exception $e) {
        return false;
    }
}
?>