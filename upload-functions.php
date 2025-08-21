<?php
// Upload helper functions - include this file if upload functions are missing

// Upload configuration
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/uploads/');
}
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', BASE_URL . '/uploads/');
}
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
}
if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
}

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Upload image function
if (!function_exists('uploadImage')) {
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
                // Check if table exists first
                $stmt = $pdo->query("SHOW TABLES LIKE 'uploaded_images'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $pdo->prepare("INSERT INTO uploaded_images (filename, original_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $newFileName,
                        $fileName,
                        UPLOAD_URL . $newFileName,
                        $fileSize,
                        $file['type'],
                        $_SESSION['admin_id'] ?? null
                    ]);
                }
                
                return [
                    'success' => true,
                    'url' => UPLOAD_URL . $newFileName,
                    'filename' => $newFileName
                ];
            } catch (Exception $e) {
                // If database insert fails, still return success with file URL
                return [
                    'success' => true,
                    'url' => UPLOAD_URL . $newFileName,
                    'filename' => $newFileName
                ];
            }
        }
        
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }
}

// Get uploaded images function
if (!function_exists('getUploadedImages')) {
    function getUploadedImages() {
        global $pdo;
        try {
            // Check if table exists first
            $stmt = $pdo->query("SHOW TABLES LIKE 'uploaded_images'");
            if ($stmt->rowCount() > 0) {
                $stmt = $pdo->query("SELECT * FROM uploaded_images ORDER BY created_at DESC");
                return $stmt->fetchAll();
            }
        } catch (Exception $e) {
            // If table doesn't exist, return empty array
        }
        return [];
    }
}
?>