<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$imageId = $_GET['id'] ?? 0;

if (!$imageId) {
    echo json_encode(['success' => false, 'error' => 'No image ID provided']);
    exit;
}

try {
    // Get image data first
    $stmt = $pdo->prepare("SELECT * FROM uploaded_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch();
    
    if (!$image) {
        echo json_encode(['success' => false, 'error' => 'Image not found']);
        exit;
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM uploaded_images WHERE id = ?");
    $stmt->execute([$imageId]);
    
    // Delete physical file
    $filePath = UPLOAD_DIR . $image['filename'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>