<?php
// admin/save-page-order.php
require_once '../config.php';
require_once '../function.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    foreach ($input as $item) {
        if (!isset($item['id']) || !isset($item['order'])) {
            throw new Exception('Invalid item data');
        }
        
        $stmt = $pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ?");
        $stmt->execute([$item['order'], $item['id']]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Page order updated successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error updating page order: ' . $e->getMessage()]);
}
?>