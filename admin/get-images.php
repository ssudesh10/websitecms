<?php
require_once '../config.php';
require_once '../function.php';
require_once '../upload-functions.php';
requireLogin();

header('Content-Type: application/json');

try {
    $images = getUploadedImages();
    echo json_encode([
        'success' => true,
        'images' => $images
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>