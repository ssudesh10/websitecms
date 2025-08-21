<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

$sectionId = $_GET['id'] ?? 0;

// Get section data to find the page
$stmt = $pdo->prepare("SELECT page_id FROM page_sections WHERE id = ?");
$stmt->execute([$sectionId]);
$section = $stmt->fetch();

if (!$section) {
    redirect(BASE_URL . '/admin/pages.php');
}

$pageId = $section['page_id'];

try {
    // Delete the section
    $stmt = $pdo->prepare("DELETE FROM page_sections WHERE id = ?");
    $stmt->execute([$sectionId]);
    
    // Reorder remaining sections
    $stmt = $pdo->prepare("SELECT id FROM page_sections WHERE page_id = ? ORDER BY section_order ASC");
    $stmt->execute([$pageId]);
    $sections = $stmt->fetchAll();
    
    $order = 1;
    foreach ($sections as $sec) {
        $stmt = $pdo->prepare("UPDATE page_sections SET section_order = ? WHERE id = ?");
        $stmt->execute([$order, $sec['id']]);
        $order++;
    }
    
} catch (Exception $e) {
    // Handle error silently and redirect
}

redirect(BASE_URL . '/admin/edit-page.php?id=' . $pageId);
?>