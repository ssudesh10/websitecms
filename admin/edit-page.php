<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

$pageId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Get page data
$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$pageId]);
$page = $stmt->fetch();

if (!$page) {
    redirect(BASE_URL . '/admin/pages.php');
}

// Handle AJAX requests for reordering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reorder') {
    header('Content-Type: application/json');
    
    try {
        $sectionIds = $_POST['section_ids'] ?? [];
        $sortOrder = 10; // Start from 10
        
        foreach ($sectionIds as $sectionId) {
            $stmt = $pdo->prepare("UPDATE page_sections SET sort_order = ? WHERE id = ? AND page_id = ?");
            $stmt->execute([$sortOrder, $sectionId, $pageId]);
            $sortOrder += 10; // Increment by 10 for each section
        }
        
        echo json_encode(['success' => true, 'message' => 'Sections reordered successfully']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error reordering sections: ' . $e->getMessage()]);
        exit;
    }
}

// Handle regular form submissions for page updates
if ($_POST && !isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    
    if (empty($title)) {
        $error = 'Page title is required';
    } elseif (empty($slug)) {
        $error = 'Page slug is required';
    } else {
        // Check if slug already exists (excluding current page)
        $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $pageId]);
        if ($stmt->fetch()) {
            $error = 'A page with this slug already exists';
        } else {
            try {
                // Update page
                $stmt = $pdo->prepare("UPDATE pages SET title = ?, slug = ?, meta_description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$title, $slug, $metaDescription, $pageId]);
                
                $success = 'Page updated successfully!';
                
                // Refresh page data
                $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
                $stmt->execute([$pageId]);
                $page = $stmt->fetch();
                
            } catch (Exception $e) {
                $error = 'Error updating page: ' . $e->getMessage();
            }
        }
    }
}

// Get page sections ordered by sort_order
$stmt = $pdo->prepare("SELECT * FROM page_sections WHERE page_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$pageId]);
$sections = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Page: <?= htmlspecialchars($page['title']) ?> - Admin</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Add SortableJS for drag and drop -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="pages.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Pages
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Edit: <?= htmlspecialchars($page['title']) ?></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="<?= formatUrl($page['slug']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-external-link-alt mr-1"></i>View Page
                    </a>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Page Settings -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Page Settings</h2>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Page Title *</label>
                            <input type="text" id="title" name="title" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                   value="<?= htmlspecialchars($page['title']) ?>">
                        </div>
                        
                        <div>
                            <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">Page Slug *</label>
                            <input type="text" id="slug" name="slug" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                   value="<?= htmlspecialchars($page['slug']) ?>"
                                   <?= $page['is_default'] ? 'readonly' : '' ?>>
                            <?php if ($page['is_default']): ?>
                                <p class="text-sm text-gray-500 mt-1">Default pages cannot change their slug.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- <div>
                            <label for="meta_description" class="block text-sm font-medium text-gray-700 mb-2">Meta Description</label>
                            <textarea id="meta_description" name="meta_description" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($page['meta_description']) ?></textarea>
                        </div> -->
                        
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-save mr-2"></i>Update Page
                        </button>
                    </form>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow mt-6">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <a href="add-section.php?page_id=<?= $page['id'] ?>" 
                           class="w-full bg-green-600 text-white py-2 px-4 rounded hover:bg-green-700 transition duration-200 text-center block">
                            <i class="fas fa-plus mr-2"></i>Add Section
                        </a>
                        
                        <?php if (!$page['is_default']): ?>
                            <a href="delete-page.php?id=<?= $page['id'] ?>" 
                               class="w-full bg-red-600 text-white py-2 px-4 rounded hover:bg-red-700 transition duration-200 text-center block"
                               onclick="return confirm('Are you sure you want to delete this page?')">
                                <i class="fas fa-trash mr-2"></i>Delete Page
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Page Sections -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Page Sections</h2>
                                <p class="text-gray-600 mt-1">Drag and drop to reorder sections.</p>
                            </div>
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-arrows-alt mr-1"></i>Drag to reorder
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($sections)): ?>
                        <div class="p-6 text-center">
                            <i class="fas fa-puzzle-piece text-gray-400 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No sections yet</h3>
                            <p class="text-gray-600 mb-4">Start building your page by adding sections.</p>
                            <a href="add-section.php?page_id=<?= $page['id'] ?>" 
                               class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-200">
                                <i class="fas fa-plus mr-2"></i>Add First Section
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Sortable sections container -->
                        <div id="sections-container" class="divide-y divide-gray-200">
                            <?php foreach ($sections as $index => $section): ?>
                                <div class="section-item p-6 hover:bg-gray-50 transition duration-200 cursor-move" 
                                     data-id="<?= $section['id'] ?>" 
                                     data-sort-order="<?= $section['sort_order'] ?>">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <!-- Drag handle -->
                                            <div class="drag-handle text-gray-400 hover:text-gray-600 cursor-grab active:cursor-grabbing">
                                                <i class="fas fa-grip-vertical"></i>
                                            </div>
                                            
                                            <div class="flex-shrink-0">
                                                <?php
                                                $icons = [
                                                    'hero' => 'fas fa-star text-blue-600',
                                                    'content' => 'fas fa-align-left text-green-600',
                                                    'features' => 'fas fa-th-large text-purple-600',
                                                    'gallery' => 'fas fa-images text-orange-600',
                                                    'contact_form' => 'fas fa-envelope text-red-600',
                                                    'custom' => 'fas fa-code text-gray-600',
                                                    'stats' => 'fas fa-chart-line text-indigo-600',
                                                    'testimonials' => 'fas fa-quote-left text-yellow-600',
                                                    'pricing' => 'fas fa-tags text-emerald-600',
                                                    'team' => 'fas fa-users text-cyan-600',
                                                    'slider' => 'fas fa-images text-pink-600',
                                                    'video' => 'fas fa-video text-rose-600',
                                                    'timeline' => 'fas fa-timeline text-violet-600',
                                                    'faq' => 'fas fa-question-circle text-amber-600',
                                                    'newsletter' => 'fas fa-envelope-open text-teal-600',
                                                    'cta' => 'fas fa-bullhorn text-red-600',
                                                    'projects' => 'fas fa-briefcase text-blue-600',
                                                    'textwithimage' => 'fas fa-image text-pink-600'
                                                ];
                                                $iconClass = $icons[$section['section_type']] ?? 'fas fa-puzzle-piece text-gray-600';
                                                ?>
                                                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <i class="<?= $iconClass ?>"></i>
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <h3 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($section['title']) ?></h3>
                                                <p class="text-sm text-gray-500">
                                                    <?= ucfirst(str_replace('_', ' ', $section['section_type'])) ?> Section
                                                    <?php if ($section['content']): ?>
                                                        • <?= strlen($section['content']) ?> characters
                                                    <?php endif; ?>
                                                    • <span class="sort-order-text">Sort: <?= $section['sort_order'] ?></span>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <a href="edit-section.php?id=<?= $section['id'] ?>" 
                                               class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition duration-200">
                                                <i class="fas fa-edit mr-1"></i>Edit
                                            </a>
                                            <a href="delete-section.php?id=<?= $section['id'] ?>" 
                                               class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700 transition duration-200"
                                               onclick="return confirm('Are you sure you want to delete this section?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <!-- Section content preview -->
                                    <?php if ($section['content'] && $section['section_type'] !== 'contact_form'): ?>
                                        <div class="mt-4 p-4 bg-gray-50 rounded text-sm text-gray-600">
                                            <?php if ($section['section_type'] === 'features'): ?>
                                                <strong>Features:</strong> <?= str_replace('|', ', ', htmlspecialchars(substr($section['content'], 0, 150))) ?><?= strlen($section['content']) > 150 ? '...' : '' ?>
                                            <?php elseif ($section['section_type'] === 'custom'): ?>
                                                <div class="font-mono text-xs"><?= htmlspecialchars(substr($section['content'], 0, 200)) ?><?= strlen($section['content']) > 200 ? '...' : '' ?></div>
                                            <?php else: ?>
                                                <?= htmlspecialchars(substr($section['content'], 0, 200)) ?><?= strlen($section['content']) > 200 ? '...' : '' ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="p-6 border-t border-gray-200">
                            <a href="add-section.php?page_id=<?= $page['id'] ?>" 
                               class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-200">
                                <i class="fas fa-plus mr-2"></i>Add Another Section
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Initialize drag and drop functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sectionsContainer = document.getElementById('sections-container');
        
        if (sectionsContainer) {
            const sortable = Sortable.create(sectionsContainer, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onStart: function(evt) {
                    // Add visual feedback when dragging starts
                    evt.item.style.opacity = '0.7';
                },
                onEnd: function(evt) {
                    // Reset opacity
                    evt.item.style.opacity = '1';
                    
                    // Get the new order of section IDs
                    const sectionIds = [];
                    sectionsContainer.querySelectorAll('.section-item').forEach(function(item) {
                        sectionIds.push(item.getAttribute('data-id'));
                    });
                    
                    // Send AJAX request to update the order
                    updateSectionOrder(sectionIds);
                }
            });
        }
    });

    function updateSectionOrder(sectionIds) {
        const formData = new FormData();
        formData.append('action', 'reorder');
        
        sectionIds.forEach(function(id, index) {
            formData.append('section_ids[]', id);
        });
        
        // Show loading indicator
        showMessage('Reordering sections...', 'info');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showMessage('Sections reordered successfully!', 'success');
                
                // Update the sort order display
                document.querySelectorAll('.section-item').forEach(function(item, index) {
                    const sortOrderText = item.querySelector('.sort-order-text');
                    if (sortOrderText) {
                        const newSortOrder = (index + 1) * 10;
                        sortOrderText.textContent = 'Sort: ' + newSortOrder;
                        item.setAttribute('data-sort-order', newSortOrder);
                    }
                });
            } else {
                showMessage('Error reordering sections: ' + data.message, 'error');
                // Reload the page on error to reset positions
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        })
        .catch(error => {
            showMessage('Network error occurred', 'error');
            console.error('Error:', error);
            // Reload the page on error
            setTimeout(() => {
                location.reload();
            }, 2000);
        });
    }

    function showMessage(message, type) {
        // Remove any existing messages
        const existingMessage = document.querySelector('.temp-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // Create and show a temporary message
        const messageDiv = document.createElement('div');
        messageDiv.className = `temp-message fixed top-4 right-4 px-4 py-3 rounded shadow-lg z-50 ${
            type === 'success' 
                ? 'bg-green-100 border border-green-400 text-green-700' 
                : type === 'error'
                ? 'bg-red-100 border border-red-400 text-red-700'
                : 'bg-blue-100 border border-blue-400 text-blue-700'
        }`;
        
        const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
        messageDiv.innerHTML = `<i class="fas fa-${icon} mr-2"></i>${message}`;
        
        document.body.appendChild(messageDiv);
        
        // Remove after 3 seconds (except for info messages which are removed faster)
        const timeout = type === 'info' ? 1000 : 3000;
        setTimeout(() => {
            if (messageDiv && messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, timeout);
    }
    </script>

    <style>
    .sortable-ghost {
        opacity: 0.4;
        background: #f3f4f6;
        transform: rotate(2deg);
    }
    
    .sortable-chosen {
        background: #f9fafb;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .sortable-drag {
        transform: rotate(5deg);
        z-index: 999;
    }
    
    .drag-handle {
        opacity: 0.6;
        transition: opacity 0.2s ease;
    }
    
    .section-item:hover .drag-handle {
        opacity: 1;
    }
    
    .drag-handle:hover {
        color: #374151;
        cursor: grab;
    }
    
    .drag-handle:active {
        cursor: grabbing;
    }
    
    .section-item {
        position: relative;
        transition: all 0.2s ease;
    }
    
    .section-item:hover {
        background-color: #f9fafb;
        border-left: 4px solid #3b82f6;
        padding-left: 22px;
    }
    
    /* Custom scrollbar for the sections container */
    #sections-container {
        max-height: 70vh;
        overflow-y: auto;
    }
    
    #sections-container::-webkit-scrollbar {
        width: 6px;
    }
    
    #sections-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    #sections-container::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }
    
    #sections-container::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    </style>
</body>
</html>