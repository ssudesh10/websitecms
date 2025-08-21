<?php
require_once '../config.php';
require_once '../function.php';
require_once '../upload-functions.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'No image file provided']);
    exit;
}

$result = uploadImage($_FILES['image']);
echo json_encode($result);
?>