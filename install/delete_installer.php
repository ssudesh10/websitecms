<?php
// File: install/delete_installer.php
// This file deletes the entire installer directory immediately

// Function to recursively delete directory and all contents
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($filePath)) {
            deleteDirectory($filePath);
        } else {
            unlink($filePath);
        }
    }
    
    return rmdir($dir);
}

$installer_path = __DIR__; // Current directory (install/)
$success = false;
$error_message = '';

try {
    // Attempt to delete the installer directory
    if (deleteDirectory($installer_path)) {
        $success = true;
        // Redirect to admin panel or main site after successful deletion
        header('Location: ../admin/?msg=installer_deleted');
        exit();
    } else {
        $error_message = 'Failed to delete installer directory. Please check file permissions.';
    }
} catch (Exception $e) {
    $error_message = 'Error: ' . $e->getMessage();
}

// If we get here, deletion failed
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installer Deletion Failed</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            max-width: 600px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f3f4f6;
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); 
        }
        .error { 
            color: #dc2626; 
            padding: 20px; 
            background: #fee2e2; 
            border: 2px solid #fecaca;
            border-radius: 10px; 
            margin: 20px 0;
            text-align: center;
        }
        .btn { 
            background: #2563eb; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px; 
            text-decoration: none; 
            display: inline-block; 
            margin: 10px;
        }
        .manual-steps {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>❌ Deletion Failed</h1>
        
        <div class="error">
            <h3>Could not delete installer automatically</h3>
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>

        <div class="manual-steps">
            <h3>Manual Deletion Required</h3>
            <p>Please manually delete the <code>install/</code> folder using:</p>
            <ul>
                <li>FTP client (FileZilla, etc.)</li>
                <li>cPanel File Manager</li>
                <li>SSH command: <code>rm -rf install/</code></li>
            </ul>
        </div>

        <div style="text-align: center;">
            <a href="../admin/" class="btn">Go to Admin Panel</a>
            <a href="../" class="btn">View Website</a>
        </div>
    </div>
</body>
</html>