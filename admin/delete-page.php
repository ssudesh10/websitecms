<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

$pageId = $_GET['id'] ?? 0;

// Get page data
$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ? AND is_default = 0");
$stmt->execute([$pageId]);
$page = $stmt->fetch();

// Only allow deletion of non-default pages
if ($page) {
    try {
        $pdo->beginTransaction();
        
        // Delete all sections first (foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM page_sections WHERE page_id = ?");
        $stmt->execute([$pageId]);
        
        // Delete the page
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = ?");
        $stmt->execute([$pageId]);
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        // Handle error silently and redirect
    }
}

redirect(BASE_URL . '/admin/pages.php');
?>