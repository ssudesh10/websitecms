<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

// DEBUG: Log all requests
// error_log("Settings page accessed - Method: " . $_SERVER['REQUEST_METHOD']);
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     error_log("POST Data received: " . print_r($_POST, true));
// }

// Handle license refresh BEFORE other form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_license'])) {
    error_log("License refresh initiated");
    
    // Get document root (cPanel root)
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    
    // Multiple possible root locations to check
    $possibleRoots = [
        $documentRoot,
        dirname($documentRoot),
        dirname($documentRoot, 2),
        dirname(__DIR__),
        dirname(__DIR__, 2),
        realpath(__DIR__ . '/../../'),
        realpath(__DIR__ . '/../'),
    ];
    
    $removed = [];
    $errors = [];
    $keyFiles = ['.localkey', '.suspendedkey'];
    
    foreach ($keyFiles as $fileName) {
        $fileRemoved = false;
        
        foreach ($possibleRoots as $root) {
            if (!$root || !is_dir($root)) continue;
            
            $filePath = rtrim($root, '/') . '/' . $fileName;
            
            if (file_exists($filePath)) {
                error_log("Found $fileName at: $filePath");
                
                try {
                    if (@unlink($filePath)) {
                        $removed[] = "$fileName (from $root)";
                        error_log("Successfully removed: $filePath");
                        $fileRemoved = true;
                        break; // File removed, no need to check other roots
                    } else {
                        $errors[] = "Failed to remove $fileName from $root - Permission denied";
                        error_log("Failed to remove: $filePath - Permission denied");
                        $fileRemoved = true; // Still consider it "handled"
                        break;
                    }
                } catch (Exception $e) {
                    $errors[] = "Error removing $fileName: " . $e->getMessage();
                    error_log("Exception removing $filePath: " . $e->getMessage());
                    $fileRemoved = true;
                    break;
                }
            }
        }
        
        if (!$fileRemoved) {
            // File not found in any location - this is actually good
            $removed[] = "$fileName (not found - system clean)";
            error_log("$fileName not found in any location - system is clean");
        }
    }
    
    // Prepare success/error messages
    if (!empty($removed)) {
        $success = "License cache cleared! Processed: " . implode(', ', $removed);
        if (!empty($errors)) {
            $success .= " | Warnings: " . implode(', ', $errors);
        }
        $success .= ". Please reload the site to re-check with license server.";
        error_log("License refresh completed successfully: " . $success);
    } else if (!empty($errors)) {
        $error = "Errors occurred: " . implode(', ', $errors);
        error_log("License refresh failed: " . $error);
    } else {
        $success = "License refresh completed. No files found to remove - system is clean.";
        error_log("License refresh completed: " . $success);
    }
    
    // Set active tab to license to stay on the same tab
    $activeTab = 'license';
    
    // Don't process any other form actions when refreshing license
    // This prevents the "No action specified" error
    goto skip_form_processing;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        if ($_POST['ajax_action'] === 'remove_favicon') {
            removeFaviconFromServer();
            echo json_encode(['success' => true, 'message' => 'Favicon removed successfully']);
        } elseif ($_POST['ajax_action'] === 'remove_logo') {
            removeSiteLogoFromServer();
            echo json_encode(['success' => true, 'message' => 'Site logo removed successfully']);
        } elseif ($_POST['ajax_action'] === 'remove_og_image') {
            $pageId = intval($_POST['page_id'] ?? 0);
            
            if ($pageId <= 0) {
                throw new Exception("Invalid page ID: " . $pageId);
            }
            
            // Check if page exists
            $stmt = $pdo->prepare("SELECT id FROM pages WHERE id = ?");
            $stmt->execute([$pageId]);
            if (!$stmt->fetch()) {
                throw new Exception("Page not found with ID: " . $pageId);
            }
            
            removeOGImage($pageId);
            
            echo json_encode([
                'success' => true, 
                'message' => 'OG image removed successfully',
                'page_id' => $pageId
            ]);
        } elseif ($_POST['ajax_action'] === 'scan_key_files') {
            $keyFiles = scanLicenseKeyFiles();
            echo json_encode([
                'success' => true, 
                'message' => 'Scan completed',
                'files' => $keyFiles
            ]);
        } elseif ($_POST['ajax_action'] === 'remove_key_files') {
            $results = removeLicenseKeyFiles();
            $removedFiles = array_filter($results, function($r) { return $r['status'] === 'removed'; });
            
            if (!empty($removedFiles)) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'License key files removed successfully',
                    'results' => $results
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'No files were removed or files not found',
                    'results' => $results
                ]);
            }
        } else {
            throw new Exception("Unknown AJAX action: " . $_POST['ajax_action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $error = '';
    
    try {
        if (isset($_POST['action'])) {
            error_log("Processing action: " . $_POST['action']);
            
            switch ($_POST['action']) {
                case 'general_settings':
                    updateGeneralSettings($_POST);
                    $success = "General settings updated successfully!";
                    break;
                    
                case 'contact_settings':
                    updateContactSettings($_POST);
                    $success = "Contact settings updated successfully!";
                    break;
                    
                case 'social_settings':
                    updateSocialSettings($_POST);
                    $success = "Social media settings updated successfully!";
                    break;
                    
                case 'seo_settings':
                    updateSEOSettings($_POST);
                    $success = "SEO settings updated successfully!";
                    break;
                    
                case 'page_seo_settings':
                    error_log("Processing page SEO settings for page ID: " . ($_POST['page_id'] ?? 'not set'));
                    updatePageSEOSettings($_POST);
                    $success = "Page SEO settings updated successfully!";
                    break;
                    
                case 'system_settings':
                    updateSystemSettings($_POST);
                    $success = "System settings updated successfully!";
                    break;
                    
                case 'change_password':
                    changePassword($_POST);
                    $success = "Password changed successfully!";
                    break;

                case 'update_license_key':
                    // Handle direct license key update - NO VALIDATION
                    $newLicenseKey = $_POST['license_key'] ?? ''; // No trim, no empty check
                    
                    $result = updateLicenseKeyInConfig($newLicenseKey);
                    
                    if ($result['success']) {
                        // Also update in database for consistency
                        updateSetting('license_key', $newLicenseKey);
                        $success = $result['message'];
                        
                        // IMPORTANT: Set active tab to license and ensure it stays
                        $activeTab = 'license';
                    } else {
                        throw new Exception($result['message']);
                    }
                    break;

                case 'license_settings':
                    updateLicenseSettings($_POST);
                    $success = "License settings updated successfully!";
                    break;
                
                case 'theme_settings':
                    if (isset($_POST['upload_theme'])) {
                        $result = uploadThemePackage($_FILES['theme_file'] ?? null);
                        if ($result['success']) {
                            $success = $result['message'];
                        } else {
                            throw new Exception($result['message']);
                        }
                    } else {
                        $success = updateThemeSettings($_POST);
                    }
                    break;
                    
                default:
                    error_log("Unknown action: " . $_POST['action']);
                    $error = "Unknown action specified.";
            }
            
            error_log("Action completed. Success: " . ($success ? $success : 'none') . ", Error: " . ($error ? $error : 'none'));
            
        } else {
            error_log("No action specified in POST data");
            $error = "No action specified.";
        }
    } catch (Exception $e) {
        error_log("Exception caught: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Label for skipping form processing when handling license refresh
skip_form_processing:

function updateLicenseSettings($data) {
    // Check if this is a license key update
    if (isset($data['license_key'])) {
        $newLicenseKey = $data['license_key']; // No trim, no validation
        
        // Update the license key in config.php
        $result = updateLicenseKeyInConfig($newLicenseKey);
        
        if ($result['success']) {
            // Also update it in the database settings table for consistency
            updateSetting('license_key', $newLicenseKey);
            return $result['message'];
        } else {
            throw new Exception($result['message']);
        }
    }
    
    // Handle other license settings if any
    return "License settings updated successfully!";
}

// Enhanced license key files removal function
function removeLicenseKeyFiles() {
    $results = [];
    
    // Get the document root (cPanel root)
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    
    // Try multiple possible root locations
    $possibleRoots = [
        $documentRoot,
        dirname($_SERVER['SCRIPT_FILENAME'], 2),
        dirname(__DIR__),
        realpath(__DIR__ . '/../../'),
        realpath(__DIR__ . '/../'),
    ];
    
    $keyFiles = [
        '.localkey' => '.localkey',
        '.suspendedkey' => '.suspendedkey'
    ];
    
    foreach ($keyFiles as $fileName => $file) {
        $removed = false;
        
        foreach ($possibleRoots as $root) {
            if (!$root) continue;
            
            $filePath = rtrim($root, '/') . '/' . $file;
            
            if (file_exists($filePath)) {
                try {
                    if (@unlink($filePath)) {
                        $results[] = [
                            'file' => $fileName,
                            'status' => 'removed',
                            'message' => "Successfully removed {$fileName}",
                            'path' => $filePath,
                            'root_used' => $root
                        ];
                        $removed = true;
                        break; // File removed, no need to check other roots
                    } else {
                        $results[] = [
                            'file' => $fileName,
                            'status' => 'error',
                            'message' => "Failed to remove {$fileName} - Permission denied",
                            'path' => $filePath,
                            'root_used' => $root
                        ];
                        $removed = true;
                        break;
                    }
                } catch (Exception $e) {
                    $results[] = [
                        'file' => $fileName,
                        'status' => 'error',
                        'message' => "Error removing {$fileName}: " . $e->getMessage(),
                        'path' => $filePath,
                        'root_used' => $root
                    ];
                    $removed = true;
                    break;
                }
            }
        }
        
        if (!$removed) {
            $results[] = [
                'file' => $fileName,
                'status' => 'not_found',
                'message' => "{$fileName} not found in cPanel root",
                'searched_paths' => array_map(function($root) use ($file) {
                    return rtrim($root, '/') . '/' . $file;
                }, array_filter($possibleRoots))
            ];
        }
    }
    
    return $results;
}

// Enhanced license key files scanning function
function scanLicenseKeyFiles() {
    $results = [];
    
    // Get the document root (usually public_html)
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    
    // Build comprehensive list of possible root locations
    $possibleRoots = [
        // Current document root
        $documentRoot,
        
        // Parent of document root (typical shared hosting structure)
        dirname($documentRoot),
        
        // Go up multiple levels from document root
        dirname($documentRoot, 2),
        dirname($documentRoot, 3),
        
        // Script-based path detection
        dirname($_SERVER['SCRIPT_FILENAME'], 2),
        dirname($_SERVER['SCRIPT_FILENAME'], 3),
        dirname($_SERVER['SCRIPT_FILENAME'], 4),
        
        // Directory-based detection
        dirname(__DIR__),
        dirname(__DIR__, 2),
        dirname(__DIR__, 3),
        
        // Real path resolution
        realpath(__DIR__ . '/../../'),
        realpath(__DIR__ . '/../../../'),
        realpath(__DIR__ . '/../../../../'),
        
        // Home directory detection (if available)
        getenv('HOME'),
        
        // Try to detect cPanel user home directory
        // In shared hosting: /home/username/
        preg_match('/\/home\/([^\/]+)\//', $documentRoot, $matches) ? '/home/' . $matches[1] : null,
        
        // Common shared hosting patterns
        str_replace('/public_html', '', $documentRoot),
        str_replace('/htdocs', '', $documentRoot),
        str_replace('/www', '', $documentRoot),
    ];
    
    // Remove duplicates and null values
    $possibleRoots = array_unique(array_filter($possibleRoots));
    
    $keyFiles = [
        '.localkey' => '.localkey',
        '.suspendedkey' => '.suspendedkey'
    ];
    
    foreach ($keyFiles as $fileName => $file) {
        $found = false;
        
        foreach ($possibleRoots as $root) {
            if (!$root || !is_dir($root)) continue;
            
            $filePath = rtrim($root, '/') . '/' . $file;
            
            if (file_exists($filePath)) {
                $found = true;
                
                $fileInfo = [
                    'file' => $fileName,
                    'exists' => true,
                    'path' => $filePath,
                    'root_used' => $root,
                    'size' => filesize($filePath),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'modified_timestamp' => filemtime($filePath),
                    'readable' => is_readable($filePath),
                    'writable' => is_writable($filePath),
                    'permissions' => substr(sprintf('%o', fileperms($filePath)), -4)
                ];
                
                // Try to read file content (if it's small and readable)
                if ($fileInfo['readable'] && $fileInfo['size'] < 2048) {
                    try {
                        $content = file_get_contents($filePath);
                        $fileInfo['content_preview'] = substr($content, 0, 200);
                        $fileInfo['content_length'] = strlen($content);
                        $fileInfo['content_lines'] = substr_count($content, "\n") + 1;
                    } catch (Exception $e) {
                        $fileInfo['content_preview'] = 'Unable to read file: ' . $e->getMessage();
                        $fileInfo['content_length'] = 0;
                        $fileInfo['content_lines'] = 0;
                    }
                } else {
                    $fileInfo['content_preview'] = $fileInfo['readable'] ? 'File too large to preview' : 'File not readable';
                    $fileInfo['content_length'] = $fileInfo['size'];
                    $fileInfo['content_lines'] = 0;
                }
                
                $results[] = $fileInfo;
                break; // Found the file, no need to check other roots
            }
        }
        
        if (!$found) {
            $results[] = [
                'file' => $fileName,
                'exists' => false,
                'message' => 'File not found in any searched location',
                'searched_paths' => array_map(function($root) use ($file) {
                    return rtrim($root, '/') . '/' . $file;
                }, $possibleRoots)
            ];
        }
    }
    
    return $results;
}


// Get current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = "Error getting settings: " . $e->getMessage();
}

// Get pages
$pages = [];
try {
    $stmt = $pdo->query("SELECT * FROM pages WHERE is_active = 1 ORDER BY sort_order ASC");
    $pages = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error getting pages: " . $e->getMessage();
}

// Get current admin user info
$currentAdmin = [];
try {
    $stmt = $pdo->prepare("SELECT id, username, email, created_at, last_login FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $currentAdmin = $stmt->fetch();
} catch (Exception $e) {
    error_log("Error getting admin user info: " . $e->getMessage());
}

function getPageSEO($page_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM page_seo WHERE page_id = ?");
        $stmt->execute([$page_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting page SEO for page $page_id: " . $e->getMessage());
        return false;
    }
}

function updateSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    } catch (Exception $e) {
        error_log("Error updating setting $key: " . $e->getMessage());
        throw $e;
    }
}

function changePassword($data) {
    global $pdo;
    
    error_log("changePassword called");
    
    // Check if admin_id is set in session
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        throw new Exception("Admin session not found. Please log in again.");
    }
    
    // Validate required fields
    if (empty($data['current_password']) || empty($data['new_password']) || empty($data['confirm_password'])) {
        throw new Exception("All password fields are required");
    }
    
    // Check if new passwords match
    if ($data['new_password'] !== $data['confirm_password']) {
        throw new Exception("New passwords do not match");
    }
    
    // Validate password strength
    if (strlen($data['new_password']) < 6) {
        throw new Exception("New password must be at least 6 characters long");
    }
    
    // Get current admin user from 'admins' table
    try {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        
        error_log("Admin lookup for ID: " . $_SESSION['admin_id'] . " - Found: " . ($admin ? 'YES' : 'NO'));
        
        if (!$admin) {
            throw new Exception("Admin user not found in database. Session ID: " . $_SESSION['admin_id']);
        }
    } catch (PDOException $e) {
        error_log("Database error in admin lookup: " . $e->getMessage());
        throw new Exception("Database error while looking up admin user");
    }
    
    // Verify current password
    if (!password_verify($data['current_password'], $admin['password'])) {
        throw new Exception("Current password is incorrect");
    }
    
    // Update password in 'admins' table
    try {
        $newPasswordHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $result = $stmt->execute([$newPasswordHash, $_SESSION['admin_id']]);
        
        if (!$result) {
            throw new Exception("Failed to update password");
        }
        
        error_log("Password updated successfully for admin ID: " . $_SESSION['admin_id']);
    } catch (PDOException $e) {
        error_log("Database error updating password: " . $e->getMessage());
        throw new Exception("Database error while updating password");
    }
}

function uploadFavicon($file) {
    global $settings;
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file type
    $allowedTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/ico', 'image/icon', 'image/png', 'image/jpeg', 'image/gif'];
    $fileType = $file['type'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['ico', 'png', 'jpg', 'jpeg', 'gif'];
    
    if (!in_array($fileType, $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Invalid file type. Please upload an ICO, PNG, JPG, or GIF file.");
    }
    
    // Validate file size (max 1MB for favicon)
    $maxSize = 1 * 1024 * 1024; // 1MB
    if ($file['size'] > $maxSize) {
        throw new Exception("File size too large. Maximum size is 1MB.");
    }
    
    // Generate unique filename
    $filename = 'favicon_' . time() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception("Failed to upload file.");
    }
    
    // Delete old favicon if it exists
    $oldFavicon = $settings['favicon_url'] ?? '';
    if ($oldFavicon && file_exists('../' . $oldFavicon)) {
        unlink('../' . $oldFavicon);
    }
    
    // Update favicon_enabled to 1 when favicon is uploaded
    updateSetting('favicon_enabled', '1');
    
    // Return the relative path
    return 'uploads/' . $filename;
}

function removeFaviconFromServer() {
    global $pdo, $settings;
    
    try {
        // Get current favicon path
        $currentFavicon = $settings['favicon_url'] ?? '';
        
        // Delete physical file if it exists
        if ($currentFavicon && file_exists('../' . $currentFavicon)) {
            unlink('../' . $currentFavicon);
        }
        
        // Update database settings
        updateSetting('favicon_url', '');
        updateSetting('favicon_enabled', '0');
        
        return true;
    } catch (Exception $e) {
        error_log("Error removing favicon: " . $e->getMessage());
        throw new Exception("Failed to remove favicon: " . $e->getMessage());
    }
}

function uploadOGImage($file, $pageId) {
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/og-images/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $file['type'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($fileType, $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.");
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception("File size too large. Maximum size is 5MB.");
    }
    
    // Validate image
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception("Invalid image file.");
    }
    
    // Generate unique filename
    $filename = 'og_page_' . $pageId . '_' . time() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception("Failed to upload OG image.");
    }
    
    // Return relative path for saving in database
    return 'uploads/og-images/' . $filename;
}

function removeOGImage($pageId) {
    global $pdo;
    
    try {
        error_log("removeOGImage called with page ID: " . $pageId);
        
        // Get current OG image path
        $stmt = $pdo->prepare("SELECT og_image FROM page_seo WHERE page_id = ?");
        $stmt->execute([$pageId]);
        $currentOGImage = $stmt->fetchColumn();
        
        error_log("Current OG image found: " . ($currentOGImage ?: 'none'));
        
        if ($currentOGImage) {
            // Delete physical file if it's a local file
            if (strpos($currentOGImage, 'uploads/') === 0) {
                $filePath = '../' . $currentOGImage;
                error_log("Attempting to delete file: " . $filePath);
                
                if (file_exists($filePath)) {
                    if (unlink($filePath)) {
                        error_log("File deleted successfully: " . $filePath);
                    } else {
                        error_log("Failed to delete file: " . $filePath);
                    }
                } else {
                    error_log("File not found: " . $filePath);
                }
            }
        }
        
        // Update database to remove OG image
        $stmt = $pdo->prepare("UPDATE page_seo SET og_image = '' WHERE page_id = ?");
        $result = $stmt->execute([$pageId]);
        
        error_log("Database update result: " . ($result ? 'success' : 'failed'));
        error_log("Rows affected: " . $stmt->rowCount());
        
        if (!$result) {
            throw new Exception("Database update failed");
        }
        
        // If no rows were affected, the page_seo record might not exist
        if ($stmt->rowCount() === 0) {
            error_log("No rows affected - checking if page_seo record exists");
            
            // Check if page_seo record exists
            $stmt = $pdo->prepare("SELECT id FROM page_seo WHERE page_id = ?");
            $stmt->execute([$pageId]);
            
            if (!$stmt->fetch()) {
                error_log("No page_seo record found for page ID: " . $pageId);
                // This is actually success since there's no OG image to remove
                return true;
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error in removeOGImage: " . $e->getMessage());
        throw new Exception("Failed to remove OG image: " . $e->getMessage());
    }
}

function updatePageSEOSettings($data) {
    global $pdo;
    
    // Validate required field
    if (!isset($data['page_id']) || empty($data['page_id'])) {
        throw new Exception("Page ID is required for SEO settings");
    }
    
    $page_id = intval($data['page_id']);
    
    // Handle OG image removal first
    if (isset($data['remove_og_image']) && $data['remove_og_image'] === '1') {
        removeOGImage($page_id);
    }
    
    // Handle OG image upload
    $og_image = trim($data['og_image'] ?? '');
    
    // If file upload is present, it takes priority over manual URL input
    if (isset($_FILES['og_image_file']) && $_FILES['og_image_file']['error'] === UPLOAD_ERR_OK) {
        try {
            // Remove old OG image before uploading new one
            removeOGImage($page_id);
            
            // Upload new OG image and get relative path
            $og_image = uploadOGImage($_FILES['og_image_file'], $page_id);
        } catch (Exception $e) {
            throw new Exception("OG image upload failed: " . $e->getMessage());
        }
    }
    
    // Get other form data
    $meta_title = trim($data['meta_title'] ?? '');
    $meta_description = trim($data['meta_description'] ?? '');
    $meta_keywords = trim($data['meta_keywords'] ?? '');
    $og_title = trim($data['og_title'] ?? '');
    $og_description = trim($data['og_description'] ?? '');
    $canonical_url = trim($data['canonical_url'] ?? '');
    $robots = $data['robots'] ?? 'index,follow';
    
    try {
        // Check if page SEO record exists
        $stmt = $pdo->prepare("SELECT id FROM page_seo WHERE page_id = ?");
        $stmt->execute([$page_id]);
        $existingRecord = $stmt->fetch();
        
        if ($existingRecord) {
            // Update existing record
            $sql = "UPDATE page_seo SET 
                meta_title = ?, meta_description = ?, meta_keywords = ?, 
                og_title = ?, og_description = ?, og_image = ?, 
                canonical_url = ?, robots = ?, updated_at = NOW() 
                WHERE page_id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $meta_title, $meta_description, $meta_keywords, 
                $og_title, $og_description, $og_image, 
                $canonical_url, $robots, $page_id
            ]);
        } else {
            // Insert new record
            $sql = "INSERT INTO page_seo 
                (page_id, meta_title, meta_description, meta_keywords, 
                 og_title, og_description, og_image, canonical_url, robots) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $page_id, $meta_title, $meta_description, $meta_keywords, 
                $og_title, $og_description, $og_image, $canonical_url, $robots
            ]);
        }
        
        if (!$result) {
            throw new Exception("Database operation failed");
        }
        
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
}

// Add these functions to your existing settings.php file

function uploadSiteLogo($file) {
    global $settings;
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file type
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/svg+xml'];
    $fileType = $file['type'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
    
    if (!in_array($fileType, $allowedTypes) && !in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Invalid file type. Please upload a PNG, JPG, GIF, WebP, or SVG file.");
    }
    
    // Validate file size (max 2MB for logo)
    $maxSize = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $maxSize) {
        throw new Exception("File size too large. Maximum size is 2MB.");
    }
    
    // Validate image dimensions (optional - you can adjust these)
    if ($fileExtension !== 'svg') {
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception("Invalid image file.");
        }
        
        // Check if image is too large (optional constraint)
        $maxWidth = 1000;
        $maxHeight = 500;
        if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
            throw new Exception("Image dimensions too large. Maximum size is {$maxWidth}x{$maxHeight}px.");
        }
    }
    
    // Generate unique filename
    $filename = 'site_logo_' . time() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception("Failed to upload file.");
    }
    
    // Delete old logo if it exists
    $oldLogo = $settings['site_logo_url'] ?? '';
    if ($oldLogo && file_exists('../' . $oldLogo)) {
        unlink('../' . $oldLogo);
    }
    
    // Update logo_enabled to 1 when logo is uploaded
    updateSetting('site_logo_enabled', '1');
    
    // Return the relative path
    return 'uploads/' . $filename;
}

function removeSiteLogoFromServer() {
    global $pdo, $settings;
    
    try {
        // Get current logo path
        $currentLogo = $settings['site_logo_url'] ?? '';
        
        // Delete physical file if it exists
        if ($currentLogo && file_exists('../' . $currentLogo)) {
            unlink('../' . $currentLogo);
        }
        
        // Update database settings
        updateSetting('site_logo_url', '');
        updateSetting('site_logo_enabled', '0');
        
        return true;
    } catch (Exception $e) {
        error_log("Error removing site logo: " . $e->getMessage());
        throw new Exception("Failed to remove site logo: " . $e->getMessage());
    }
}

function uploadThemePackage($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error occurred.'];
    }
    
    // Validate file type
    $allowedTypes = ['application/zip', 'application/x-zip-compressed'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file['type'], $allowedTypes) && $fileExtension !== 'zip') {
        return ['success' => false, 'message' => 'Invalid file type. Please upload a ZIP file.'];
    }
    
    // Validate file size (max 10MB)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size too large. Maximum size is 10MB.'];
    }
    
    // Create temp directory for extraction
    $tempDir = sys_get_temp_dir() . '/theme_upload_' . uniqid();
    if (!mkdir($tempDir, 0755, true)) {
        return ['success' => false, 'message' => 'Failed to create temporary directory.'];
    }
    
    try {
        // Extract ZIP file
        $zip = new ZipArchive();
        $result = $zip->open($file['tmp_name']);
        
        if ($result !== TRUE) {
            return ['success' => false, 'message' => 'Failed to open ZIP file. Error code: ' . $result];
        }
        
        // Extract to temp directory
        $zip->extractTo($tempDir);
        $zip->close();
        
        // Look for theme.json file (theme configuration)
        $themeJsonPath = findThemeJson($tempDir);
        if (!$themeJsonPath) {
            cleanupTempDir($tempDir);
            return ['success' => false, 'message' => 'Invalid theme package. No theme.json file found.'];
        }
        
        // Parse theme configuration
        $themeConfig = json_decode(file_get_contents($themeJsonPath), true);
        if (!$themeConfig) {
            cleanupTempDir($tempDir);
            return ['success' => false, 'message' => 'Invalid theme.json file.'];
        }
        
        // Validate required fields
        $requiredFields = ['name', 'display_name', 'version'];
        foreach ($requiredFields as $field) {
            if (empty($themeConfig[$field])) {
                cleanupTempDir($tempDir);
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }
        
        // Sanitize theme name
        $themeName = sanitizeThemeName($themeConfig['name']);
        
        // Check if theme already exists
        if (themeExists($themeName)) {
            cleanupTempDir($tempDir);
            return ['success' => false, 'message' => "Theme '{$themeName}' already exists. Please remove it first or choose a different theme."];
        }
        
        // Install theme
        $installResult = installThemeFromTemp($tempDir, $themeName, $themeConfig);
        cleanupTempDir($tempDir);
        
        return $installResult;
        
    } catch (Exception $e) {
        cleanupTempDir($tempDir);
        return ['success' => false, 'message' => 'Error processing theme: ' . $e->getMessage()];
    }
}

function findThemeJson($directory) {
    // Check root directory first
    $rootPath = $directory . '/theme.json';
    if (file_exists($rootPath)) {
        return $rootPath;
    }
    
    // Check subdirectories (one level deep)
    $dirs = glob($directory . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $subPath = $dir . '/theme.json';
        if (file_exists($subPath)) {
            return $subPath;
        }
    }
    
    return false;
}

function themeExists($themeName) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM themes WHERE theme_name = ?");
        $stmt->execute([$themeName]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function installThemeFromTemp($tempDir, $themeName, $themeConfig) {
    global $pdo;
    
    try {
        // Create theme directory
        $themeDir = '../themes/' . $themeName;
        if (!is_dir('../themes')) {
            mkdir('../themes', 0755, true);
        }
        
        if (!mkdir($themeDir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create theme directory.'];
        }
        
        // Copy theme files
        $themeJsonDir = dirname(findThemeJson($tempDir));
        if (!copyDirectory($themeJsonDir, $themeDir)) {
            // Cleanup on failure
            removeDirectory($themeDir);
            return ['success' => false, 'message' => 'Failed to copy theme files.'];
        }
        
        // Prepare theme configuration for database
        $dbConfig = [
            'primary_color' => $themeConfig['colors']['primary'] ?? '#3b82f6',
            'secondary_color' => $themeConfig['colors']['secondary'] ?? '#6b7280',
            'font_family' => $themeConfig['typography']['font_family'] ?? 'Inter',
            'button_style' => $themeConfig['styles']['button_style'] ?? 'rounded'
        ];
        
        // Insert theme into database
        $stmt = $pdo->prepare("
            INSERT INTO themes (theme_name, theme_display_name, theme_description, theme_author, theme_version, theme_config, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");
        
        $result = $stmt->execute([
            $themeName,
            $themeConfig['display_name'],
            $themeConfig['description'] ?? '',
            $themeConfig['author'] ?? '',
            $themeConfig['version'],
            json_encode($dbConfig)
        ]);
        
        if (!$result) {
            // Cleanup on database failure
            removeDirectory($themeDir);
            return ['success' => false, 'message' => 'Failed to register theme in database.'];
        }
        
        return [
            'success' => true, 
            'message' => "Theme '{$themeConfig['display_name']}' uploaded and installed successfully!"
        ];
        
    } catch (Exception $e) {
        // Cleanup on any error
        if (isset($themeDir) && is_dir($themeDir)) {
            removeDirectory($themeDir);
        }
        return ['success' => false, 'message' => 'Installation failed: ' . $e->getMessage()];
    }
}

function copyDirectory($source, $destination) {
    if (!is_dir($source)) {
        return false;
    }
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
        } else {
            if (!copy($item, $targetPath)) {
                return false;
            }
        }
    }
    
    return true;
}

function removeDirectory($directory) {
    if (!is_dir($directory)) {
        return false;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item);
        } else {
            unlink($item);
        }
    }
    
    return rmdir($directory);
}

function cleanupTempDir($tempDir) {
    if (is_dir($tempDir)) {
        removeDirectory($tempDir);
    }
}

function updateThemeSettings($data) {
    if (isset($data['upload_theme'])) {
        // This is handled in the POST processing section above
        return "Theme uploaded successfully!";
    }
    
    if (isset($data['activate_theme'])) {
        $themeName = $data['activate_theme'];
        if (activateTheme($themeName)) {
            return "Theme activated successfully!";
        } else {
            throw new Exception("Failed to activate theme");
        }
    }
    
    if (isset($data['delete_theme'])) {
        $themeName = $data['delete_theme'];
        if (deleteTheme($themeName)) {
            return "Theme deleted successfully!";
        } else {
            throw new Exception("Cannot delete this theme");
        }
    }
    
    throw new Exception("Unknown theme action");
}

function sanitizeThemeName($name) {
    return preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($name)));
}

// Add AJAX handler for logo removal in your existing AJAX section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // ... existing AJAX code ...
    
    try {
        if ($_POST['ajax_action'] === 'remove_logo') {
            removeSiteLogoFromServer();
            echo json_encode(['success' => true, 'message' => 'Site logo removed successfully']);
        } elseif ($_POST['ajax_action'] === 'remove_favicon') {
            // ... existing favicon removal code ...
        } elseif ($_POST['ajax_action'] === 'remove_og_image') {
            // ... existing og image removal code ...
        } else {
            throw new Exception("Unknown AJAX action: " . $_POST['ajax_action']);
        }
    } catch (Exception $e) {
        error_log("AJAX Exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}



function updateGeneralSettings($data) {
    updateSetting('site_name', $data['site_name'] ?? '');
    updateSetting('site_tagline', $data['site_tagline'] ?? '');
    updateSetting('site_description', $data['site_description'] ?? '');
    updateSetting('admin_email', $data['admin_email'] ?? '');
    updateSetting('timezone', $data['timezone'] ?? 'UTC');
    updateSetting('maintenance_mode', isset($data['maintenance_mode']) ? '1' : '0');
    
    // Handle logo removal
    if (isset($data['remove_logo']) && $data['remove_logo'] === '1') {
        removeSiteLogoFromServer();
        return; // Exit early to avoid processing upload
    }
    
    // Handle logo upload
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        try {
            $logoPath = uploadSiteLogo($_FILES['site_logo']);
            updateSetting('site_logo_url', $logoPath);
        } catch (Exception $e) {
            throw new Exception("Site logo upload failed: " . $e->getMessage());
        }
    }
    
    // Handle favicon removal
    if (isset($data['remove_favicon']) && $data['remove_favicon'] === '1') {
        removeFaviconFromServer();
        return; // Exit early to avoid processing upload
    }
    
    // Handle favicon upload
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        try {
            $faviconPath = uploadFavicon($_FILES['favicon']);
            updateSetting('favicon_url', $faviconPath);
        } catch (Exception $e) {
            throw new Exception("Favicon upload failed: " . $e->getMessage());
        }
    }
}

function updateLicenseKeyInConfig($newLicenseKey) {
    try {
        // Get the config file path - go up one level from admin folder to reach root
        $configFile = dirname(__DIR__) . '/config.php';
        
        // Alternative paths to try if the above doesn't work
        $alternativePaths = [
            __DIR__ . '/../config.php',
            $_SERVER['DOCUMENT_ROOT'] . '/config.php',
            realpath(__DIR__ . '/../config.php'),
            dirname($_SERVER['SCRIPT_FILENAME']) . '/../config.php'
        ];
        
        // Check if primary path exists, if not try alternatives
        if (!file_exists($configFile)) {
            foreach ($alternativePaths as $altPath) {
                if (file_exists($altPath)) {
                    $configFile = $altPath;
                    break;
                }
            }
        }
        
        // Check if config file exists
        if (!file_exists($configFile)) {
            throw new Exception("Config file not found.");
        }
        
        // Read current config file
        $configContent = file_get_contents($configFile);
        if ($configContent === false) {
            throw new Exception("Unable to read config file.");
        }
        
        // Create backup of config file
        $backupFile = $configFile . '.backup.' . date('Y-m-d_H-i-s');
        copy($configFile, $backupFile); // Don't check if backup fails
        
        // Pattern to match SERIAL_NUMBER definition
        $pattern = '/define\s*\(\s*[\'"]SERIAL_NUMBER[\'"]\s*,\s*[\'"][^\'"]*[\'"]([^)]*)\);/';
        
        // Check if SERIAL_NUMBER already exists
        if (preg_match($pattern, $configContent)) {
            // Replace existing SERIAL_NUMBER
            $newConfigContent = preg_replace(
                $pattern,
                "define('SERIAL_NUMBER', '$newLicenseKey');",
                $configContent
            );
        } else {
            // Add SERIAL_NUMBER at the end before closing PHP tag
            $newConfigContent = $configContent;
            
            // Remove closing PHP tag if exists
            $newConfigContent = preg_replace('/\s*\?\>\s*$/', '', $newConfigContent);
            
            // Add SERIAL_NUMBER definition
            $newConfigContent .= "\n\n// Serial Number (License Key) Configuration\n";
            $newConfigContent .= "define('SERIAL_NUMBER', '$newLicenseKey');\n";
            
            // Add closing PHP tag back
            $newConfigContent .= "\n?>";
        }
        
        // Write updated config file - NO VERIFICATION
        file_put_contents($configFile, $newConfigContent, LOCK_EX);
        
        // Log the change
        error_log("License key (SERIAL_NUMBER) updated in config file: " . $configFile);
        
        return [
            'success' => true,
            'message' => 'License key saved successfully',
            'backup_file' => $backupFile,
            'config_file' => $configFile
        ];
        
    } catch (Exception $e) {
        error_log("Error updating license key in config: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function getLicenseKeyFromConfig() {
    try {
        // Get the config file path - go up one level from admin folder to reach root
        $configFile = dirname(__DIR__) . '/config.php';
        
        // Alternative paths to try if the above doesn't work
        $alternativePaths = [
            __DIR__ . '/../config.php',           // Go up one level from admin
            $_SERVER['DOCUMENT_ROOT'] . '/config.php',  // Document root
            realpath(__DIR__ . '/../config.php'), // Real path resolution
            dirname($_SERVER['SCRIPT_FILENAME']) . '/../config.php' // Script location
        ];
        
        // Check if primary path exists, if not try alternatives
        if (!file_exists($configFile)) {
            foreach ($alternativePaths as $altPath) {
                if ($altPath && file_exists($altPath)) {
                    $configFile = $altPath;
                    break;
                }
            }
        }
        
        if (!file_exists($configFile)) {
            error_log("Config file not found in any of the expected locations");
            return '';
        }
        
        $configContent = file_get_contents($configFile);
        if ($configContent === false) {
            error_log("Unable to read config file: " . $configFile);
            return '';
        }
        
        // Extract license key using regex (look for SERIAL_NUMBER)
        if (preg_match('/define\s*\(\s*[\'"]SERIAL_NUMBER[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/', $configContent, $matches)) {
            return $matches[1];
        }
        
        // Fallback: look for LICENSE_KEY if SERIAL_NUMBER not found
        if (preg_match('/define\s*\(\s*[\'"]LICENSE_KEY[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/', $configContent, $matches)) {
            return $matches[1];
        }
        
        return '';
        
    } catch (Exception $e) {
        error_log("Error reading license key from config: " . $e->getMessage());
        return '';
    }
}

// Get the current serial number directly from the constant
function getCurrentSerialNumber() {
    if (defined('SERIAL_NUMBER')) {
        return SERIAL_NUMBER;
    }
    
    // Fallback to reading from config file
    return getLicenseKeyFromConfig();
}

// Set all license variables to safe defaults first
// $licenseStatus = ['status' => 'Unknown', 'message' => 'Not checked', 'color' => 'gray'];
// $statusColorClass = 'text-gray-700';
// $iconClass = 'fas fa-question-circle';
// $statusText = 'License Status Unknown';
// $hasLicenseKey = false;
// $currentLicenseKey = '';

/**
 * Enhanced license status function with full error handling
 * @param array $settings - Settings array containing license_key and local_key
 * @return array - License status array with status, message, and color
 */

function getLicenseStatus($settings) {
    $licenseKey = $settings['license_key'] ?? '';
    $localKey = $settings['local_key'] ?? '';
    
    if (empty($licenseKey)) {
        return [
            'status' => 'inactive', 
            'message' => 'No license key provided', 
            'color' => 'gray',
            'icon' => 'fas fa-exclamation-triangle'
        ];
    }
    
    try {
        // Include the license checking file
        if (file_exists('../core/licence.php')) {
            require_once '../core/licence.php';
            
            // Call the WHMCS license check function
            $licenseResult = peek_check_license($licenseKey, $localKey);
            
            // Update local key if provided in response
            if (isset($licenseResult['localkey'])) {
                updateSetting('local_key', $licenseResult['localkey']);
            }
            
            // Process the license result
            return processLicenseResult($licenseResult);
        } else {
            return [
                'status' => 'unknown', 
                'message' => 'License validation system not found', 
                'color' => 'gray',
                'icon' => 'fas fa-question-circle'
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'error', 
            'message' => 'License check failed: ' . $e->getMessage(), 
            'color' => 'red',
            'icon' => 'fas fa-exclamation-triangle'
        ];
    }
}

function processLicenseResult($result) {
    if (!isset($result['status'])) {
        return [
            'status' => 'error', 
            'message' => 'Invalid license response', 
            'color' => 'red',
            'icon' => 'fas fa-exclamation-triangle'
        ];
    }
    
    switch (strtolower($result['status'])) {
        case 'active':
            $message = 'License is active and valid';
            $color = 'green';
            $icon = 'fas fa-check-circle';
            
            // Add expiry date if available
            if (isset($result['nextduedate']) && !empty($result['nextduedate'])) {
                $expiryDate = strtotime($result['nextduedate']);
                $daysUntilExpiry = floor(($expiryDate - time()) / (24 * 60 * 60));
                
                if ($daysUntilExpiry <= 30 && $daysUntilExpiry > 0) {
                    $message .= ' (Expires in ' . $daysUntilExpiry . ' days)';
                    $color = 'yellow';
                    $icon = 'fas fa-exclamation-triangle';
                } elseif ($daysUntilExpiry <= 0) {
                    return [
                        'status' => 'expired', 
                        'message' => 'License expired', 
                        'color' => 'red',
                        'icon' => 'fas fa-times-circle'
                    ];
                } else {
                    $message .= ' (Expires on ' . date('M j, Y', $expiryDate) . ')';
                }
            }
            
            return [
                'status' => 'active', 
                'message' => $message, 
                'color' => $color,
                'icon' => $icon
            ];
            
        case 'invalid':
            $description = isset($result['description']) ? $result['description'] : 'License key is invalid';
            return [
                'status' => 'invalid', 
                'message' => $description, 
                'color' => 'red',
                'icon' => 'fas fa-times-circle'
            ];
            
        case 'expired':
            $description = isset($result['description']) ? $result['description'] : 'License has expired';
            return [
                'status' => 'expired', 
                'message' => $description, 
                'color' => 'red',
                'icon' => 'fas fa-times-circle'
            ];
            
        case 'suspended':
            $description = isset($result['description']) ? $result['description'] : 'License is suspended';
            return [
                'status' => 'suspended', 
                'message' => $description, 
                'color' => 'red',
                'icon' => 'fas fa-ban'
            ];
            
        default:
            $description = isset($result['description']) ? $result['description'] : 'License status unknown';
            return [
                'status' => 'inactive', 
                'message' => $description, 
                'color' => 'gray',
                'icon' => 'fas fa-question-circle'
            ];
    }
}

// Get the license status - THIS IS THE MAIN PART YOU NEED
$currentLicenseKey = getLicenseKeyFromConfig();
$hasLicenseKey = !empty($currentLicenseKey);

// Get current license status
$licenseStatus = [
    'status' => 'Unknown',
    'message' => 'Not checked',
    'color' => 'gray',
    'icon' => 'fas fa-question-circle'
];

if ($hasLicenseKey) {
    $licenseStatus = getLicenseStatus($settings);
}

// Set simple status variables for display
$statusColorClass = 'text-gray-700';
$iconClass = 'fas fa-question-circle';
$statusText = 'License Status Unknown';

switch ($licenseStatus['color']) {
    case 'green':
        $statusColorClass = 'text-green-700';
        $iconClass = 'fas fa-check-circle';
        $statusText = 'License Active';
        break;
    case 'yellow':
        $statusColorClass = 'text-yellow-700';
        $iconClass = 'fas fa-exclamation-triangle';
        $statusText = 'License Expiring Soon';
        break;
    case 'red':
        $statusColorClass = 'text-red-700';
        $iconClass = 'fas fa-times-circle';
        $statusText = 'License Issue';
        break;
    default:
        $statusColorClass = 'text-gray-700';
        $iconClass = 'fas fa-question-circle';
        $statusText = 'License Status Unknown';
}

// Tab badge class for license tab button
function getLicenseTabBadgeClass($status) {
    switch ($status['color']) {
        case 'green':
            return 'bg-green-500';
        case 'yellow':
            return 'bg-yellow-500';
        case 'red':
            return 'bg-red-500';
        default:
            return 'bg-gray-500';
    }
}

$licenseTabBadgeClass = getLicenseTabBadgeClass($licenseStatus);

// Add this debugging code to see what's actually happening with your license


function updateContactSettings($data) {
    updateSetting('contact_email', $data['contact_email'] ?? '');
    updateSetting('contact_phone', $data['contact_phone'] ?? '');
    updateSetting('contact_address', $data['contact_address'] ?? '');
    updateSetting('business_hours', $data['business_hours'] ?? '');
}

function updateSocialSettings($data) {
    updateSetting('facebook_url', $data['facebook_url'] ?? '');
    updateSetting('twitter_url', $data['twitter_url'] ?? '');
    updateSetting('linkedin_url', $data['linkedin_url'] ?? '');
    updateSetting('instagram_url', $data['instagram_url'] ?? '');
    updateSetting('youtube_url', $data['youtube_url'] ?? '');
}

function updateSEOSettings($data) {
    updateSetting('default_meta_title', $data['default_meta_title'] ?? '');
    updateSetting('default_meta_description', $data['default_meta_description'] ?? '');
    updateSetting('default_meta_keywords', $data['default_meta_keywords'] ?? '');
    updateSetting('google_analytics_id', $data['google_analytics_id'] ?? '');
    updateSetting('google_search_console', $data['google_search_console'] ?? '');
}

function updateSystemSettings($data) {
    updateSetting('max_upload_size', $data['max_upload_size'] ?? '5');
    updateSetting('allowed_file_types', $data['allowed_file_types'] ?? 'jpg,jpeg,png,gif,webp,svg');
    updateSetting('items_per_page', $data['items_per_page'] ?? '10');
    updateSetting('enable_comments', isset($data['enable_comments']) ? '1' : '0');
    updateSetting('cache_enabled', isset($data['cache_enabled']) ? '1' : '0');
    
    // Add reCAPTCHA settings
    updateSetting('recaptcha_enabled', isset($data['recaptcha_enabled']) ? '1' : '0');
    updateSetting('recaptcha_site_key', $data['recaptcha_site_key'] ?? '');
    updateSetting('recaptcha_secret_key', $data['recaptcha_secret_key'] ?? '');
}

// Create tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_seo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        page_id INT NOT NULL,
        meta_title VARCHAR(255),
        meta_description TEXT,
        meta_keywords TEXT,
        og_title VARCHAR(255),
        og_description TEXT,
        og_image VARCHAR(255),
        canonical_url VARCHAR(255),
        robots VARCHAR(100) DEFAULT 'index,follow',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_page_id (page_id)
    )");
    
    // Check if admin_users table exists and has required columns
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    if ($stmt->rowCount() == 0) {
        // Create admin_users table if it doesn't exist
        $pdo->exec("CREATE TABLE admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )");
    } else {
        // Check if required columns exist
        $columns = $pdo->query("SHOW COLUMNS FROM admin_users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('updated_at', $columns)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        if (!in_array('last_login', $columns)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN last_login TIMESTAMP NULL");
        }
    }
    
    // Add reCAPTCHA settings to default settings if they don't exist
    $recaptchaSettings = [
        'recaptcha_enabled' => '0',
        'recaptcha_site_key' => '',
        'recaptcha_secret_key' => ''
    ];
    
    foreach ($recaptchaSettings as $key => $defaultValue) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $defaultValue]);
    }

    // Add license settings to default settings if they don't exist
    $licenseSettings = [
        'license_key' => '',
        'license_type' => '',
        'license_holder' => '',
        'license_organization' => '',
        'license_valid_from' => '',
        'license_valid_until' => '',
        'license_features' => '',
        'license_status' => 'inactive',
        'license_terms_url' => '',
        'license_support_email' => ''
    ];

    foreach ($licenseSettings as $key => $defaultValue) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $defaultValue]);
    }
    
} catch (Exception $e) {
    error_log("Error creating tables: " . $e->getMessage());
}

// Determine which tab to show
// Initialize activeTab with proper detection
$activeTab = 'general'; // Default

// Check if we have a current_tab parameter (from form submission)
if (isset($_POST['current_tab']) && !empty($_POST['current_tab'])) {
    $activeTab = $_POST['current_tab'];
} 
// Check URL hash parameter
elseif (isset($_GET['tab']) && !empty($_GET['tab'])) {
    $activeTab = $_GET['tab'];
}
// Check if this is a specific action that should determine the tab
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_license_key':
        case 'license_settings':
            $activeTab = 'license';
            break;
        case 'general_settings':
            $activeTab = 'general';
            break;
        case 'contact_settings':
            $activeTab = 'contact';
            break;
        case 'social_settings':
            $activeTab = 'social';
            break;
        case 'seo_settings':
            $activeTab = 'seo';
            break;
        case 'page_seo_settings':
            $activeTab = 'page_seo';
            break;
        case 'system_settings':
            $activeTab = 'system';
            break;
        case 'change_password':
            $activeTab = 'users';
            break;
        case 'theme_settings':
            $activeTab = 'themes';
            break;
        default:
            $activeTab = 'general';
    }
}
// Check for refresh_license action
elseif (isset($_POST['refresh_license'])) {
    $activeTab = 'license';
}

// Validate the tab name to prevent XSS
$validTabs = ['general', 'contact', 'social', 'seo', 'page_seo', 'users', 'system', 'license', 'themes'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'general';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Enhanced Tab Navigation Styles */
        .tab-navigation {
            position: relative;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            overflow-x: auto;
            scrollbar-width: thin;
        }
        
        .tab-navigation::-webkit-scrollbar {
            height: 3px;
        }
        
        .tab-navigation::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        .tab-navigation::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }
        
        .tab-buttons-container {
            display: flex;
            min-width: max-content;
            position: relative;
            padding: 0 1.5rem;
        }
        
        .tab-button {
            position: relative;
            padding: 1rem 1.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            color: #6b7280;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
            margin-bottom: -1px;
            z-index: 1;
        }
        
        .tab-button:hover {
            color: #374151;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            transform: translateY(-1px);
        }
        
        .tab-button.active {
            color: #3b82f6;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-bottom: 2px solid #3b82f6;
            transform: translateY(0);
            z-index: 2;
        }
        
        .tab-button i {
            font-size: 1rem;
            transition: transform 0.3s ease;
        }
        
        .tab-button:hover i {
            transform: scale(1.1);
        }
        
        .tab-button.active i {
            transform: scale(1.05);
        }
        
        /* Animated underline indicator */
        .tab-indicator {
            position: absolute;
            bottom: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            border-radius: 2px 2px 0 0;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 3;
        }
        
        /* Tab content transitions */
        .tab-content {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease-in-out;
        }
        
        .tab-content.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
            animation: fadeInUp 0.4s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Badge styles for tab counts */
        .tab-badge {
            background: #ef4444;
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            font-weight: 600;
            margin-left: 0.25rem;
            min-width: 1.25rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .tab-button.active .tab-badge {
            background: #3b82f6;
            transform: scale(1.1);
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .tab-button {
                padding: 0.75rem 1rem;
                font-size: 0.8rem;
            }
            
            .tab-button i {
                font-size: 0.875rem;
            }
            
            .tab-navigation {
                border-bottom: none;
                box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1);
            }
        }
        
        /* Enhanced form styles */
        .form-section {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .form-section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-section-content {
            padding: 1.5rem;
        }
        
        /* Success/Error message animations */
        .alert-message {
            animation: slideInDown 0.4s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Loading state for tab switching */
        .tab-loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .tab-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #3b82f6;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Enhanced focus styles for accessibility */
        .tab-button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
        
        /* Smooth scroll for tab navigation */
        .tab-navigation {
            scroll-behavior: smooth;
        }
        
        /* Page SEO styles */
        .page-seo-item {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        
        .page-seo-header {
            background-color: #f9fafb;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .page-seo-header:hover {
            background-color: #f3f4f6;
        }
        
        .page-seo-content {
            padding: 1rem;
            display: none;
            background: white;
        }
        
        .page-seo-content.active {
            display: block;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .page-toggle-icon {
            transition: transform 0.3s ease;
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 4px;
            background-color: #e5e7eb;
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
            width: 0%;
        }
        
        .strength-weak { background-color: #ef4444; }
        .strength-medium { background-color: #f59e0b; }
        .strength-strong { background-color: #10b981; }
        
        /* Enhanced button styles */
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Enhanced input focus styles */
        .form-input {
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            transform: translateY(-1px);
        }
        /* Favicon upload styles */
.favicon-upload-container {
    position: relative;
    display: inline-block;
    width: 100%;
}

.favicon-preview {
    width: 32px;
    height: 32px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    display: inline-block;
    margin-right: 10px;
    vertical-align: middle;
    background: #f9fafb;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath fill='%23cbd5e1' d='M8 0C3.6 0 0 3.6 0 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8zm0 14c-3.3 0-6-2.7-6-6s2.7-6 6-6 6 2.7 6 6-2.7 6-6 6z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 16px 16px;
}

.favicon-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 3px;
}

.favicon-upload-label {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    transition: all 0.2s;
    font-size: 14px;
}

.favicon-upload-label:hover {
    background: #f9fafb;
    border-color: #3b82f6;
}

.favicon-upload-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}

.favicon-remove-btn {
    margin-left: 8px;
    padding: 4px 8px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: background 0.2s;
}

.favicon-remove-btn:hover {
    background: #dc2626;
}

.favicon-info {
    margin-top: 8px;
    padding: 8px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 4px;
    font-size: 12px;
    color: #0369a1;
}

/* OG Image upload styles */
.og-image-upload-container {
    position: relative;
    display: block;
    width: 100%;
    margin-top: 0.5rem;
}

.og-image-preview {
    width: 200px;
    height: 105px; /* 1.9:1 aspect ratio for OG images */
    border: 2px dashed #e5e7eb;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    background: #f9fafb;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.og-image-preview:hover {
    border-color: #3b82f6;
    background: #f0f9ff;
}

.og-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}

.og-image-preview-placeholder {
    text-align: center;
    color: #6b7280;
    font-size: 14px;
}

.og-image-upload-label {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    padding: 8px 16px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    transition: all 0.2s;
    font-size: 14px;
    margin-right: 10px;
}

.og-image-upload-label:hover {
    background: #f9fafb;
    border-color: #3b82f6;
}

.og-image-upload-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}

.og-image-remove-btn {
    padding: 8px 16px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;
    display: inline-flex;
    align-items: center;
}

.og-image-remove-btn:hover {
    background: #dc2626;
}

.og-image-info {
    margin-top: 8px;
    padding: 12px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 6px;
    font-size: 12px;
    color: #0369a1;
}

.og-image-dimensions {
    margin-top: 4px;
    font-size: 11px;
    color: #6b7280;
}
/* Site Logo upload styles */
.logo-upload-container {
    position: relative;
    display: inline-block;
    width: 100%;
}

.logo-preview {
    width: 200px;
    height: 100px;
    border: 2px dashed #e5e7eb;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32'%3E%3Cpath fill='%23cbd5e1' d='M16 4C9.4 4 4 9.4 4 16s5.4 12 12 12 12-5.4 12-12S22.6 4 16 4zm0 20c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8z'/%3E%3Cpath fill='%23cbd5e1' d='M16 10c-3.3 0-6 2.7-6 6s2.7 6 6 6 6-2.7 6-6-2.7-6-6-6zm0 8c-1.1 0-2-0.9-2-2s0.9-2 2-2 2 0.9 2 2-0.9 2-2 2z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 32px 32px;
    transition: all 0.3s ease;
    overflow: hidden;
    flex-shrink: 0;
}

.logo-preview:hover {
    border-color: #3b82f6;
    background-color: #f0f9ff;
}

.logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 6px;
}

.logo-upload-label {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    padding: 10px 16px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 8px;
}

.logo-upload-label:hover {
    background: #f9fafb;
    border-color: #3b82f6;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.logo-upload-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}

.logo-remove-btn {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    margin-left: 8px;
}

.logo-remove-btn:hover {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
}

.logo-info {
    margin-top: 12px;
    padding: 12px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 6px;
    font-size: 12px;
    color: #0369a1;
    line-height: 1.4;
}

/* Logo specific states */
.logo-preview.has-image {
    background-image: none;
    border-style: solid;
    border-color: #10b981;
    background-color: #f0fdf4;
}

.logo-preview.drag-over {
    border-color: #3b82f6;
    background-color: #dbeafe;
    transform: scale(1.02);
}

/* Responsive adjustments for logo */
@media (max-width: 768px) {
    .logo-preview {
        width: 150px;
        height: 75px;
    }
    
    .logo-upload-label {
        padding: 8px 12px;
        font-size: 13px;
    }
    
    .logo-remove-btn {
        padding: 6px 10px;
        font-size: 13px;
        margin-left: 4px;
        margin-top: 4px;
    }
}

/* Logo loading animation */
.logo-loading {
    position: relative;
    opacity: 0.7;
}

.logo-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #3b82f6;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

/* Theme Upload Styles */
.theme-upload-area {
    transition: all 0.3s ease;
}

.theme-upload-area:hover {
    background-color: #f8fafc;
    transform: translateY(-2px);
}

.theme-upload-area.drag-over {
    border-color: #3b82f6;
    background-color: #dbeafe;
    transform: scale(1.02);
}

.theme-upload-content {
    pointer-events: none;
}

#theme-file-preview {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Progress bar animation */
#progress-bar {
    transition: width 0.5s ease;
}

/* Code block styling */
pre code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.85rem;
    line-height: 1.5;
}
    </style>
</head>
<body class="bg-gray-50">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-gray-600 hover:text-gray-900 mr-4">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></span>
                    <a href="<?= BASE_URL ?>/" target="_blank" class="text-blue-600 hover:text-blue-800 transition-colors">
                        <i class="fas fa-external-link-alt mr-1"></i>View Site
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- Success/Error Messages -->
        <?php if (isset($success) && $success): ?>
            <div class="alert-message bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error) && $error): ?>
            <div class="alert-message bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Enhanced Settings Tabs -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Enhanced Tab Navigation -->
            <div class="tab-navigation">
                <div class="tab-buttons-container">
                    <button class="tab-button <?= $activeTab === 'general' ? 'active' : '' ?>" data-tab="general" onclick="switchTab('general')">
                        <i class="fas fa-cog"></i>
                        <span>General</span>
                    </button>
                    <button class="tab-button <?= $activeTab === 'contact' ? 'active' : '' ?>" data-tab="contact" onclick="switchTab('contact')">
                        <i class="fas fa-envelope"></i>
                        <span>Contact</span>
                    </button>
                    <button class="tab-button <?= $activeTab === 'social' ? 'active' : '' ?>" data-tab="social" onclick="switchTab('social')">
                        <i class="fas fa-share-alt"></i>
                        <span>Social Media</span>
                    </button>
                    <button class="tab-button <?= $activeTab === 'seo' ? 'active' : '' ?>" data-tab="seo" onclick="switchTab('seo')">
                        <i class="fas fa-search"></i>
                        <span>SEO</span>
                    </button>
                    <button class="tab-button <?= $activeTab === 'page_seo' ? 'active' : '' ?>" data-tab="page_seo" onclick="switchTab('page_seo')">
                        <i class="fas fa-file-alt"></i>
                        <span>Page SEO</span>
                        <span class="tab-badge"><?= count($pages) ?></span>
                    </button>
                    <button class="tab-button <?= $activeTab === 'users' ? 'active' : '' ?>" data-tab="users" onclick="switchTab('users')">
                        <i class="fas fa-user"></i>
                        <span>Users</span>
                    </button>
                    <button class="tab-button <?= $activeTab === 'system' ? 'active' : '' ?>" data-tab="system" onclick="switchTab('system')">
                        <i class="fas fa-server"></i>
                        <span>System</span>
                    </button>
                    <button class="tab-button <?= $activeTab === 'license' ? 'active' : '' ?>" data-tab="license" onclick="switchTab('license')">
                        <i class="fas fa-certificate"></i>
                        <span>License</span>
                    </button>
                    <button class="tab-button <?= $activeTab === 'themes' ? 'active' : '' ?>" data-tab="themes" onclick="switchTab('themes')">
                        <i class="fas fa-palette"></i>
                        <span>Themes</span>
                    </button>
                    <div class="tab-indicator"></div>
                </div>
            </div>

            <!-- General Settings Tab -->
<div id="general-tab" class="tab-content <?= $activeTab === 'general' ? 'active' : '' ?>">
    <div class="p-6">
        <div class="form-section">
            <div class="form-section-header">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-cog text-blue-600 mr-2"></i>
                    General Settings
                </h2>
            </div>
            <div class="form-section-content">
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="general_settings">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Site Name</label>
                            <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'Your Website') ?>" 
                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Admin Email</label>
                            <input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" 
                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Site Tagline</label>
                            <input type="text" name="site_tagline" value="<?= htmlspecialchars($settings['site_tagline'] ?? '') ?>" 
                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Site Description</label>
                            <textarea name="site_description" rows="3" 
                                      class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Favicon Upload Section -->
<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fas fa-star text-yellow-500 mr-1"></i>Site Favicon
    </label>
    <div class="favicon-upload-container">
        <div class="flex items-center mb-3">
            <div class="favicon-preview" id="favicon-preview">
                <?php if (!empty($settings['favicon_url']) && file_exists('../' . $settings['favicon_url'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($settings['favicon_url']) ?>?v=<?= time() ?>" alt="Current favicon">
                <?php endif; ?>
            </div>
            <div class="flex-1">
                <label for="favicon-upload" class="favicon-upload-label">
                    <i class="fas fa-upload mr-2"></i>
                    Choose Favicon
                </label>
                <input type="file" id="favicon-upload" name="favicon" accept=".ico,.png,.jpg,.jpeg,.gif" class="favicon-upload-input" onchange="previewFavicon(this)">
                <?php if (!empty($settings['favicon_url']) && ($settings['favicon_enabled'] ?? '0') === '1'): ?>
                    <button type="button" class="favicon-remove-btn" onclick="removeFavicon()">
                        <i class="fas fa-trash-alt mr-1"></i>Remove
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Favicon Status Indicator -->
        <div class="mb-3">
            <?php if (($settings['favicon_enabled'] ?? '0') === '1' && !empty($settings['favicon_url'])): ?>
                <div class="flex items-center text-green-600 text-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Favicon is active and displayed on your site</span>
                </div>
            <?php else: ?>
                <div class="flex items-center text-gray-500 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>No favicon uploaded. Upload one to display on your site.</span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="favicon-info">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>Favicon Guidelines:</strong> Upload an ICO, PNG, JPG, or GIF file. Recommended size: 32x32px or 16x16px. Maximum file size: 1MB.
        </div>
    </div>
</div>    
    <!-- Hidden input for favicon removal -->
    <input type="hidden" id="remove-favicon-input" name="remove_favicon" value="0">

<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-2">
        <i class="fas fa-image text-blue-500 mr-1"></i>Site Logo
    </label>
    <div class="logo-upload-container">
        <div class="flex items-start mb-3">
            <div class="logo-preview" id="logo-preview">
                <?php if (!empty($settings['site_logo_url']) && file_exists('../' . $settings['site_logo_url'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($settings['site_logo_url']) ?>?v=<?= time() ?>" alt="Current site logo">
                <?php endif; ?>
            </div>
            <div class="flex-1 ml-4">
                <label for="logo-upload" class="logo-upload-label">
                    <i class="fas fa-upload mr-2"></i>
                    Choose Logo
                </label>
                <input type="file" id="logo-upload" name="site_logo" accept=".png,.jpg,.jpeg,.gif,.webp,.svg" class="logo-upload-input" onchange="previewLogo(this)">
                <?php if (!empty($settings['site_logo_url']) && ($settings['site_logo_enabled'] ?? '0') === '1'): ?>
                    <button type="button" class="logo-remove-btn" onclick="removeLogo()">
                        <i class="fas fa-trash-alt mr-1"></i>Remove
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Logo Status Indicator -->
        <div class="mb-3">
            <?php if (($settings['site_logo_enabled'] ?? '0') === '1' && !empty($settings['site_logo_url'])): ?>
                <div class="flex items-center text-green-600 text-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span>Site logo is active and displayed on your site</span>
                </div>
            <?php else: ?>
                <div class="flex items-center text-gray-500 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span>No logo uploaded. Upload one to display on your site.</span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="logo-info">
            <i class="fas fa-info-circle mr-1"></i>
            <strong>Logo Guidelines:</strong> Upload a PNG, JPG, GIF, WebP, or SVG file. Recommended size: 200-400px wide. Maximum file size: 2MB. For best results, use a transparent PNG for light/dark theme compatibility.
        </div>
    </div>
</div>

<!-- Hidden input for logo removal -->
<input type="hidden" id="remove-logo-input" name="remove_logo" value="0">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Timezone</label>
                            <select name="timezone" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="UTC" <?= ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                <option value="America/New_York" <?= ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time</option>
                                <option value="America/Chicago" <?= ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time</option>
                                <option value="America/Denver" <?= ($settings['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time</option>
                                <option value="America/Los_Angeles" <?= ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time</option>
                                <option value="Europe/London" <?= ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                <option value="Asia/Colombo" <?= ($settings['timezone'] ?? '') === 'Asia/Colombo' ? 'selected' : '' ?>>Colombo</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="maintenance_mode" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> 
                                       class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-700">Maintenance Mode</span>
                            </label>
                            <div class="ml-2 relative group">
                                <i class="fas fa-info-circle text-gray-400 cursor-help"></i>
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap">
                                    Enable to show maintenance page to visitors
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-between items-center">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Save General Settings
                        </button>
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Changes are saved immediately
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

            <!-- Contact Settings Tab -->
            <div id="contact-tab" class="tab-content <?= $activeTab === 'contact' ? 'active' : '' ?>">
                <div class="p-6">
                    <div class="form-section">
                        <div class="form-section-header">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-envelope text-blue-600 mr-2"></i>
                                Contact Information
                            </h2>
                        </div>
                        <div class="form-section-content">
                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                <input type="hidden" name="action" value="contact_settings">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                                        <input type="email" name="contact_email" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" 
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Phone</label>
                                        <input type="tel" name="contact_phone" value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>" 
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Business Address</label>
                                        <textarea name="contact_address" rows="3" 
                                                  class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($settings['contact_address'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Business Hours</label>
                                        <textarea name="business_hours" rows="3" placeholder="Mon-Fri: 9:00 AM - 5:00 PM&#10;Sat: 10:00 AM - 3:00 PM&#10;Sun: Closed" 
                                                  class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($settings['business_hours'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-save"></i>
                                        Save Contact Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Media Settings Tab -->
            <div id="social-tab" class="tab-content <?= $activeTab === 'social' ? 'active' : '' ?>">
                <div class="p-6">
                    <div class="form-section">
                        <div class="form-section-header">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-share-alt text-blue-600 mr-2"></i>
                                Social Media Links
                            </h2>
                        </div>
                        <div class="form-section-content">
                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                <input type="hidden" name="action" value="social_settings">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fab fa-facebook text-blue-600 mr-2"></i>Facebook URL
                                        </label>
                                        <input type="url" name="facebook_url" value="<?= htmlspecialchars($settings['facebook_url'] ?? '') ?>" 
                                               placeholder="https://facebook.com/yourpage"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fab fa-twitter text-blue-400 mr-2"></i>Twitter URL
                                        </label>
                                        <input type="url" name="twitter_url" value="<?= htmlspecialchars($settings['twitter_url'] ?? '') ?>" 
                                               placeholder="https://twitter.com/yourhandle"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fab fa-linkedin text-blue-700 mr-2"></i>LinkedIn URL
                                        </label>
                                        <input type="url" name="linkedin_url" value="<?= htmlspecialchars($settings['linkedin_url'] ?? '') ?>" 
                                               placeholder="https://linkedin.com/company/yourcompany"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fab fa-instagram text-pink-600 mr-2"></i>Instagram URL
                                        </label>
                                        <input type="url" name="instagram_url" value="<?= htmlspecialchars($settings['instagram_url'] ?? '') ?>" 
                                               placeholder="https://instagram.com/youraccount"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fab fa-youtube text-red-600 mr-2"></i>YouTube URL
                                        </label>
                                        <input type="url" name="youtube_url" value="<?= htmlspecialchars($settings['youtube_url'] ?? '') ?>" 
                                               placeholder="https://youtube.com/yourchannel"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-save"></i>
                                        Save Social Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEO Settings Tab -->
            <div id="seo-tab" class="tab-content <?= $activeTab === 'seo' ? 'active' : '' ?>">
                <div class="p-6">
                    <div class="form-section">
                        <div class="form-section-header">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-search text-blue-600 mr-2"></i>
                                Default SEO Settings
                            </h2>
                        </div>
                        <div class="form-section-content">
                            <p class="text-sm text-gray-600 mb-6">These are the default SEO settings used when pages don't have specific SEO configured.</p>
                            
                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                                <input type="hidden" name="action" value="seo_settings">
                                
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Default Meta Title</label>
                                        <input type="text" name="default_meta_title" value="<?= htmlspecialchars($settings['default_meta_title'] ?? '') ?>" 
                                               placeholder="Your Website - Default Title"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <p class="text-xs text-gray-500 mt-1">Used when pages don't have a specific meta title</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Default Meta Description</label>
                                        <textarea name="default_meta_description" rows="3" 
                                                  placeholder="A brief description of your website..."
                                                  class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($settings['default_meta_description'] ?? '') ?></textarea>
                                        <p class="text-xs text-gray-500 mt-1">Used when pages don't have a specific meta description</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Default Meta Keywords</label>
                                        <input type="text" name="default_meta_keywords" value="<?= htmlspecialchars($settings['default_meta_keywords'] ?? '') ?>" 
                                               placeholder="keyword1, keyword2, keyword3"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <p class="text-xs text-gray-500 mt-1">Comma-separated keywords</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Google Analytics ID</label>
                                        <input type="text" name="google_analytics_id" value="<?= htmlspecialchars($settings['google_analytics_id'] ?? '') ?>" 
                                               placeholder="GA4-XXXXXXXXX-X"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Google Search Console Verification</label>
                                        <input type="text" name="google_search_console" value="<?= htmlspecialchars($settings['google_search_console'] ?? '') ?>" 
                                               placeholder="meta verification code"
                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                
                                <div class="mt-6">
                                    <button type="submit" class="btn-primary">
                                        <i class="fas fa-save"></i>
                                        Save SEO Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page SEO Settings Tab -->
            <div id="page_seo-tab" class="tab-content <?= $activeTab === 'page_seo' ? 'active' : '' ?>">
                <div class="p-6">
                    <div class="form-section">
                        <div class="form-section-header">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                                Page-Specific SEO Settings
                            </h2>
                        </div>
                        <div class="form-section-content">
                            <p class="text-sm text-gray-600 mb-6">Configure individual SEO settings for each page. Click on a page to expand its SEO options.</p>
                            
                            <?php if (empty($pages)): ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-file-alt text-4xl mb-4"></i>
                                    <p>No pages found. Create some pages first to configure their SEO settings.</p>
                                </div>
                            <?php else: ?>
                                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded">
                                    <p class="text-green-800">✅ <strong>Found <?= count($pages) ?> pages</strong> ready for SEO configuration</p>
                                </div>
                                
                                <?php foreach ($pages as $page): ?>
                                    <?php $pageSEO = getPageSEO($page['id']); ?>
                                    <div class="page-seo-item">
                                        <div class="page-seo-header" onclick="togglePageSEO(<?= $page['id'] ?>)">
                                            <div class="flex items-center">
                                                <i class="fas fa-file-alt text-blue-600 mr-3"></i>
                                                <div>
                                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($page['title']) ?></h3>
                                                    <p class="text-sm text-gray-500">
                                                        Slug: <?= htmlspecialchars($page['slug']) ?> | 
                                                        ID: <?= $page['id'] ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-center">
                                                <?php if ($pageSEO): ?>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full mr-2">
                                                        <i class="fas fa-check mr-1"></i>SEO Configured
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full mr-2">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>Default SEO
                                                    </span>
                                                <?php endif; ?>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                        </div>
                                        
                                        <div class="page-seo-content" id="page-seo-<?= $page['id'] ?>">
                                            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="page_seo_settings">
                                                <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                                                
                                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                                    <!-- Basic Meta Tags -->
                                                    <div class="lg:col-span-2">
                                                        <h4 class="text-md font-semibold text-gray-800 mb-4 border-b pb-2">
                                                            <i class="fas fa-tags mr-2"></i>Basic Meta Tags
                                                        </h4>
                                                    </div>
                                                    
                                                    <div class="lg:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Meta Title</label>
                                                        <input type="text" name="meta_title" 
                                                               value="<?= htmlspecialchars($pageSEO['meta_title'] ?? '') ?>" 
                                                               placeholder="Page specific title (leave empty for default)"
                                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                               maxlength="60">
                                                        <p class="text-xs text-gray-500 mt-1">Recommended: 50-60 characters</p>
                                                    </div>
                                                    
                                                    <div class="lg:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Meta Description</label>
                                                        <textarea name="meta_description" rows="3" 
                                                                  placeholder="Page specific description (leave empty for default)"
                                                                  class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                                  maxlength="160"><?= htmlspecialchars($pageSEO['meta_description'] ?? '') ?></textarea>
                                                        <p class="text-xs text-gray-500 mt-1">Recommended: 150-160 characters</p>
                                                    </div>
                                                    
                                                    <!-- Open Graph Tags -->
                                                    <div class="lg:col-span-2 mt-6">
                                                        <h4 class="text-md font-semibold text-gray-800 mb-4 border-b pb-2">
                                                            <i class="fab fa-facebook mr-2"></i>Open Graph (Social Media)
                                                        </h4>
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">OG Title</label>
                                                        <input type="text" name="og_title" 
                                                               value="<?= htmlspecialchars($pageSEO['og_title'] ?? '') ?>" 
                                                               placeholder="Social media title"
                                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                                            <i class="fas fa-image mr-1"></i>OG Image
                                                        </label>
                                                        
                                                        <!-- Current OG Image Info -->
                                                        <?php if (!empty($pageSEO['og_image'])): ?>
                                                            <div class="file-info">
                                                                <label class="block text-xs font-medium text-gray-600 mb-2">Current OG Image:</label>
                                                                <div class="file-path"><?= htmlspecialchars($pageSEO['og_image']) ?></div>
                                                                
                                                                <!-- File Status Check -->
                                                                <?php if (strpos($pageSEO['og_image'], 'uploads/') === 0): ?>
                                                                    <?php $fileExists = file_exists('../' . $pageSEO['og_image']); ?>
                                                                    <div class="file-status <?= $fileExists ? 'exists' : 'missing' ?>">
                                                                        <i class="fas <?= $fileExists ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                                                                        <span><?= $fileExists ? 'File exists on server' : 'File not found on server' ?></span>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="file-status external">
                                                                        <i class="fas fa-external-link-alt"></i>
                                                                        <span>External URL</span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Image Preview -->
                                                        <div class="og-image-preview" id="og-image-preview-<?= $page['id'] ?>">
                                                            <?php if (!empty($pageSEO['og_image'])): ?>
                                                                <?php if (strpos($pageSEO['og_image'], 'http') === 0): ?>
                                                                    <img src="<?= htmlspecialchars($pageSEO['og_image']) ?>" alt="Current OG image" onerror="this.parentElement.innerHTML='<div class=\'og-image-preview-placeholder\'><i class=\'fas fa-exclamation-triangle text-red-500 text-2xl mb-2\'></i><div>Image not found</div></div>'">
                                                                <?php else: ?>
                                                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($pageSEO['og_image']) ?>?v=<?= time() ?>" alt="Current OG image" onerror="this.parentElement.innerHTML='<div class=\'og-image-preview-placeholder\'><i class=\'fas fa-exclamation-triangle text-red-500 text-2xl mb-2\'></i><div>Image not found</div></div>'">
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <div class="og-image-preview-placeholder">
                                                                    <i class="fas fa-image text-2xl mb-2"></i>
                                                                    <div>1200x630px recommended</div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Upload Controls -->
                                                        <div class="flex items-center mb-3">
                                                            <label for="og-image-upload-<?= $page['id'] ?>" class="inline-flex items-center cursor-pointer px-4 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 mr-3">
                                                                <i class="fas fa-upload mr-2"></i>
                                                                Choose OG Image
                                                            </label>
                                                            <input type="file" 
                                                                   id="og-image-upload-<?= $page['id'] ?>" 
                                                                   name="og_image_file" 
                                                                   accept=".jpg,.jpeg,.png,.gif,.webp" 
                                                                   class="hidden" 
                                                                   onchange="previewOGImage(this, <?= $page['id'] ?>)">
                                                            
                                                            <?php if (!empty($pageSEO['og_image'])): ?>
                                                                <button type="button" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm" onclick="removeOGImage(<?= $page['id'] ?>)">
                                                                    <i class="fas fa-trash-alt mr-1"></i>Remove
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Manual URL Input -->
                                                        <div class="mb-3">
                                                            <label class="block text-xs font-medium text-gray-600 mb-1">Or enter image URL manually:</label>
                                                            <input type="text" name="og_image" 
                                                                   value="<?= htmlspecialchars($pageSEO['og_image'] ?? '') ?>" 
                                                                   placeholder="https://example.com/og-image.jpg"
                                                                   class="form-input w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                        </div>
                                                        
                                                        <!-- Hidden input for removal -->
                                                        <input type="hidden" id="remove-og-image-<?= $page['id'] ?>" name="remove_og_image" value="0">
                                                        
                                                        <div class="text-xs text-blue-600 bg-blue-50 p-3 rounded border border-blue-200">
                                                            <i class="fas fa-info-circle mr-1"></i>
                                                            <strong>OG Image Guidelines:</strong> Upload JPG, PNG, GIF, or WebP. Recommended size: 1200x630px (1.91:1 ratio). Max file size: 5MB.
                                                            <div class="mt-1">
                                                                This image will be displayed when your page is shared on social media platforms.
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="lg:col-span-2">
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">OG Description</label>
                                                        <textarea name="og_description" rows="3" 
                                                                  placeholder="Social media description"
                                                                  class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($pageSEO['og_description'] ?? '') ?></textarea>
                                                    </div>
                                                    
                                                    <!-- Technical SEO -->
                                                    <div class="lg:col-span-2 mt-6">
                                                        <h4 class="text-md font-semibold text-gray-800 mb-4 border-b pb-2">
                                                            <i class="fas fa-cogs mr-2"></i>Technical SEO
                                                        </h4>
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Canonical URL</label>
                                                        <input type="url" name="canonical_url" 
                                                               value="<?= htmlspecialchars($pageSEO['canonical_url'] ?? '') ?>" 
                                                               placeholder="https://example.com/page-url"
                                                               class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                        <p class="text-xs text-gray-500 mt-1">Leave empty for auto-generated</p>
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-2">Robots Meta</label>
                                                        <select name="robots" class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                            <option value="index,follow" <?= ($pageSEO['robots'] ?? 'index,follow') === 'index,follow' ? 'selected' : '' ?>>Index, Follow</option>
                                                            <option value="index,nofollow" <?= ($pageSEO['robots'] ?? '') === 'index,nofollow' ? 'selected' : '' ?>>Index, No Follow</option>
                                                            <option value="noindex,follow" <?= ($pageSEO['robots'] ?? '') === 'noindex,follow' ? 'selected' : '' ?>>No Index, Follow</option>
                                                            <option value="noindex,nofollow" <?= ($pageSEO['robots'] ?? '') === 'noindex,nofollow' ? 'selected' : '' ?>>No Index, No Follow</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-6 flex justify-between items-center">
                                                    <button type="submit" class="btn-primary">
                                                        <i class="fas fa-save"></i>
                                                        Save Page SEO
                                                    </button>
                                                    
                                                    <?php if ($pageSEO): ?>
                                                        <div class="text-xs text-gray-500">
                                                            Last updated: <?= date('M j, Y \a\t g:i A', strtotime($pageSEO['updated_at'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
            <div id="users-tab" class="tab-content <?= $activeTab === 'users' ? 'active' : '' ?>">
                <div class="p-6">
                    <div class="form-section">
                        <div class="form-section-header">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-user text-blue-600 mr-2"></i>
                                User Management
                            </h2>
                        </div>
                        <div class="form-section-content">
                            <!-- Current User Info -->
                            <?php if ($currentAdmin): ?>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-user-circle text-4xl text-blue-600"></i>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-lg font-medium text-blue-900">Current Admin User</h3>
                                            <div class="mt-2 text-sm text-blue-700">
                                                <p><strong>Username:</strong> <?= htmlspecialchars($currentAdmin['username']) ?></p>
                                                <p><strong>Email:</strong> <?= htmlspecialchars($currentAdmin['email']) ?></p>
                                                <p><strong>Account Created:</strong> <?= date('M j, Y \a\t g:i A', strtotime($currentAdmin['created_at'])) ?></p>
                                                <?php if ($currentAdmin['last_login']): ?>
                                                    <p><strong>Last Login:</strong> <?= date('M j, Y \a\t g:i A', strtotime($currentAdmin['last_login'])) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Change Password Form -->
                            <div class="bg-white border border-gray-200 rounded-lg p-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">
                                    <i class="fas fa-key text-blue-600 mr-2"></i>Change Password
                                </h3>
                                <p class="text-sm text-gray-600 mb-6">Update your account password for security.</p>
                                
                                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="password-form">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="space-y-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                            <input type="password" name="current_password" required 
                                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                   placeholder="Enter your current password">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                            <input type="password" name="new_password" required id="new-password"
                                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                   placeholder="Enter your new password">
                                            <div class="password-strength mt-2">
                                                <div class="password-strength-bar" id="password-strength-bar"></div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1" id="password-feedback">Password must be at least 6 characters long</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                            <input type="password" name="confirm_password" required id="confirm-password"
                                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                   placeholder="Confirm your new password">
                                            <p class="text-xs text-gray-500 mt-1" id="confirm-feedback"></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-8 flex items-center justify-between">
                                        <button type="submit" id="submit-password" class="btn-primary">
                                            <i class="fas fa-key"></i>
                                            Change Password
                                        </button>
                                        
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-shield-alt text-green-500 mr-1"></i>
                                            Password is encrypted and secure
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Security Tips -->
                            <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                                <h4 class="text-md font-medium text-yellow-800 mb-3">
                                    <i class="fas fa-lightbulb mr-2"></i>Security Tips
                                </h4>
                                <ul class="text-sm text-yellow-700 space-y-2">
                                    <li>• Use a strong password with at least 8 characters</li>
                                    <li>• Include uppercase and lowercase letters, numbers, and symbols</li>
                                    <li>• Don't use the same password for multiple accounts</li>
                                    <li>• Change your password regularly (every 3-6 months)</li>
                                    <li>• Never share your password with anyone</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Settings Tab -->
            <div id="system-tab" class="tab-content <?= $activeTab === 'system' ? 'active' : '' ?>">
                <div class="p-6">
                    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <input type="hidden" name="action" value="system_settings">
                        
                        <div class="space-y-8">
                            <!-- File Upload Settings -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <h3 class="text-md font-semibold text-gray-800 flex items-center">
                                        <i class="fas fa-upload text-blue-600 mr-2"></i>File Upload Settings
                                    </h3>
                                </div>
                                <div class="form-section-content">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Upload Size (MB)</label>
                                            <input type="number" name="max_upload_size" value="<?= htmlspecialchars($settings['max_upload_size'] ?? '5') ?>" 
                                                   min="1" max="100"
                                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Items Per Page</label>
                                            <input type="number" name="items_per_page" value="<?= htmlspecialchars($settings['items_per_page'] ?? '10') ?>" 
                                                   min="5" max="100"
                                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Allowed File Types</label>
                                            <input type="text" name="allowed_file_types" value="<?= htmlspecialchars($settings['allowed_file_types'] ?? 'jpg,jpeg,png,gif,webp,svg') ?>" 
                                                   placeholder="jpg,jpeg,png,gif,webp,svg"
                                                   class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <p class="text-xs text-gray-500 mt-1">Comma-separated file extensions</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Feature Settings -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <h3 class="text-md font-semibold text-gray-800 flex items-center">
                                        <i class="fas fa-toggle-on text-blue-600 mr-2"></i>Feature Settings
                                    </h3>
                                </div>
                                <div class="form-section-content">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="flex items-center cursor-pointer">
                                                <input type="checkbox" name="enable_comments" <?= ($settings['enable_comments'] ?? '0') === '1' ? 'checked' : '' ?> 
                                                       class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span class="text-sm font-medium text-gray-700">Enable Comments</span>
                                            </label>
                                            <p class="text-xs text-gray-500 mt-1 ml-6">Allow users to comment on pages</p>
                                        </div>
                                        
                                        <div>
                                            <label class="flex items-center cursor-pointer">
                                                <input type="checkbox" name="cache_enabled" <?= ($settings['cache_enabled'] ?? '0') === '1' ? 'checked' : '' ?> 
                                                       class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span class="text-sm font-medium text-gray-700">Enable Caching</span>
                                            </label>
                                            <p class="text-xs text-gray-500 mt-1 ml-6">Improve site performance with caching</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Security Settings -->
                            <div class="form-section">
                                <div class="form-section-header">
                                    <h3 class="text-md font-semibold text-gray-800 flex items-center">
                                        <i class="fas fa-shield-alt text-blue-600 mr-2"></i>Security Settings
                                    </h3>
                                </div>
                                <div class="form-section-content">
                                    <!-- reCAPTCHA Enable/Disable -->
                                    <div class="mb-6">
                                        <label class="flex items-center cursor-pointer">
                                            <input type="checkbox" name="recaptcha_enabled" id="recaptcha-enabled" 
                                                   <?= ($settings['recaptcha_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                                                   onchange="toggleRecaptchaFields()"
                                                   class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="text-sm font-medium text-gray-700">Enable reCAPTCHA v2 for Login</span>
                                        </label>
                                        <p class="text-xs text-gray-500 mt-1 ml-6">Add Google reCAPTCHA protection to admin login</p>
                                    </div>
                                    
                                    <!-- reCAPTCHA Configuration Fields -->
                                    <div id="recaptcha-fields" class="<?= ($settings['recaptcha_enabled'] ?? '0') === '1' ? '' : 'hidden' ?> space-y-4 ml-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <div class="grid grid-cols-1 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-key mr-1"></i>reCAPTCHA Site Key
                                                </label>
                                                <input type="text" name="recaptcha_site_key" 
                                                       value="<?= htmlspecialchars($settings['recaptcha_site_key'] ?? '') ?>" 
                                                       placeholder="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"
                                                       class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <p class="text-xs text-gray-500 mt-1">Get this from your Google reCAPTCHA console</p>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-lock mr-1"></i>reCAPTCHA Secret Key
                                                </label>
                                                <input type="password" name="recaptcha_secret_key" 
                                                       value="<?= htmlspecialchars($settings['recaptcha_secret_key'] ?? '') ?>" 
                                                       placeholder="6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe"
                                                       class="form-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <p class="text-xs text-gray-500 mt-1">Keep this secret and secure</p>
                                            </div>
                                        </div>
                                        
                                        <!-- reCAPTCHA Setup Instructions -->
                                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                                            <h4 class="text-sm font-medium text-yellow-800 mb-2">
                                                <i class="fas fa-info-circle mr-1"></i>Setup Instructions
                                            </h4>
                                            <ol class="text-xs text-yellow-700 space-y-1 ml-4">
                                                <li>1. Go to <a href="https://www.google.com/recaptcha/admin" target="_blank" class="text-blue-600 underline">Google reCAPTCHA Console</a></li>
                                                <li>2. Register a new site with reCAPTCHA v2 ("I'm not a robot" Checkbox)</li>
                                                <li>3. Add your domain to the list of authorized domains</li>
                                                <li>4. Copy the Site Key and Secret Key here</li>
                                                <li>5. Save settings and test the login page</li>
                                            </ol>
                                        </div>
                                        
                                        <!-- Test reCAPTCHA Status -->
                                        <?php if (($settings['recaptcha_enabled'] ?? '0') === '1' && !empty($settings['recaptcha_site_key']) && !empty($settings['recaptcha_secret_key'])): ?>
                                            <div class="mt-4 flex items-center p-3 bg-green-50 border border-green-200 rounded">
                                                <i class="fas fa-check-circle text-green-600 mr-2"></i>
                                                <span class="text-sm text-green-700">reCAPTCHA is configured and enabled</span>
                                            </div>
                                        <?php elseif (($settings['recaptcha_enabled'] ?? '0') === '1'): ?>
                                            <div class="mt-4 flex items-center p-3 bg-red-50 border border-red-200 rounded">
                                                <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                                                <span class="text-sm text-red-700">reCAPTCHA is enabled but keys are missing</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-8 flex justify-between items-center">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i>
                                Save System Settings
                            </button>
                            
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                Changes take effect immediately
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <!-- License Settings Tab -->
<!-- License Settings Tab -->
<div id="license-tab" class="tab-content <?= $activeTab === 'license' ? 'active' : '' ?>">
                <div class="p-6">
                            <!-- License Key Management Section -->
        <div class="form-section mb-6">
            <div class="form-section-header">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-key text-blue-600 mr-2"></i>
                    License Key Management
                </h2>
            </div>
            <div class="form-section-content">
                <?php
                // Read license key from config file
                $currentLicenseKey = getLicenseKeyFromConfig();
                $hasLicenseKey = !empty($currentLicenseKey);
                ?>
                
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-md font-medium text-gray-900">
                            <i class="fas fa-certificate mr-2"></i>Current License Key
                        </h3>
                        

                    </div>
                    
                    <!-- License Key Input Form -->
                    <form id="license-key-form" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                        <input type="hidden" name="action" value="update_license_key">
                        <input type="hidden" id="license-edit-mode" name="edit_mode" value="0">
                        
                        <div class="space-y-4">
                            <!-- License Key Input -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-key mr-1"></i>License Key
                                </label>
                                <div class="flex items-center space-x-3">
                                    <div class="flex-1 relative">
                                        <input 
                                            type="text" 
                                            id="license-key-input" 
                                            name="license_key" 
                                            value="<?= htmlspecialchars($currentLicenseKey) ?>" 
                                            placeholder="Enter your license key"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                            <?= $hasLicenseKey ? 'readonly' : '' ?>
                                        >
                                        
                                        <!-- Success checkmark overlay -->
                                        <?php if ($hasLicenseKey): ?>
                                            <div class="absolute right-3 top-1/2 transform -translate-y-1/2" id="license-checkmark">
                                                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Edit/Save/Cancel Buttons -->
                                    <div class="flex items-center space-x-2">
                                        <?php if ($hasLicenseKey): ?>
                                            <button type="button" id="edit-license-btn" onclick="toggleLicenseEdit()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                                <i class="fas fa-edit mr-1"></i>Edit
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button type="submit" id="save-license-btn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors <?= $hasLicenseKey ? 'hidden' : '' ?>">
                                            <i class="fas fa-save mr-1"></i>Save
                                        </button>
                                        
                                        <button type="button" id="cancel-license-btn" onclick="cancelLicenseEdit()" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors hidden">
                                            <i class="fas fa-times mr-1"></i>Cancel
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- License Key Info -->
                                <div class="mt-2 text-sm text-gray-600">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    License key format: Owned-[alphanumeric string] (e.g., Owned-da4c197a469438f72929)
                                </div>
                            </div>
                            
                            <!-- License Key Validation Status -->
                            <!-- <div id="license-validation-status"></div> -->
                            
                            <!-- Current License Details -->
                        </div>
                    </form>
                </div>
            </div>
        </div>
                    <!-- License Key Files Management -->
                    <div class="form-section mb-6">
                        <div class="form-section-header">
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <i class="fas fa-file-code text-orange-600 mr-2"></i>
                                License Key Files Management
                            </h2>
                        </div>
                        <div class="form-section-content">
                            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                                <h4 class="text-sm font-medium text-orange-800 mb-2">
                                    <i class="fas fa-info-circle mr-1"></i>About License Key Files
                                </h4>
                                <div class="text-sm text-orange-700 space-y-1">
                                    <p>• <code>.localkey</code> - Local license key file for offline validation</p>
                                    <p>• <code>.suspendedkey</code> - File created when license is suspended</p>
                                    <p>• These files may cause licensing issues if corrupted or outdated</p>
                                    <p>• Removing them will force fresh license validation</p>
                                </div>
                            </div>
                            
                            <!-- Scan for Key Files -->
                            <div class="mb-4">
                                <button type="button" onclick="scanKeyFiles()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                    <i class="fas fa-search mr-2"></i>
                                    Scan for Key Files
                                </button>
                            </div>
                            
                            <!-- Key Files Status -->
                            <div id="key-files-status" class="mb-4"></div>
                            
                            <!-- License Refresh Form -->
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 mb-6">
                                <h3 class="text-lg font-semibold text-blue-900 mb-4 flex items-center">
                                    <i class="fas fa-sync-alt text-blue-600 mr-2"></i>
                                    License Refresh
                                </h3>
                                <p class="text-blue-700 mb-4">
                                    This will remove .localkey and .suspendedkey files from the system and force a fresh license check with the server.
                                </p>
                                
                                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" onsubmit="return confirmLicenseRefresh()">
                                    <input type="hidden" name="refresh_license" value="1">
                                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors font-medium">
                                        <i class="fas fa-refresh mr-2"></i>
                                        🔄 Refresh License
                                    </button>
                                </form>
                                
                                <div class="mt-4 text-sm text-blue-600">
                                    <i class="fas fa-lightbulb mr-1"></i>
                                    <strong>Tip:</strong> Use this if you're experiencing license validation issues or after updating your license.
                                </div>
                                
                                <!-- Status indicator for last refresh -->
                                <?php if (isset($success) && strpos($success, 'License cache cleared') !== false): ?>
                                    <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded">
                                        <div class="flex items-center text-green-800">
                                            <i class="fas fa-check-circle mr-2"></i>
                                            <span class="text-sm font-medium">Last refresh completed successfully</span>
                                        </div>
                                        <div class="text-xs text-green-700 mt-1">
                                            <?= htmlspecialchars($success) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            
                            <!-- Advanced Remove Key Files -->
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-red-800 mb-3">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Advanced: Manual Key File Removal
                                </h4>
                                <p class="text-sm text-red-700 mb-4">
                                    Use this only if the refresh button above doesn't work. This will attempt to remove license key files individually.
                                </p>
                                <button type="button" onclick="removeLicenseFiles()" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                                    <i class="fas fa-trash-alt mr-2"></i>
                                    Remove Key Files
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- License Information -->
                    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <h4 class="text-md font-medium text-blue-800 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>License Management Information
                        </h4>
                        <div class="text-sm text-blue-700 space-y-2">
                            <p>• License files are automatically created by your licensing system</p>
                            <p>• .localkey contains cached license validation data</p>
                            <p>• .suspendedkey is created when a license is suspended</p>
                            <p>• Refreshing forces a new check with the license server</p>
                            <p>• After refreshing, reload your site to see the updated license status</p>
                            <p>• Contact support if license issues persist after refreshing</p>
                        </div>
                    </div>
                </div>
            </div>
<div id="themes-tab" class="tab-content <?= $activeTab === 'themes' ? 'active' : '' ?>">
    <div class="p-6">
        <?php 
        $currentTheme = getCurrentTheme();
        $allThemes = getAllThemes();
        ?>
        
        <!-- Current Active Theme -->
        <div class="form-section mb-6">
            <div class="form-section-header">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    Active Theme
                </h2>
            </div>
            <div class="form-section-content">
                <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-green-900">
                                <?= htmlspecialchars($currentTheme['theme_display_name']) ?>
                            </h3>
                            <p class="text-green-700 mb-2"><?= htmlspecialchars($currentTheme['theme_description'] ?? '') ?></p>
                            <?php if ($currentTheme['theme_author']): ?>
                                <p class="text-sm text-green-600">By: <?= htmlspecialchars($currentTheme['theme_author']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-green-600">
                            <i class="fas fa-check-circle text-3xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Available Themes -->
        <div class="form-section">
            <div class="form-section-header">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-palette text-blue-600 mr-2"></i>
                    Available Themes
                </h2>
            </div>
            <div class="form-section-content">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <?php foreach ($allThemes as $theme): ?>
                        <?php if ($theme['theme_name'] !== $currentTheme['theme_name']): ?>
                            <div class="border border-gray-200 rounded-lg p-6 hover:border-blue-300 transition-colors">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-semibold text-gray-900 mb-2">
                                            <?= htmlspecialchars($theme['theme_display_name']) ?>
                                        </h3>
                                        <p class="text-sm text-gray-600 mb-2">
                                            <?= htmlspecialchars($theme['theme_description'] ?? '') ?>
                                        </p>
                                        <?php if ($theme['theme_author']): ?>
                                            <p class="text-xs text-gray-500">By: <?= htmlspecialchars($theme['theme_author']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Theme Preview Colors -->
                                <?php 
                                $config = json_decode($theme['theme_config'], true);
                                $primaryColor = $config['primary_color'] ?? '#3b82f6';
                                $secondaryColor = $config['secondary_color'] ?? '#6b7280';
                                ?>
                                <div class="flex items-center mb-4">
                                    <div class="w-8 h-8 rounded border border-gray-300 mr-2" 
                                         style="background-color: <?= $primaryColor ?>"></div>
                                    <div class="w-8 h-8 rounded border border-gray-300 mr-2" 
                                         style="background-color: <?= $secondaryColor ?>"></div>
                                    <span class="text-xs text-gray-500 ml-2">
                                        <?= $config['font_family'] ?? 'Inter' ?>
                                    </span>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="action" value="theme_settings">
                                        <input type="hidden" name="activate_theme" value="<?= $theme['theme_name'] ?>">
                                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors text-sm">
                                            Activate
                                        </button>
                                    </form>
                                    
                                    <?php if (!in_array($theme['theme_name'], ['default', 'dark', 'corporate', 'creative', 'minimal'])): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this theme?')">
                                            <input type="hidden" name="action" value="theme_settings">
                                            <input type="hidden" name="delete_theme" value="<?= $theme['theme_name'] ?>">
                                            <button type="submit" class="bg-red-600 text-white px-3 py-2 rounded hover:bg-red-700 transition-colors text-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Upload Theme Section (replaces Create New Theme) -->
        <div class="form-section">
            <div class="form-section-header">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-upload text-blue-600 mr-2"></i>
                    Upload New Theme
                </h2>
            </div>
            <div class="form-section-content">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h4 class="text-md font-medium text-blue-800 mb-3">
                        <i class="fas fa-info-circle mr-2"></i>Theme Package Requirements
                    </h4>
                    <div class="text-sm text-blue-700 space-y-2">
                        <p>• Theme must be packaged as a ZIP file</p>
                        <p>• Must contain a <code class="bg-blue-100 px-1 rounded">theme.json</code> configuration file</p>
                        <p>• Maximum file size: 10MB</p>
                        <p>• Theme files will be installed to the themes directory</p>
                    </div>
                </div>
                
                <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data" id="theme-upload-form">
                    <input type="hidden" name="action" value="theme_settings">
                    <input type="hidden" name="upload_theme" value="1">
                    
                    <div class="space-y-6">
                        <!-- File Upload Area -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-file-archive mr-1"></i>Theme Package (ZIP file)
                            </label>
                            
                            <!-- Drag & Drop Area -->
                            <div class="theme-upload-area border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors cursor-pointer"
                                onclick="document.getElementById('theme-file-input').click()"
                                ondragover="handleThemeDragOver(event)"
                                ondragleave="handleThemeDragLeave(event)"
                                ondrop="handleThemeDrop(event)">
                                
                                <div class="theme-upload-content">
                                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">Upload Theme Package</h3>
                                    <p class="text-gray-600 mb-4">Drag and drop your theme ZIP file here, or click to browse</p>
                                    <div class="text-sm text-gray-500">
                                        <span class="font-medium">Supported:</span> ZIP files up to 10MB
                                    </div>
                                </div>
                                
                                <!-- Hidden file input -->
                                <input type="file" 
                                    id="theme-file-input" 
                                    name="theme_file" 
                                    accept=".zip" 
                                    required 
                                    class="hidden" 
                                    onchange="previewThemeFile(this)">
                            </div>
                            
                            <!-- File Preview -->
                            <div id="theme-file-preview" class="hidden mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <i class="fas fa-file-archive text-blue-600 text-xl mr-3"></i>
                                        <div>
                                            <div class="font-medium text-gray-900" id="theme-file-name"></div>
                                            <div class="text-sm text-gray-500" id="theme-file-size"></div>
                                        </div>
                                    </div>
                                    <button type="button" onclick="clearThemeFile()" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Upload Progress -->
                        <div id="theme-upload-progress" class="hidden">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center mb-2">
                                    <i class="fas fa-spinner fa-spin text-blue-600 mr-2"></i>
                                    <span class="text-blue-800 font-medium">Installing theme...</span>
                                </div>
                                <div class="w-full bg-blue-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%" id="progress-bar"></div>
                                </div>
                                <div class="text-sm text-blue-700 mt-2">Please wait while the theme is being processed and installed.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 flex justify-between items-center">
                        <button type="submit" class="btn-primary" id="upload-theme-btn">
                            <i class="fas fa-upload"></i>
                            Upload & Install Theme
                        </button>
                        
                        <div class="text-sm text-gray-500">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Themes are sandboxed for security
                        </div>
                    </div>
                </form>
                
                <!-- Theme Package Format Example -->
                <!-- <div class="mt-8 bg-gray-50 border border-gray-200 rounded-lg p-6">
                    <h4 class="text-md font-medium text-gray-800 mb-3">
                        <i class="fas fa-code mr-2"></i>Expected theme.json Format
                    </h4>
                    <pre class="bg-gray-800 text-green-400 p-4 rounded text-sm overflow-x-auto"><code>{
                    "name": "my-awesome-theme",
                    "display_name": "My Awesome Theme",
                    "description": "A beautiful theme for your website",
                    "author": "Your Name",
                    "version": "1.0.0",
                    "colors": {
                        "primary": "#3b82f6",
                        "secondary": "#6b7280"
                    },
                    "typography": {
                        "font_family": "Inter"
                    },
                    "styles": {
                        "button_style": "rounded"
                    }
                    }</code></pre>
                </div> -->
            </div>
        </div>
    </div>
</div>
        </div>
    </div>

    <script>
        // Enhanced tab switching with improved UX
        let currentTab = '<?= $activeTab ?>';
        let isTransitioning = false;

        function switchTab(tabName) {
            if (isTransitioning || tabName === currentTab) return;
            
            isTransitioning = true;
            
            // Add loading state
            const clickedButton = document.querySelector(`[data-tab="${tabName}"]`);
            if (clickedButton) {
                clickedButton.classList.add('tab-loading');
            }
            
            // Update active button
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            if (clickedButton) {
                clickedButton.classList.add('active');
                
                // Update indicator position
                updateTabIndicator(clickedButton);
                
                // Scroll tab into view if needed
                scrollTabIntoView(clickedButton);
            }
            
            // Hide current tab with fade out
            const currentTabElement = document.getElementById(`${currentTab}-tab`);
            if (currentTabElement) {
                currentTabElement.style.opacity = '0';
                currentTabElement.style.transform = 'translateY(-10px)';
                
                setTimeout(() => {
                    currentTabElement.classList.remove('active');
                    
                    // Show new tab with fade in
                    const newTabElement = document.getElementById(`${tabName}-tab`);
                    if (newTabElement) {
                        newTabElement.classList.add('active');
                        
                        // Force reflow
                        newTabElement.offsetHeight;
                        
                        newTabElement.style.opacity = '1';
                        newTabElement.style.transform = 'translateY(0)';
                        
                        currentTab = tabName;
                        
                        // Update URL without refresh
                        if (history.pushState) {
                            history.pushState(null, null, `#${tabName}`);
                        }
                        
                        // Remove loading state and re-enable transitions
                        setTimeout(() => {
                            if (clickedButton) {
                                clickedButton.classList.remove('tab-loading');
                            }
                            isTransitioning = false;
                        }, 300);
                    }
                }, 150);
            }
        }
        
        function updateTabIndicator(activeButton) {
            const indicator = document.querySelector('.tab-indicator');
            if (indicator && activeButton) {
                const buttonRect = activeButton.getBoundingClientRect();
                const containerRect = activeButton.parentElement.getBoundingClientRect();
                
                const left = buttonRect.left - containerRect.left;
                const width = buttonRect.width;
                
                indicator.style.left = `${left}px`;
                indicator.style.width = `${width}px`;
            }
        }
        
        function scrollTabIntoView(button) {
            const container = document.querySelector('.tab-navigation');
            const buttonRect = button.getBoundingClientRect();
            const containerRect = container.getBoundingClientRect();
            
            if (buttonRect.left < containerRect.left) {
                container.scrollLeft -= (containerRect.left - buttonRect.left + 20);
            } else if (buttonRect.right > containerRect.right) {
                container.scrollLeft += (buttonRect.right - containerRect.right + 20);
            }
        }
        
        function togglePageSEO(pageId) {
            const content = document.getElementById('page-seo-' + pageId);
            const header = event.currentTarget;
            const icon = header.querySelector('.page-toggle-icon');
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                icon.style.transform = 'rotate(0deg)';
            } else {
                // Close all other open page SEO sections
                document.querySelectorAll('.page-seo-content').forEach(el => {
                    el.classList.remove('active');
                });
                document.querySelectorAll('.page-toggle-icon').forEach(el => {
                    el.style.transform = 'rotate(0deg)';
                });
                
                // Open this section
                content.classList.add('active');
                icon.style.transform = 'rotate(180deg)';
            }
        }
        
        function toggleRecaptchaFields() {
            const checkbox = document.getElementById('recaptcha-enabled');
            const fields = document.getElementById('recaptcha-fields');
            
            if (checkbox.checked) {
                fields.classList.remove('hidden');
                // Add a smooth slide-down effect
                fields.style.maxHeight = '0px';
                fields.style.overflow = 'hidden';
                fields.style.transition = 'max-height 0.3s ease-out';
                
                setTimeout(() => {
                    fields.style.maxHeight = fields.scrollHeight + 'px';
                }, 10);
                
                setTimeout(() => {
                    fields.style.maxHeight = 'none';
                    fields.style.overflow = 'visible';
                }, 300);
            } else {
                // Add a smooth slide-up effect
                fields.style.maxHeight = fields.scrollHeight + 'px';
                fields.style.overflow = 'hidden';
                fields.style.transition = 'max-height 0.3s ease-out';
                
                setTimeout(() => {
                    fields.style.maxHeight = '0px';
                }, 10);
                
                setTimeout(() => {
                    fields.classList.add('hidden');
                    fields.style.maxHeight = 'none';
                    fields.style.overflow = 'visible';
                }, 300);
            }
        }
        
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            const strengthBar = document.getElementById('password-strength-bar');
            const feedbackElement = document.getElementById('password-feedback');
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength-bar';
                feedback = 'Password must be at least 6 characters long';
            } else if (strength < 3) {
                strengthBar.style.width = '33%';
                strengthBar.className = 'password-strength-bar strength-weak';
                feedback = 'Weak password - Add uppercase, numbers, or symbols';
            } else if (strength < 5) {
                strengthBar.style.width = '66%';
                strengthBar.className = 'password-strength-bar strength-medium';
                feedback = 'Medium strength - Consider adding more complexity';
            } else {
                strengthBar.style.width = '100%';
                strengthBar.className = 'password-strength-bar strength-strong';
                feedback = 'Strong password!';
            }
            
            feedbackElement.textContent = feedback;
            return strength;
        }
        
        // Validate password confirmation
        function validatePasswordConfirmation() {
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const feedback = document.getElementById('confirm-feedback');
            const submitButton = document.getElementById('submit-password');
            
            if (confirmPassword.length === 0) {
                feedback.textContent = '';
                feedback.className = 'text-xs text-gray-500 mt-1';
            } else if (newPassword === confirmPassword) {
                feedback.textContent = '✓ Passwords match';
                feedback.className = 'text-xs text-green-600 mt-1';
            } else {
                feedback.textContent = '✗ Passwords do not match';
                feedback.className = 'text-xs text-red-600 mt-1';
            }
            
            // Enable/disable submit button
            const passwordStrength = checkPasswordStrength(newPassword);
            const passwordsMatch = newPassword === confirmPassword && newPassword.length > 0;
            submitButton.disabled = !(passwordStrength >= 2 && passwordsMatch);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize indicator position
            const activeButton = document.querySelector('.tab-button.active');
            if (activeButton) {
                updateTabIndicator(activeButton);
            }
            
            // Handle browser back/forward
            window.addEventListener('popstate', function(event) {
                const hash = window.location.hash.substring(1);
                if (hash && document.getElementById(`${hash}-tab`)) {
                    switchTab(hash);
                }
            });
            
            // Handle initial hash
            const initialHash = window.location.hash.substring(1);
            if (initialHash && document.getElementById(`${initialHash}-tab`)) {
                switchTab(initialHash);
            }
            
            // Add keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.altKey && e.key >= '1' && e.key <= '7') {
                    e.preventDefault();
                    const tabIndex = parseInt(e.key) - 1;
                    const tabs = ['general', 'contact', 'social', 'seo', 'page_seo', 'users', 'system', 'license', 'themes'];
                    if (tabs[tabIndex]) {
                        switchTab(tabs[tabIndex]);
                    }
                }
            });
            
            // Add touch/swipe support for mobile
            let touchStartX = 0;
            let touchEndX = 0;
            
            document.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            document.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                const swipeThreshold = 50;
                const diff = touchStartX - touchEndX;
                
                if (Math.abs(diff) > swipeThreshold) {
                    const tabs = ['general', 'contact', 'social', 'seo', 'page_seo', 'users', 'system', 'themes'];
                    const currentIndex = tabs.indexOf(currentTab);
                    
                    if (diff > 0 && currentIndex < tabs.length - 1) {
                        switchTab(tabs[currentIndex + 1]);
                    } else if (diff < 0 && currentIndex > 0) {
                        // Swipe right - previous tab
                        switchTab(tabs[currentIndex - 1]);
                    }
                }
            }
            
            // Password strength and validation
            const newPasswordField = document.getElementById('new-password');
            const confirmPasswordField = document.getElementById('confirm-password');
            
            if (newPasswordField) {
                newPasswordField.addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                    validatePasswordConfirmation();
                });
            }
            
            if (confirmPasswordField) {
                confirmPasswordField.addEventListener('input', validatePasswordConfirmation);
            }
            
            // Initialize reCAPTCHA fields visibility
            const recaptchaCheckbox = document.getElementById('recaptcha-enabled');
            if (recaptchaCheckbox && !recaptchaCheckbox.checked) {
                const fields = document.getElementById('recaptcha-fields');
                if (fields) {
                    fields.classList.add('hidden');
                }
            }
            
            // Add character counters to SEO fields
            const titleFields = document.querySelectorAll('input[name="meta_title"]');
            const descFields = document.querySelectorAll('textarea[name="meta_description"]');
            
            titleFields.forEach(field => {
                addCharacterCounter(field, 60);
            });
            
            descFields.forEach(field => {
                addCharacterCounter(field, 160);
            });
            
            // Enhanced form validation
            document.querySelectorAll('input[required], textarea[required]').forEach(field => {
                field.addEventListener('blur', function() {
                    validateField(this);
                });
            });
            
            // Auto-save functionality (optional)
            let autoSaveTimeout;
            
            function autoSave() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    console.log('Auto-saving...');
                    // Implement auto-save logic here
                }, 2000);
            }
            
            // Add auto-save to form fields
            document.querySelectorAll('input, textarea, select').forEach(field => {
                field.addEventListener('input', autoSave);
            });
        });
        
        function addCharacterCounter(field, maxLength) {
            const counter = document.createElement('div');
            counter.className = 'text-xs text-gray-500 mt-1';
            counter.style.textAlign = 'right';
            
            function updateCounter() {
                const length = field.value.length;
                counter.textContent = length + '/' + maxLength + ' characters';
                
                if (length > maxLength) {
                    counter.className = 'text-xs text-red-500 mt-1';
                } else if (length > maxLength * 0.9) {
                    counter.className = 'text-xs text-yellow-600 mt-1';
                } else {
                    counter.className = 'text-xs text-gray-500 mt-1';
                }
            }
            
            field.addEventListener('input', updateCounter);
            field.parentNode.appendChild(counter);
            updateCounter();
        }
        
        function validateField(field) {
            if (field.value.trim() === '') {
                field.classList.add('border-red-500');
                field.classList.remove('border-gray-300');
            } else {
                field.classList.remove('border-red-500');
                field.classList.add('border-gray-300');
            }
        }
        
        // Show success message when forms are submitted
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Don't prevent default, let the form submit normally
                    console.log('Form being submitted:', this);
                    const formData = new FormData(this);
                    console.log('Form data:');
                    for (let [key, value] of formData.entries()) {
                        console.log(key + ': ' + value);
                    }
                });
            });
        });

function previewFavicon(input) {
    const preview = document.getElementById('favicon-preview');
    const file = input.files[0];
    
    if (file) {
        // Validate file type
        const allowedTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/ico', 'image/icon', 'image/png', 'image/jpeg', 'image/gif'];
        const allowedExtensions = ['ico', 'png', 'jpg', 'jpeg', 'gif'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
            alert('Invalid file type. Please upload an ICO, PNG, JPG, or GIF file.');
            input.value = '';
            return;
        }
        
        // Validate file size (1MB max)
        if (file.size > 1024 * 1024) {
            alert('File size too large. Maximum size is 1MB.');
            input.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Favicon preview">`;
        };
        reader.readAsDataURL(file);
        
        // Show remove button if it doesn't exist
        showRemoveButton();
    }
}

// Enhanced remove favicon function with AJAX
function removeFavicon() {
    if (!confirm('Are you sure you want to remove the favicon?')) {
        return;
    }
    
    // Show loading state
    const removeBtn = document.querySelector('.favicon-remove-btn');
    const originalText = removeBtn.innerHTML;
    removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Removing...';
    removeBtn.disabled = true;
    
    // Send AJAX request to remove favicon
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=remove_favicon'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear preview
            const preview = document.getElementById('favicon-preview');
            preview.innerHTML = '';
            
            // Clear input
            const input = document.getElementById('favicon-upload');
            input.value = '';
            
            // Remove button
            if (removeBtn) {
                removeBtn.remove();
            }
            
            // Update status indicator
            updateFaviconStatus(false);
            
            // Show success message
            showMessage('Favicon removed successfully!', 'success');
            
        } else {
            // Show error message
            showMessage('Error removing favicon: ' + data.message, 'error');
            
            // Restore button
            removeBtn.innerHTML = originalText;
            removeBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error removing favicon. Please try again.', 'error');
        
        // Restore button
        removeBtn.innerHTML = originalText;
        removeBtn.disabled = false;
    });
}

// Helper function to show remove button
function showRemoveButton() {
    const container = document.querySelector('.favicon-upload-container .flex-1');
    let removeBtn = container.querySelector('.favicon-remove-btn');
    
    if (!removeBtn) {
        removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'favicon-remove-btn';
        removeBtn.innerHTML = '<i class="fas fa-trash-alt mr-1"></i>Remove';
        removeBtn.onclick = removeFavicon;
        container.appendChild(removeBtn);
    }
}

// Helper function to update favicon status indicator
function updateFaviconStatus(isActive) {
    const container = document.querySelector('.favicon-upload-container');
    const statusDiv = container.querySelector('.mb-3');
    
    if (isActive) {
        statusDiv.innerHTML = `
            <div class="flex items-center text-green-600 text-sm">
                <i class="fas fa-check-circle mr-2"></i>
                <span>Favicon is active and displayed on your site</span>
            </div>
        `;
    } else {
        statusDiv.innerHTML = `
            <div class="flex items-center text-gray-500 text-sm">
                <i class="fas fa-info-circle mr-2"></i>
                <span>No favicon uploaded. Upload one to display on your site.</span>
            </div>
        `;
    }
}

// Helper function to show messages
function showMessage(message, type) {
    // Remove existing messages
    const existingMessage = document.querySelector('.favicon-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `favicon-message alert-message ${type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'} px-4 py-3 rounded mb-4`;
    messageDiv.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
        ${message}
    `;
    
    // Insert message at the top of the form section
    const formSection = document.querySelector('.form-section-content');
    formSection.insertBefore(messageDiv, formSection.firstChild);
    
    // Auto-remove message after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
}

// Enhanced drag and drop with BASE_URL preview support
document.addEventListener('DOMContentLoaded', function() {
    const faviconContainer = document.querySelector('.favicon-upload-container');
    const faviconInput = document.getElementById('favicon-upload');
    
    if (faviconContainer && faviconInput) {
        faviconContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            faviconContainer.style.backgroundColor = '#f0f9ff';
            faviconContainer.style.borderColor = '#3b82f6';
        });
        
        faviconContainer.addEventListener('dragleave', function(e) {
            e.preventDefault();
            faviconContainer.style.backgroundColor = '';
            faviconContainer.style.borderColor = '';
        });
        
        faviconContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            faviconContainer.style.backgroundColor = '';
            faviconContainer.style.borderColor = '';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                faviconInput.files = files;
                previewFavicon(faviconInput);
            }
        });
    }
});

 function removeOGImage(pageId) {
    if (!confirm('Are you sure you want to remove this OG image?')) {
        return;
    }
    
    // Show loading state
    const removeBtn = document.querySelector(`button[onclick="removeOGImage(${pageId})"]`);
    if (removeBtn) {
        const originalText = removeBtn.innerHTML;
        removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Removing...';
        removeBtn.disabled = true;
        
        // Restore button function
        const restoreButton = () => {
            removeBtn.innerHTML = originalText;
            removeBtn.disabled = false;
        };
        
        // Send AJAX request with proper form data
        const formData = new FormData();
        formData.append('ajax_action', 'remove_og_image');
        formData.append('page_id', pageId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Clear preview
                const preview = document.getElementById('og-image-preview-' + pageId);
                if (preview) {
                    preview.innerHTML = `
                        <div class="og-image-preview-placeholder">
                            <i class="fas fa-image text-2xl mb-2"></i>
                            <div>1200x630px recommended</div>
                        </div>
                    `;
                }
                
                // Clear file input
                const fileInput = document.getElementById('og-image-upload-' + pageId);
                if (fileInput) {
                    fileInput.value = '';
                }
                
                // Clear manual URL input - Need to find the correct input within the form
                const form = removeBtn.closest('form');
                if (form) {
                    const urlInput = form.querySelector('input[name="og_image"]');
                    if (urlInput) {
                        urlInput.value = '';
                    }
                }
                
                // Remove the remove button since there's no image anymore
                removeBtn.remove();
                
                // Show success message
                showOGMessage('OG image removed successfully!', 'success', pageId);
                
            } else {
                restoreButton();
                showOGMessage('Error removing OG image: ' + (data.message || 'Unknown error'), 'error', pageId);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            restoreButton();
            showOGMessage('Error removing OG image. Please try again.', 'error', pageId);
        });
    }
}

// Helper function to show OG-specific messages
function showOGMessage(message, type, pageId) {
    // Remove existing messages for this page
    const existingMessage = document.querySelector(`.og-message-${pageId}`);
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `og-message-${pageId} alert-message ${type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'} px-4 py-3 rounded mb-4`;
    messageDiv.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
        ${message}
    `;
    
    // Insert message at the top of the page SEO content
    const pageContent = document.getElementById('page-seo-' + pageId);
    if (pageContent) {
        pageContent.insertBefore(messageDiv, pageContent.firstChild);
    }
    
    // Auto-remove message after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
}

// Enhanced previewOGImage function with better error handling
function previewOGImage(input, pageId) {
    const preview = document.getElementById('og-image-preview-' + pageId);
    const file = input.files[0];
    
    if (file) {
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
            showOGMessage('Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.', 'error', pageId);
            input.value = '';
            return;
        }
        
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            showOGMessage('File size too large. Maximum size is 5MB.', 'error', pageId);
            input.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="OG Image preview">`;
        };
        reader.onerror = function() {
            showOGMessage('Error reading file. Please try again.', 'error', pageId);
        };
        reader.readAsDataURL(file);
    }
}

// Add these JavaScript functions to your existing <script> section

function previewLogo(input) {
    const preview = document.getElementById('logo-preview');
    const file = input.files[0];
    
    if (file) {
        // Validate file type
        const allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/svg+xml'];
        const allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
            showLogoMessage('Invalid file type. Please upload a PNG, JPG, GIF, WebP, or SVG file.', 'error');
            input.value = '';
            return;
        }
        
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            showLogoMessage('File size too large. Maximum size is 2MB.', 'error');
            input.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Logo preview">`;
            preview.classList.add('has-image');
        };
        reader.onerror = function() {
            showLogoMessage('Error reading file. Please try again.', 'error');
        };
        reader.readAsDataURL(file);
        
        // Show remove button if it doesn't exist
        showLogoRemoveButton();
    }
}

// Enhanced remove logo function with AJAX
function removeLogo() {
    if (!confirm('Are you sure you want to remove the site logo?')) {
        return;
    }
    
    // Show loading state
    const removeBtn = document.querySelector('.logo-remove-btn');
    if (!removeBtn) return;
    
    const originalText = removeBtn.innerHTML;
    removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Removing...';
    removeBtn.disabled = true;
    
    // Send AJAX request to remove logo
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=remove_logo'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear preview
            const preview = document.getElementById('logo-preview');
            preview.innerHTML = '';
            preview.classList.remove('has-image');
            
            // Clear input
            const input = document.getElementById('logo-upload');
            input.value = '';
            
            // Remove button
            if (removeBtn) {
                removeBtn.remove();
            }
            
            // Update status indicator
            updateLogoStatus(false);
            
            // Show success message
            showLogoMessage('Site logo removed successfully!', 'success');
            
        } else {
            // Show error message
            showLogoMessage('Error removing logo: ' + data.message, 'error');
            
            // Restore button
            removeBtn.innerHTML = originalText;
            removeBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showLogoMessage('Error removing logo. Please try again.', 'error');
        
        // Restore button
        removeBtn.innerHTML = originalText;
        removeBtn.disabled = false;
    });
}

// Helper function to show remove button for logo
function showLogoRemoveButton() {
    const container = document.querySelector('.logo-upload-container .flex-1');
    let removeBtn = container.querySelector('.logo-remove-btn');
    
    if (!removeBtn) {
        removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'logo-remove-btn';
        removeBtn.innerHTML = '<i class="fas fa-trash-alt mr-1"></i>Remove';
        removeBtn.onclick = removeLogo;
        container.appendChild(removeBtn);
    }
}

// Helper function to update logo status indicator
function updateLogoStatus(isActive) {
    const container = document.querySelector('.logo-upload-container');
    const statusDiv = container.querySelector('.mb-3');
    
    if (isActive) {
        statusDiv.innerHTML = `
            <div class="flex items-center text-green-600 text-sm">
                <i class="fas fa-check-circle mr-2"></i>
                <span>Site logo is active and displayed on your site</span>
            </div>
        `;
    } else {
        statusDiv.innerHTML = `
            <div class="flex items-center text-gray-500 text-sm">
                <i class="fas fa-info-circle mr-2"></i>
                <span>No logo uploaded. Upload one to display on your site.</span>
            </div>
        `;
    }
}

// Helper function to show logo-specific messages
function showLogoMessage(message, type) {
    // Remove existing messages
    const existingMessage = document.querySelector('.logo-message');
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `logo-message alert-message ${type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'} px-4 py-3 rounded mb-4`;
    messageDiv.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
        ${message}
    `;
    
    // Insert message at the top of the form section
    const formSection = document.querySelector('.form-section-content');
    formSection.insertBefore(messageDiv, formSection.firstChild);
    
    // Auto-remove message after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 5000);
}

// Enhanced drag and drop for logo upload
document.addEventListener('DOMContentLoaded', function() {
    const logoContainer = document.querySelector('.logo-upload-container');
    const logoInput = document.getElementById('logo-upload');
    const logoPreview = document.getElementById('logo-preview');
    
    if (logoContainer && logoInput && logoPreview) {
        // Drag and drop functionality
        logoPreview.addEventListener('dragover', function(e) {
            e.preventDefault();
            logoPreview.classList.add('drag-over');
        });
        
        logoPreview.addEventListener('dragleave', function(e) {
            e.preventDefault();
            logoPreview.classList.remove('drag-over');
        });
        
        logoPreview.addEventListener('drop', function(e) {
            e.preventDefault();
            logoPreview.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                logoInput.files = files;
                previewLogo(logoInput);
            }
        });
        
        // Click to upload
        logoPreview.addEventListener('click', function() {
            logoInput.click();
        });
        
        // Add cursor pointer when hovering over preview
        logoPreview.style.cursor = 'pointer';
    }
    
    // Add logo upload progress indicator (optional enhancement)
    const logoForm = document.querySelector('form[action*="settings"]');
    if (logoForm) {
        logoForm.addEventListener('submit', function() {
            const logoInput = document.getElementById('logo-upload');
            if (logoInput && logoInput.files.length > 0) {
                const preview = document.getElementById('logo-preview');
                preview.classList.add('logo-loading');
                
                // Remove loading state after form submission
                setTimeout(() => {
                    preview.classList.remove('logo-loading');
                }, 1000);
            }
        });
    }
});
// License validation function
function validateLicense() {
    const licenseKey = document.getElementById('license-key-input').value.trim();
    const resultDiv = document.getElementById('license-validation-result');
    const saveBtn = document.getElementById('save-license-btn');
    
    if (!licenseKey) {
        showLicenseValidationResult('Please enter a license key', 'error');
        saveBtn.disabled = true;
        return;
    }
    
    // Show loading state
    showLicenseValidationResult('<i class="fas fa-spinner fa-spin mr-2"></i>Validating license...', 'info');
    
    // AJAX validation
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax_action=validate_license&license_key=${encodeURIComponent(licenseKey)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showLicenseValidationResult(data.message, 'success');
            updateLicensePreview(data.data);
            saveBtn.disabled = false;
        } else {
            showLicenseValidationResult(data.message, 'error');
            hideLicensePreview();
            saveBtn.disabled = true;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showLicenseValidationResult('Error validating license. Please try again.', 'error');
        saveBtn.disabled = true;
    });
}

function showLicenseValidationResult(message, type) {
    const resultDiv = document.getElementById('license-validation-result');
    
    let bgColor, textColor, icon;
    
    switch(type) {
        case 'success':
            bgColor = 'bg-green-50';
            textColor = 'text-green-700';
            icon = 'fa-check-circle';
            break;
        case 'error':
            bgColor = 'bg-red-50';
            textColor = 'text-red-700';
            icon = 'fa-exclamation-circle';
            break;
        case 'info':
            bgColor = 'bg-blue-50';
            textColor = 'text-blue-700';
            icon = 'fa-info-circle';
            break;
        default:
            bgColor = 'bg-gray-50';
            textColor = 'text-gray-700';
            icon = 'fa-info-circle';
    }
    
    resultDiv.innerHTML = `
        <div class="${bgColor} border border-${type === 'success' ? 'green' : (type === 'error' ? 'red' : 'blue')}-200 rounded px-3 py-2">
            <span class="${textColor} text-sm">
                <i class="fas ${icon} mr-1"></i>${message}
            </span>
        </div>
    `;
}

function updateLicensePreview(data) {
    const previewDiv = document.getElementById('license-details-preview');
    
    document.getElementById('preview-type').textContent = data.license_type || '-';
    document.getElementById('preview-holder').textContent = data.license_holder || '-';
    document.getElementById('preview-organization').textContent = data.license_organization || '-';
    document.getElementById('preview-valid-until').textContent = data.license_valid_until ? 
        new Date(data.license_valid_until).toLocaleDateString() : '-';
    
    const featuresDiv = document.getElementById('preview-features');
    if (data.license_features) {
        const features = data.license_features.split('\n').filter(f => f.trim());
        featuresDiv.innerHTML = features.map(f => `• ${f.trim()}`).join('<br>');
    } else {
        featuresDiv.textContent = '-';
    }
    
    previewDiv.classList.remove('hidden');
}

function hideLicensePreview() {
    const previewDiv = document.getElementById('license-details-preview');
    previewDiv.classList.add('hidden');
}

// Format license key input
// document.addEventListener('DOMContentLoaded', function() {
//     const licenseKeyInput = document.getElementById('license-key-input');
    
//     if (licenseKeyInput) {
//         licenseKeyInput.addEventListener('input', function(e) {
//             // Remove any non-alphanumeric characters except hyphens
//             let value = e.target.value.replace(/[^A-Z0-9\-]/gi, '').toUpperCase();
            
//             // Remove existing hyphens first
//             value = value.replace(/-/g, '');
            
//             // Add hyphens every 4 characters for XXXX-XXXX-XXXX-XXXX format
//             if (value.length > 0) {
//                 value = value.match(/.{1,4}/g).join('-');
//             }
            
//             // Limit to 19 characters (XXXX-XXXX-XXXX-XXXX)
//             if (value.length > 19) {
//                 value = value.substring(0, 19);
//             }
            
//             e.target.value = value;
//         });
        
//         licenseKeyInput.addEventListener('paste', function(e) {
//             setTimeout(() => {
//                 let value = e.target.value.replace(/[^A-Z0-9\-]/gi, '').toUpperCase();
//                 value = value.replace(/-/g, '');
                
//                 if (value.length > 0) {
//                     value = value.match(/.{1,4}/g).join('-');
//                 }
                
//                 if (value.length > 19) {
//                     value = value.substring(0, 19);
//                 }
                
//                 e.target.value = value;
//             }, 10);
//         });
        
//         // Auto-validate on blur
//         licenseKeyInput.addEventListener('blur', function(e) {
//             if (e.target.value.trim().length > 0) {
//                 validateLicense();
//             }
//         });
//     }
    
//     // License form submission enhancement
//     const licenseForm = document.getElementById('license-form');
//     if (licenseForm) {
//         licenseForm.addEventListener('submit', function(e) {
//             const licenseKey = document.getElementById('license-key-input').value.trim();
            
//             if (!licenseKey || licenseKey.length !== 19) {
//                 e.preventDefault();
//                 showLicenseValidationResult('Please enter a valid license key in format XXXX-XXXX-XXXX-XXXX', 'error');
//                 return false;
//             }
            
//             // Show saving state
//             const submitBtn = licenseForm.querySelector('button[type="submit"]');
//             if (submitBtn) {
//                 const originalText = submitBtn.innerHTML;
//                 submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving License...';
//                 submitBtn.disabled = true;
                
//                 // Re-enable after form submission
//                 setTimeout(() => {
//                     submitBtn.innerHTML = originalText;
//                     submitBtn.disabled = false;
//                 }, 3000);
//             }
//         });
//     }
// });

// =============================================
// STEP 9: ADD THEME JAVASCRIPT TO SETTINGS PAGE
// =============================================
// Add this to your existing <script> section in settings.php

// Update your existing switchTab function to include themes:

// Add theme-specific functions:
function syncColorInputs() {
    // Sync color picker with text input for primary color
    const primaryColorPicker = document.querySelector('input[name="new_primary_color"]');
    const primaryColorText = document.querySelector('input[name="new_primary_color"]').nextElementSibling;
    
    if (primaryColorPicker && primaryColorText) {
        primaryColorPicker.addEventListener('input', function() {
            primaryColorText.value = this.value;
        });
        
        primaryColorText.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                primaryColorPicker.value = this.value;
            }
        });
    }
    
    // Sync color picker with text input for secondary color
    const secondaryColorPicker = document.querySelector('input[name="new_secondary_color"]');
    const secondaryColorText = document.querySelector('input[name="new_secondary_color"]').nextElementSibling;
    
    if (secondaryColorPicker && secondaryColorText) {
        secondaryColorPicker.addEventListener('input', function() {
            secondaryColorText.value = this.value;
        });
        
        secondaryColorText.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                secondaryColorPicker.value = this.value;
            }
        });
    }
}

function validateThemeName() {
    const themeNameInput = document.querySelector('input[name="new_theme_name"]');
    if (themeNameInput) {
        themeNameInput.addEventListener('input', function() {
            // Convert to lowercase and remove invalid characters
            this.value = this.value.toLowerCase().replace(/[^a-z0-9_-]/g, '');
            
            // Update the display name if it's empty
            const displayNameInput = document.querySelector('input[name="new_theme_display_name"]');
            if (displayNameInput && !displayNameInput.value) {
                // Convert theme name to display name (capitalize words)
                const displayName = this.value
                    .split(/[-_]/)
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ');
                displayNameInput.value = displayName + ' Theme';
            }
        });
    }
}

function previewTheme() {
    const form = document.querySelector('form[action*="theme_settings"]');
    if (!form) return;
    
    const primaryColor = form.querySelector('input[name="new_primary_color"]')?.value || '#3b82f6';
    const secondaryColor = form.querySelector('input[name="new_secondary_color"]')?.value || '#6b7280';
    const fontFamily = form.querySelector('select[name="new_font_family"]')?.value || 'Inter';
    
    // Create preview button
    const previewBtn = document.createElement('button');
    previewBtn.type = 'button';
    previewBtn.className = 'ml-4 px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-50';
    previewBtn.innerHTML = '<i class="fas fa-eye mr-1"></i>Preview';
    previewBtn.onclick = function() {
        showThemePreview(primaryColor, secondaryColor, fontFamily);
    };
    
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn && !submitBtn.parentElement.querySelector('.preview-btn')) {
        previewBtn.classList.add('preview-btn');
        submitBtn.parentElement.appendChild(previewBtn);
    }
}

function showThemePreview(primaryColor, secondaryColor, fontFamily) {
    // Create modal for theme preview
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold">Theme Preview</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <div class="space-y-6">
                    <!-- Hero Section Preview -->
                    <div class="rounded-lg p-8 text-white text-center" style="background: linear-gradient(135deg, ${primaryColor}, ${secondaryColor}); font-family: ${fontFamily}, sans-serif;">
                        <h1 class="text-3xl font-bold mb-4">Sample Hero Section</h1>
                        <p class="text-lg mb-6 opacity-90">This is how your hero section will look with the new theme.</p>
                        <div class="space-x-4">
                            <button class="px-6 py-3 bg-white text-gray-900 rounded-lg font-semibold hover:bg-gray-100 transition-colors" style="color: ${primaryColor};">
                                Primary Button
                            </button>
                            <button class="px-6 py-3 border-2 border-white text-white rounded-lg font-semibold hover:bg-white transition-colors" style="border-color: white; color: white;" onmouseover="this.style.backgroundColor='white'; this.style.color='${primaryColor}';" onmouseout="this.style.backgroundColor='transparent'; this.style.color='white';">
                                Secondary Button
                            </button>
                        </div>
                    </div>
                    
                    <!-- Content Section Preview -->
                    <div class="bg-gray-50 rounded-lg p-8" style="font-family: ${fontFamily}, sans-serif;">
                        <h2 class="text-2xl font-bold mb-4" style="color: ${primaryColor};">Sample Content Section</h2>
                        <p class="text-gray-600 mb-6">This is how your content sections will appear with the selected theme colors and typography.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white p-4 rounded-lg border-l-4" style="border-left-color: ${primaryColor};">
                                <h3 class="font-semibold mb-2">Feature One</h3>
                                <p class="text-sm text-gray-600">Sample feature description.</p>
                            </div>
                            <div class="bg-white p-4 rounded-lg border-l-4" style="border-left-color: ${secondaryColor};">
                                <h3 class="font-semibold mb-2">Feature Two</h3>
                                <p class="text-sm text-gray-600">Sample feature description.</p>
                            </div>
                            <div class="bg-white p-4 rounded-lg border-l-4" style="border-left-color: ${primaryColor};">
                                <h3 class="font-semibold mb-2">Feature Three</h3>
                                <p class="text-sm text-gray-600">Sample feature description.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Color Palette -->
                    <div class="bg-white rounded-lg p-6 border">
                        <h3 class="text-lg font-semibold mb-4">Color Palette</h3>
                        <div class="flex space-x-4">
                            <div class="text-center">
                                <div class="w-16 h-16 rounded-lg border mb-2" style="background-color: ${primaryColor};"></div>
                                <p class="text-sm font-medium">Primary</p>
                                <p class="text-xs text-gray-500">${primaryColor}</p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 rounded-lg border mb-2" style="background-color: ${secondaryColor};"></div>
                                <p class="text-sm font-medium">Secondary</p>
                                <p class="text-xs text-gray-500">${secondaryColor}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Typography -->
                    <div class="bg-white rounded-lg p-6 border">
                        <h3 class="text-lg font-semibold mb-4">Typography</h3>
                        <div style="font-family: ${fontFamily}, sans-serif;">
                            <h1 class="text-3xl font-bold mb-2">Heading 1 - ${fontFamily}</h1>
                            <h2 class="text-2xl font-semibold mb-2">Heading 2 - ${fontFamily}</h2>
                            <p class="text-base mb-2">Regular paragraph text using ${fontFamily} font family.</p>
                            <p class="text-sm text-gray-600">Small text using ${fontFamily} font family.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

function handleThemeDragOver(event) {
    event.preventDefault();
    event.currentTarget.classList.add('drag-over');
}

function handleThemeDragLeave(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
}

function handleThemeDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        const fileInput = document.getElementById('theme-file-input');
        fileInput.files = files;
        previewThemeFile(fileInput);
    }
}

function previewThemeFile(input) {
    const file = input.files[0];
    const preview = document.getElementById('theme-file-preview');
    const fileName = document.getElementById('theme-file-name');
    const fileSize = document.getElementById('theme-file-size');
    
    if (file) {
        // Validate file type
        if (!file.name.toLowerCase().endsWith('.zip')) {
            alert('Please select a ZIP file.');
            clearThemeFile();
            return;
        }
        
        // Validate file size (10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('File size too large. Maximum size is 10MB.');
            clearThemeFile();
            return;
        }
        
        // Show preview
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        preview.classList.remove('hidden');
        
        // Hide upload area content
        document.querySelector('.theme-upload-content').style.opacity = '0.5';
    }
}

function clearThemeFile() {
    const input = document.getElementById('theme-file-input');
    const preview = document.getElementById('theme-file-preview');
    
    input.value = '';
    preview.classList.add('hidden');
    document.querySelector('.theme-upload-content').style.opacity = '1';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Enhanced form submission with progress
document.addEventListener('DOMContentLoaded', function() {
    const themeForm = document.getElementById('theme-upload-form');
    if (themeForm) {
        themeForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('theme-file-input');
            
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a theme file to upload.');
                return;
            }
            
            // Show progress
            showThemeUploadProgress();
        });
    }
});

function showThemeUploadProgress() {
    const progressDiv = document.getElementById('theme-upload-progress');
    const uploadBtn = document.getElementById('upload-theme-btn');
    const progressBar = document.getElementById('progress-bar');
    
    // Show progress section
    progressDiv.classList.remove('hidden');
    
    // Disable submit button
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Installing...';
    
    // Simulate progress (since we can't track actual upload progress easily)
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90; // Stop at 90% until form actually submits
        
        progressBar.style.width = progress + '%';
    }, 200);
    
    // Clear interval after 10 seconds (form should have submitted by then)
    setTimeout(() => {
        clearInterval(interval);
        progressBar.style.width = '100%';
    }, 10000);
}

// =============================================
// LICENSE TAB JAVASCRIPT - Complete Code
// =============================================

/**
 * Scan for license key files (.localkey and .suspendedkey)
 * Makes AJAX request to scan_key_files endpoint
 */
function scanKeyFiles() {
    const statusDiv = document.getElementById('key-files-status');
    
    // Show loading state
    statusDiv.innerHTML = `
        <div class="bg-blue-50 border border-blue-200 rounded p-3">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin text-blue-600 mr-2"></i>
                <span class="text-blue-800">Scanning for license key files...</span>
            </div>
        </div>
    `;
    
    // Send AJAX request to scan for key files
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=scan_key_files'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayKeyFilesStatus(data.files);
        } else {
            showErrorStatus(`Error scanning files: ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Scan Error:', error);
        showErrorStatus('Error scanning files. Please try again.');
    });
}

/**
 * Display the status of found license key files
 * @param {Array} files - Array of file objects from server
 */
function displayKeyFilesStatus(files) {
    const statusDiv = document.getElementById('key-files-status');
    
    if (files.length === 0) {
        statusDiv.innerHTML = `
            <div class="bg-green-50 border border-green-200 rounded p-3">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="text-green-800">No license key files found. System is clean.</span>
                </div>
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="bg-yellow-50 border border-yellow-200 rounded p-3">
            <h4 class="text-sm font-medium text-yellow-800 mb-3">
                <i class="fas fa-file-code mr-1"></i>Found ${files.length} license key file(s):
            </h4>
            <div class="space-y-2">
    `;
    
    files.forEach(file => {
        const modifiedDate = new Date(file.modified * 1000).toLocaleString();
        const fileSize = formatFileSize(file.size);
        
        html += `
            <div class="bg-white border border-yellow-300 rounded p-3">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-file text-yellow-600 mr-2"></i>
                            <span class="font-medium text-yellow-900">${file.file}</span>
                            <span class="ml-2 text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Found</span>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs text-yellow-700 mb-2">
                            <div><strong>Size:</strong> ${fileSize}</div>
                            <div><strong>Modified:</strong> ${modifiedDate}</div>
                            <div><strong>Readable:</strong> ${file.readable ? '✓ Yes' : '✗ No'}</div>
                            <div><strong>Writable:</strong> ${file.writable ? '✓ Yes' : '✗ No'}</div>
                        </div>
                        
                        ${file.content_preview && file.content_preview !== 'File too large to preview' && file.content_preview !== 'File not readable' ? `
                            <div class="mt-2">
                                <details class="text-xs">
                                    <summary class="cursor-pointer text-yellow-800 hover:text-yellow-900">
                                        <strong>📄 File Preview</strong> (${file.content_length} characters)
                                    </summary>
                                    <div class="mt-2 bg-gray-100 p-2 rounded text-gray-800 font-mono text-xs break-all max-h-32 overflow-y-auto">
                                        ${escapeHtml(file.content_preview)}
                                        ${file.content_length > 200 ? '...' : ''}
                                    </div>
                                </details>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
            <div class="mt-3 text-sm text-yellow-700">
                <i class="fas fa-lightbulb mr-1"></i>
                <strong>Recommendation:</strong> Use the "Refresh License" button above to clear these files safely.
            </div>
        </div>
    `;
    
    statusDiv.innerHTML = html;
}

/**
 * Remove license key files using AJAX
 * This is the advanced/manual removal method
 */
function removeLicenseFiles() {
    // Confirm before proceeding
    if (!confirm('Are you sure you want to remove license key files? This will clear all cached license data and may require re-validation.')) {
        return;
    }
    
    const statusDiv = document.getElementById('key-files-status');
    
    // Show loading state
    statusDiv.innerHTML = `
        <div class="bg-blue-50 border border-blue-200 rounded p-3">
            <div class="flex items-center">
                <i class="fas fa-spinner fa-spin text-blue-600 mr-2"></i>
                <span class="text-blue-800">Removing license key files...</span>
            </div>
        </div>
    `;
    
    // Send AJAX request to remove key files
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'ajax_action=remove_key_files'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayRemovalResults(data.results);
        } else {
            showErrorStatus(`Error removing files: ${data.message}`);
        }
    })
    .catch(error => {
        console.error('Removal Error:', error);
        showErrorStatus('Error removing files. Please try again.');
    });
}

/**
 * Display the results of file removal operation
 * @param {Array} results - Array of removal results from server
 */
function displayRemovalResults(results) {
    const statusDiv = document.getElementById('key-files-status');
    
    let html = `
        <div class="bg-green-50 border border-green-200 rounded p-3">
            <h4 class="text-sm font-medium text-green-800 mb-3">
                <i class="fas fa-check-circle mr-1"></i>License key files removal completed:
            </h4>
            <div class="space-y-2">
    `;
    
    results.forEach(result => {
        const color = result.status === 'removed' ? 'green' : 
                     result.status === 'not_found' ? 'blue' : 'red';
        const icon = result.status === 'removed' ? 'fa-check-circle' : 
                    result.status === 'not_found' ? 'fa-info-circle' : 'fa-exclamation-triangle';
        
        html += `
            <div class="flex items-start space-x-2">
                <i class="fas ${icon} text-${color}-600 mt-0.5"></i>
                <div>
                    <div class="text-sm text-${color}-800 font-medium">${result.file}</div>
                    <div class="text-xs text-${color}-700">${result.message}</div>
                    ${result.path ? `<div class="text-xs text-gray-600">Path: ${result.path}</div>` : ''}
                </div>
            </div>
        `;
    });
    
    html += `
            </div>
            <div class="mt-4 p-3 bg-green-100 rounded">
                <div class="text-sm text-green-800">
                    <i class="fas fa-lightbulb mr-1"></i>
                    <strong>Next step:</strong> Reload your site to re-check with the license server.
                </div>
                <div class="mt-2">
                    <a href="${window.location.origin}" target="_blank" class="inline-flex items-center text-sm text-green-700 hover:text-green-900 underline">
                        <i class="fas fa-external-link-alt mr-1"></i>
                        Open site in new tab
                    </a>
                </div>
            </div>
        </div>
    `;
    
    statusDiv.innerHTML = html;
}

/**
 * Show error status message
 * @param {string} message - Error message to display
 */
function showErrorStatus(message) {
    const statusDiv = document.getElementById('key-files-status');
    statusDiv.innerHTML = `
        <div class="bg-red-50 border border-red-200 rounded p-3">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                <span class="text-red-800">${message}</span>
            </div>
        </div>
    `;
}

/**
 * Format file size from bytes to human readable format
 * @param {number} bytes - File size in bytes
 * @returns {string} - Formatted file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Escape HTML characters to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} - Escaped text
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Auto-scan for files when the license tab is opened
 */
function onLicenseTabOpened() {
    // Check if we should auto-scan
    const statusDiv = document.getElementById('key-files-status');
    if (statusDiv && statusDiv.innerHTML.trim() === '') {
        // Only auto-scan if no previous results
        setTimeout(() => {
            scanKeyFiles();
        }, 500); // Small delay to let the tab animation complete
    }
}

/**
 * Enhanced license refresh form submission
 * Adds confirmation and loading state to the refresh form
 */
function enhanceLicenseRefreshForm() {
    const refreshForm = document.querySelector('form input[name="refresh_license"]');
    if (refreshForm) {
        const form = refreshForm.closest('form');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (form && submitBtn) {
            form.addEventListener('submit', function(e) {
                // Confirm before proceeding
                if (!confirm('Are you sure you want to refresh the license? This will clear all cached license data and force a re-check with the server.')) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Refreshing...';
                submitBtn.disabled = true;
                
                // Re-enable after a timeout (form will redirect anyway)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
            });
        }
    }
}

/**
 * Clear status display
 * Utility function to clear the status area
 */
function clearLicenseStatus() {
    const statusDiv = document.getElementById('key-files-status');
    if (statusDiv) {
        statusDiv.innerHTML = '';
    }
}

/**
 * Initialize license tab functionality
 * Call this when the DOM is loaded or when the license tab is first opened
 */
function initializeLicenseTab() {
    // Enhance the refresh form
    enhanceLicenseRefreshForm();
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+Shift+L to scan files (when license tab is active)
        if (e.ctrlKey && e.shiftKey && e.key === 'L') {
            const licenseTab = document.getElementById('license-tab');
            if (licenseTab && licenseTab.classList.contains('active')) {
                e.preventDefault();
                scanKeyFiles();
            }
        }
        
        // Ctrl+Shift+R to refresh license (when license tab is active)
        if (e.ctrlKey && e.shiftKey && e.key === 'R') {
            const licenseTab = document.getElementById('license-tab');
            if (licenseTab && licenseTab.classList.contains('active')) {
                e.preventDefault();
                const refreshBtn = document.querySelector('input[name="refresh_license"]').closest('form').querySelector('button[type="submit"]');
                if (refreshBtn) {
                    refreshBtn.click();
                }
            }
        }
    });
    
    // Add tooltips for better UX
    addLicenseTooltips();
}

/**
 * Add helpful tooltips to license tab elements
 */
function addLicenseTooltips() {
    // Add tooltips to scan button
    const scanBtn = document.querySelector('button[onclick="scanKeyFiles()"]');
    if (scanBtn) {
        scanBtn.title = 'Scan for .localkey and .suspendedkey files (Ctrl+Shift+L)';
    }
    
    // Add tooltips to refresh button
    const refreshBtn = document.querySelector('input[name="refresh_license"]');
    if (refreshBtn) {
        const btn = refreshBtn.closest('form').querySelector('button[type="submit"]');
        if (btn) {
            btn.title = 'Clear license cache and force re-validation (Ctrl+Shift+R)';
        }
    }
    
    // Add tooltips to remove button
    const removeBtn = document.querySelector('button[onclick="removeLicenseFiles()"]');
    if (removeBtn) {
        removeBtn.title = 'Manually remove license files via AJAX';
    }
}

/**
 * Monitor license tab visibility and auto-scan when opened
 * Integrates with your existing tab switching system
 */
function monitorLicenseTab() {
    // Monitor for tab switches to license tab
    const licenseTabButton = document.querySelector('[data-tab="license"]');
    if (licenseTabButton) {
        licenseTabButton.addEventListener('click', function() {
            setTimeout(onLicenseTabOpened, 300);
        });
    }
    
    // Also check if license tab is initially active
    const licenseTab = document.getElementById('license-tab');
    if (licenseTab && licenseTab.classList.contains('active')) {
        onLicenseTabOpened();
    }
}

// =============================================
// INITIALIZATION
// =============================================

/**
 * Initialize all license tab functionality when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function() {
    initializeLicenseTab();
    monitorLicenseTab();
});

/**
 * Re-initialize if called externally (useful for dynamic content)
 */
window.initializeLicenseTab = initializeLicenseTab;
window.scanKeyFiles = scanKeyFiles;
window.removeLicenseFiles = removeLicenseFiles;
window.clearLicenseStatus = clearLicenseStatus;

function toggleLicenseEdit() {
    const input = document.getElementById('license-key-input');
    const editBtn = document.getElementById('edit-license-btn');
    const saveBtn = document.getElementById('save-license-btn');
    const cancelBtn = document.getElementById('cancel-license-btn');
    const checkmark = document.getElementById('license-checkmark');
    const editModeInput = document.getElementById('license-edit-mode');
    
    // Store original value for cancel functionality
    if (!input.dataset.originalValue) {
        input.dataset.originalValue = input.value;
    }
    
    // Enable editing
    input.removeAttribute('readonly');
    input.focus();
    input.select();
    
    // Update button visibility
    editBtn.classList.add('hidden');
    saveBtn.classList.remove('hidden');
    cancelBtn.classList.remove('hidden');
    
    // Hide checkmark during editing
    if (checkmark) {
        checkmark.classList.add('hidden');
    }
    
    // Set edit mode
    editModeInput.value = '1';
    
    // Add visual feedback
    input.classList.add('border-blue-500', 'ring-2', 'ring-blue-200');
    input.classList.remove('border-gray-300');
    
    // Add event listeners (remove old ones first to prevent duplicates)
    input.removeEventListener('input', formatLicenseKeyInput);
    input.removeEventListener('keydown', handleLicenseKeyKeydown);
    input.addEventListener('input', formatLicenseKeyInput);
    input.addEventListener('keydown', handleLicenseKeyKeydown);
    
    // Enable save button by default (no validation)
    saveBtn.disabled = false;
    
    // Remove the maxlength attribute to allow any length
    input.removeAttribute('maxlength');
}
function cancelLicenseEdit() {
    const input = document.getElementById('license-key-input');
    const editBtn = document.getElementById('edit-license-btn');
    const saveBtn = document.getElementById('save-license-btn');
    const cancelBtn = document.getElementById('cancel-license-btn');
    const checkmark = document.getElementById('license-checkmark');
    const editModeInput = document.getElementById('license-edit-mode');
    
    // Restore original value
    if (input.dataset.originalValue) {
        input.value = input.dataset.originalValue;
        delete input.dataset.originalValue;
    }
    
    // Disable editing
    input.setAttribute('readonly', 'readonly');
    
    // Update button visibility
    editBtn.classList.remove('hidden');
    saveBtn.classList.add('hidden');
    cancelBtn.classList.add('hidden');
    
    // Show checkmark if there was a license key
    if (checkmark && input.value.trim()) {
        checkmark.classList.remove('hidden');
    }
    
    // Reset edit mode
    editModeInput.value = '0';
    
    // Remove visual feedback
    input.classList.remove('border-blue-500', 'ring-2', 'ring-blue-200');
    input.classList.add('border-gray-300');
    
    // Remove event listeners
    input.removeEventListener('input', formatLicenseKeyInput);
    input.removeEventListener('keydown', handleLicenseKeyKeydown);
}

function formatLicenseKeyInput(event) {
    const input = event.target;
    let value = input.value;
    
    // Only remove control characters, allow all other characters including letters, numbers, hyphens, etc.
    value = value.replace(/[\x00-\x1F\x7F]/g, '');
    
    input.value = value;
    // No automatic formatting or validation
}


// function validateLicenseKeyFormatClient(licenseKey) {
//     const statusDiv = document.getElementById('license-validation-status');
//     const saveBtn = document.getElementById('save-license-btn');
    
//     if (!licenseKey) {
//         statusDiv.innerHTML = '';
//         saveBtn.disabled = true;
//         return;
//     }
    
//     // Check format: Owned-[alphanumeric string]
//     const isValidFormat = /^Owned-[a-zA-Z0-9]+$/.test(licenseKey);
//     const isValidLength = licenseKey.length >= 16 && licenseKey.length <= 50;
    
//     if (isValidFormat && isValidLength) {
//         statusDiv.innerHTML = `
//             <div class="bg-green-50 border border-green-200 rounded px-3 py-2">
//                 <span class="text-green-700 text-sm">
//                     <i class="fas fa-check-circle mr-1"></i>Valid license key format
//                 </span>
//             </div>
//         `;
//         saveBtn.disabled = false;
//     } else {
//         let errorMessage = 'Invalid format. Expected: Owned-[alphanumeric string]';
//         if (!isValidLength) {
//             errorMessage = 'License key length must be between 16 and 50 characters';
//         }
        
//         statusDiv.innerHTML = `
//             <div class="bg-red-50 border border-red-200 rounded px-3 py-2">
//                 <span class="text-red-700 text-sm">
//                     <i class="fas fa-exclamation-triangle mr-1"></i>${errorMessage}
//                 </span>
//             </div>
//         `;
//         saveBtn.disabled = true;
//     }
// }

function handleLicenseKeyKeydown(event) {
    const input = event.target;
    
    // Handle Enter key to save
    if (event.key === 'Enter') {
        event.preventDefault();
        const saveBtn = document.getElementById('save-license-btn');
        if (saveBtn && !saveBtn.disabled) {
            saveBtn.click();
        }
    }
    
    // Handle Escape key to cancel
    if (event.key === 'Escape') {
        event.preventDefault();
        cancelLicenseEdit();
    }
}

// function validateLicenseKeyFormat(licenseKey) {
//     const statusDiv = document.getElementById('license-validation-status');
//     const saveBtn = document.getElementById('save-license-btn');
    
//     if (!licenseKey) {
//         statusDiv.innerHTML = '';
//         saveBtn.disabled = true;
//         return;
//     }
    
//     // Check format
//     const isValidFormat = /^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/.test(licenseKey);
    
//     if (isValidFormat) {
//         statusDiv.innerHTML = `
//             <div class="bg-green-50 border border-green-200 rounded px-3 py-2">
//                 <span class="text-green-700 text-sm">
//                     <i class="fas fa-check-circle mr-1"></i>Valid license key format
//                 </span>
//             </div>
//         `;
//         saveBtn.disabled = false;
//     } else {
//         statusDiv.innerHTML = `
//             <div class="bg-red-50 border border-red-200 rounded px-3 py-2">
//                 <span class="text-red-700 text-sm">
//                     <i class="fas fa-exclamation-triangle mr-1"></i>Invalid format. Expected: XXXX-XXXX-XXXX-XXXX
//                 </span>
//             </div>
//         `;
//         saveBtn.disabled = true;
//     }
// }

function initializeLicenseKeyForm() {
    const form = document.getElementById('license-key-form');
    const input = document.getElementById('license-key-input');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // Add hidden input to ensure we stay on license tab
            let tabInput = form.querySelector('input[name="current_tab"]');
            if (!tabInput) {
                tabInput = document.createElement('input');
                tabInput.type = 'hidden';
                tabInput.name = 'current_tab';
                tabInput.value = 'license';
                form.appendChild(tabInput);
            }
            
            // Show loading state
            const saveBtn = document.getElementById('save-license-btn');
            if (saveBtn) {
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
                saveBtn.disabled = true;
                
                // Show progress message
                showLicenseStatus('Saving to config.php...', 'info');
                
                // Re-enable after timeout (form will redirect anyway)
                setTimeout(() => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }, 5000);
            }
        });
    }
    
    // Initialize event listeners for any license key input
    if (input && !input.hasAttribute('readonly')) {
        input.addEventListener('input', handleLicenseKeyInput);
        input.addEventListener('keydown', handleLicenseKeyKeydown);
        
        // Always enable save button
        const saveBtn = document.getElementById('save-license-btn');
        if (saveBtn) {
            saveBtn.disabled = false;
        }
    }
}

function showLicenseKeyMessage(message, type) {
    const statusDiv = document.getElementById('license-validation-status');
    
    let bgColor, textColor, icon;
    
    switch(type) {
        case 'success':
            bgColor = 'bg-green-50';
            textColor = 'text-green-700';
            icon = 'fa-check-circle';
            break;
        case 'error':
            bgColor = 'bg-red-50';
            textColor = 'text-red-700';
            icon = 'fa-exclamation-triangle';
            break;
        case 'info':
            bgColor = 'bg-blue-50';
            textColor = 'text-blue-700';
            icon = 'fa-info-circle';
            break;
        default:
            bgColor = 'bg-gray-50';
            textColor = 'text-gray-700';
            icon = 'fa-info-circle';
    }
    
    statusDiv.innerHTML = `
        <div class="${bgColor} border border-${type === 'success' ? 'green' : (type === 'error' ? 'red' : 'blue')}-200 rounded px-3 py-2">
            <span class="${textColor} text-sm">
                <i class="fas ${icon} mr-1"></i>${message}
            </span>
        </div>
    `;
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        statusDiv.innerHTML = '';
    }, 5000);
}
function toggleLicenseEdit() {
    const input = document.getElementById('license-key-input');
    const editBtn = document.getElementById('edit-license-btn');
    const saveBtn = document.getElementById('save-license-btn');
    const cancelBtn = document.getElementById('cancel-license-btn');
    const checkmark = document.getElementById('license-checkmark');
    const editModeInput = document.getElementById('license-edit-mode');
    
    // Store original value for cancel functionality
    if (!input.dataset.originalValue) {
        input.dataset.originalValue = input.value;
    }
    
    // Enable editing
    input.removeAttribute('readonly');
    input.focus();
    input.select();
    
    // Update button visibility
    editBtn.classList.add('hidden');
    saveBtn.classList.remove('hidden');
    cancelBtn.classList.remove('hidden');
    
    // Hide checkmark during editing
    if (checkmark) {
        checkmark.classList.add('hidden');
    }
    
    // Set edit mode
    if (editModeInput) {
        editModeInput.value = '1';
    }
    
    // Add visual feedback
    input.classList.add('border-blue-500', 'ring-2', 'ring-blue-200');
    input.classList.remove('border-gray-300');
    
    // Add event listeners - NO FORMATTING
    input.removeEventListener('input', handleLicenseKeyInput);
    input.removeEventListener('keydown', handleLicenseKeyKeydown);
    input.addEventListener('input', handleLicenseKeyInput);
    input.addEventListener('keydown', handleLicenseKeyKeydown);
    
    // Always enable save button - no validation
    saveBtn.disabled = false;
    
    // Remove any restrictions
    input.removeAttribute('maxlength');
    input.removeAttribute('pattern');
}

function cancelLicenseEdit() {
    const input = document.getElementById('license-key-input');
    const editBtn = document.getElementById('edit-license-btn');
    const saveBtn = document.getElementById('save-license-btn');
    const cancelBtn = document.getElementById('cancel-license-btn');
    const checkmark = document.getElementById('license-checkmark');
    const editModeInput = document.getElementById('license-edit-mode');
    
    // Restore original value
    if (input.dataset.originalValue !== undefined) {
        input.value = input.dataset.originalValue;
        delete input.dataset.originalValue;
    }
    
    // Disable editing
    input.setAttribute('readonly', 'readonly');
    
    // Update button visibility
    editBtn.classList.remove('hidden');
    saveBtn.classList.add('hidden');
    cancelBtn.classList.add('hidden');
    
    // Show checkmark if there was a license key
    if (checkmark && input.value) {
        checkmark.classList.remove('hidden');
    }
    
    // Reset edit mode
    if (editModeInput) {
        editModeInput.value = '0';
    }
    
    // Remove visual feedback
    input.classList.remove('border-blue-500', 'ring-2', 'ring-blue-200');
    input.classList.add('border-gray-300');
    
    // Remove event listeners
    input.removeEventListener('input', handleLicenseKeyInput);
    input.removeEventListener('keydown', handleLicenseKeyKeydown);
    
    // Clear any status messages
    clearLicenseStatus();
}

// UPDATED: No formatting, no validation
function handleLicenseKeyInput(event) {
    const saveBtn = document.getElementById('save-license-btn');
    
    // Always enable save button - no validation
    if (saveBtn) {
        saveBtn.disabled = false;
    }
    
    // Show ready status
    showLicenseStatus('Ready to save', 'info');
}

function handleLicenseKeyKeydown(event) {
    // Handle Enter key to save
    if (event.key === 'Enter') {
        event.preventDefault();
        const saveBtn = document.getElementById('save-license-btn');
        if (saveBtn && !saveBtn.disabled) {
            saveBtn.click();
        }
    }
    
    // Handle Escape key to cancel
    if (event.key === 'Escape') {
        event.preventDefault();
        cancelLicenseEdit();
    }
}

function showLicenseStatus(message, type = 'info') {
    const statusDiv = document.getElementById('license-validation-status');
    if (!statusDiv) return;
    
    let bgColor, textColor, icon;
    
    switch(type) {
        case 'success':
            bgColor = 'bg-green-50 border-green-200';
            textColor = 'text-green-700';
            icon = 'fa-check-circle';
            break;
        case 'error':
            bgColor = 'bg-red-50 border-red-200';
            textColor = 'text-red-700';
            icon = 'fa-exclamation-triangle';
            break;
        case 'info':
            bgColor = 'bg-blue-50 border-blue-200';
            textColor = 'text-blue-700';
            icon = 'fa-info-circle';
            break;
        default:
            bgColor = 'bg-gray-50 border-gray-200';
            textColor = 'text-gray-700';
            icon = 'fa-info-circle';
    }
    
    statusDiv.innerHTML = `
        <div class="${bgColor} border rounded px-3 py-2">
            <span class="${textColor} text-sm">
                <i class="fas ${icon} mr-1"></i>${message}
            </span>
        </div>
    `;
}

function clearLicenseStatus() {
    const statusDiv = document.getElementById('license-validation-status');
    if (statusDiv) {
        statusDiv.innerHTML = '';
    }
}

function initializeLicenseKeyForm() {
    const form = document.getElementById('license-key-form');
    const input = document.getElementById('license-key-input');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            // NO VALIDATION - allow any value including empty
            
            // Show loading state
            const saveBtn = document.getElementById('save-license-btn');
            if (saveBtn) {
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
                saveBtn.disabled = true;
                
                // Show progress message
                showLicenseStatus('Saving to config.php...', 'info');
                
                // Re-enable after timeout (form will redirect anyway)
                setTimeout(() => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }, 5000);
            }
        });
    }
    
    // Initialize event listeners for any license key input
    if (input && !input.hasAttribute('readonly')) {
        input.addEventListener('input', handleLicenseKeyInput);
        input.addEventListener('keydown', handleLicenseKeyKeydown);
        
        // Always enable save button
        const saveBtn = document.getElementById('save-license-btn');
        if (saveBtn) {
            saveBtn.disabled = false;
        }
    }
}

// REMOVED ALL THESE FORMATTING FUNCTIONS:
// - No more automatic XXXX-XXXX-XXXX-XXXX formatting
// - No length restrictions
// - No character filtering
// - No pattern validation
// - No format requirements

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeLicenseKeyForm();
});

// Re-initialize when license tab becomes active
function onLicenseTabActivated() {
    setTimeout(() => {
        initializeLicenseKeyForm();
    }, 100);
}

// Add this JavaScript function for better UX
function confirmLicenseRefresh() {
    const confirmed = confirm('Are you sure you want to refresh the license?\n\nThis will:\n• Remove .localkey file (cached license data)\n• Remove .suspendedkey file (suspension flag)\n• Force fresh validation with license server\n\nProceed?');
    
    if (confirmed) {
        // Show loading state
        const button = event.target.querySelector('button[type="submit"]') || event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Refreshing License...';
        button.disabled = true;
        
        // Add visual feedback
        const form = button.closest('form');
        if (form) {
            form.style.opacity = '0.7';
        }
        
        // Re-enable after timeout (form will redirect anyway)
        setTimeout(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            if (form) {
                form.style.opacity = '1';
            }
        }, 5000);
    }
    
    return confirmed;
}

// Enhanced error handling for license refresh
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on license tab after a refresh
    const urlParams = new URLSearchParams(window.location.search);
    const currentTab = '<?= $activeTab ?>';
    
    if (currentTab === 'license') {
        // Ensure we're showing the license tab
        switchTab('license');
        
        // Show success message if present
        <?php if (isset($success) && strpos($success, 'License cache cleared') !== false): ?>
            // Auto-scan for files after successful refresh
            setTimeout(() => {
                scanKeyFiles();
            }, 1000);
        <?php endif; ?>
    }
});

    </script>
</body>
</html>