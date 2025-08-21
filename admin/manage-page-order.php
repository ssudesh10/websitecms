<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

// Temporary function if not in config.php yet
if (!function_exists('getAllPagesAdmin')) {
    function getAllPagesAdmin() {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM pages ORDER BY COALESCE(sort_order, 0) ASC, title ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

$allPages = getAllPagesAdmin();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_pages FROM pages WHERE is_active = 1");
$totalPages = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Page Order - Admin</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .sortable-ghost {
            opacity: 0.4;
        }
        .sortable-drag {
            transform: rotate(2deg);
        }
        .drag-handle {
            cursor: grab;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="index.php" class="text-gray-600 hover:text-gray-900 mr-4">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Manage Page Order</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                    <a href="<?= BASE_URL ?>/" target="_blank" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-external-link-alt mr-1"></i>View Site
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-600"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">How to reorder pages</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Drag and drop pages using the <i class="fas fa-grip-vertical"></i> handle to reorder them. The first 6 pages will appear in the main navigation, and the rest will be in the "More" dropdown menu.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Page Order Management -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Page Navigation Order</h2>
                <p class="text-sm text-gray-600 mt-1">Drag pages to reorder them in the navigation menu</p>
            </div>
            
            <div class="p-6">
                <div id="success-message" class="hidden bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-check-circle mr-2"></i>Page order updated successfully!
                </div>
                
                <div id="error-message" class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    <i class="fas fa-exclamation-circle mr-2"></i>Error updating page order. Please try again.
                </div>

                <!-- Navigation Preview Info -->
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                        <i class="fas fa-bars mr-2 text-blue-600"></i>
                        Main Navigation (First 6 pages)
                    </h3>
                    <div class="bg-gray-50 p-3 rounded mb-3 text-sm text-gray-600">
                        These pages will appear directly in the navigation bar
                    </div>
                </div>

                <!-- Sortable Page List -->
                <div id="sortable-pages" class="space-y-2">
                    <?php foreach ($allPages as $index => $page): ?>
                        <div class="sortable-item bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow" 
                             data-id="<?= $page['id'] ?>" data-order="<?= $page['sort_order'] ?>">
                            <div class="flex items-center">
                                <div class="drag-handle text-gray-400 hover:text-gray-600 mr-4">
                                    <i class="fas fa-grip-vertical text-lg"></i>
                                </div>
                                <div class="flex-shrink-0 h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <?php if ($page['slug'] === 'home'): ?>
                                        <i class="fas fa-home text-blue-600 text-sm"></i>
                                    <?php elseif ($page['slug'] === 'about'): ?>
                                        <i class="fas fa-info-circle text-blue-600 text-sm"></i>
                                    <?php elseif ($page['slug'] === 'services'): ?>
                                        <i class="fas fa-cogs text-blue-600 text-sm"></i>
                                    <?php elseif ($page['slug'] === 'contact'): ?>
                                        <i class="fas fa-envelope text-blue-600 text-sm"></i>
                                    <?php else: ?>
                                        <i class="fas fa-file-alt text-blue-600 text-sm"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900"><?= htmlspecialchars($page['title']) ?></h4>
                                            <p class="text-xs text-gray-500">/<?= htmlspecialchars($page['slug']) ?></p>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <?php if ($page['is_default']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-star mr-1"></i>Default
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <i class="fas fa-plus mr-1"></i>Custom
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!$page['is_active']): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    <i class="fas fa-eye-slash mr-1"></i>Inactive
                                                </span>
                                            <?php endif; ?>
                                            <span class="text-xs text-gray-500">Order: <span class="order-number"><?= $index + 1 ?></span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($index === 5): ?>
                            <!-- Dropdown separator after 6th item -->
                            <div class="my-6 border-t border-gray-300 relative">
                                <div class="absolute left-1/2 top-0 transform -translate-x-1/2 -translate-y-1/2 bg-gray-100 px-4">
                                    <span class="text-sm text-gray-500 flex items-center">
                                        <i class="fas fa-chevron-down mr-2"></i>
                                        More Menu (Remaining pages)
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex justify-between items-center">
                    <button id="reset-order" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50 transition duration-200">
                        <i class="fas fa-undo mr-2"></i>Reset to Alphabetical
                    </button>
                    <button id="save-order" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-save mr-2"></i>Save Order
                    </button>
                </div>
            </div>
        </div>

        <!-- Preview Section -->
        <div class="mt-8 bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Navigation Preview</h2>
                <p class="text-sm text-gray-600 mt-1">This is how your navigation will look</p>
            </div>
            <div class="p-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-lg text-gray-800">Your Website</div>
                        <div class="hidden lg:flex items-center space-x-6" id="nav-preview">
                            <!-- This will be populated by JavaScript -->
                        </div>
                        <div class="text-gray-600 lg:hidden">
                            <i class="fas fa-bars"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortableContainer = document.getElementById('sortable-pages');
            const navPreview = document.getElementById('nav-preview');
            const saveButton = document.getElementById('save-order');
            const resetButton = document.getElementById('reset-order');
            const successMessage = document.getElementById('success-message');
            const errorMessage = document.getElementById('error-message');

            // Initialize Sortable
            const sortable = new Sortable(sortableContainer, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onUpdate: function() {
                    updateOrderNumbers();
                    updateNavPreview();
                }
            });

            // Update order numbers
            function updateOrderNumbers() {
                const items = sortableContainer.querySelectorAll('.sortable-item');
                items.forEach((item, index) => {
                    const orderSpan = item.querySelector('.order-number');
                    if (orderSpan) {
                        orderSpan.textContent = index + 1;
                    }
                });
            }

            // Update navigation preview
            function updateNavPreview() {
                const items = sortableContainer.querySelectorAll('.sortable-item');
                navPreview.innerHTML = '';
                
                items.forEach((item, index) => {
                    const title = item.querySelector('h4').textContent;
                    
                    if (index < 6) {
                        const link = document.createElement('a');
                        link.href = '#';
                        link.className = 'text-gray-600 hover:text-blue-600 transition duration-200 font-medium';
                        link.textContent = title;
                        navPreview.appendChild(link);
                    } else if (index === 6) {
                        // Add "More" dropdown indicator
                        const moreButton = document.createElement('div');
                        moreButton.className = 'text-gray-600 font-medium flex items-center space-x-1';
                        moreButton.innerHTML = '<span>More</span> <i class="fas fa-chevron-down text-sm"></i>';
                        navPreview.appendChild(moreButton);
                    }
                });
            }

            // Save order
            saveButton.addEventListener('click', function() {
                const items = sortableContainer.querySelectorAll('.sortable-item');
                const orderData = [];
                
                items.forEach((item, index) => {
                    orderData.push({
                        id: item.dataset.id,
                        order: (index + 1) * 10 // Use increments of 10 for easier reordering
                    });
                });

                // Show loading state
                saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                saveButton.disabled = true;

                // Send data to backend
                fetch('save-page-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(orderData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        successMessage.classList.remove('hidden');
                        errorMessage.classList.add('hidden');
                        setTimeout(() => successMessage.classList.add('hidden'), 3000);
                    } else {
                        errorMessage.classList.remove('hidden');
                        successMessage.classList.add('hidden');
                        setTimeout(() => errorMessage.classList.add('hidden'), 3000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    errorMessage.classList.remove('hidden');
                    successMessage.classList.add('hidden');
                    setTimeout(() => errorMessage.classList.add('hidden'), 3000);
                })
                .finally(() => {
                    // Restore button state
                    saveButton.innerHTML = '<i class="fas fa-save mr-2"></i>Save Order';
                    saveButton.disabled = false;
                });
            });

            // Reset to alphabetical order
            resetButton.addEventListener('click', function() {
                if (confirm('Are you sure you want to reset the page order to alphabetical?')) {
                    // Get all items
                    const items = Array.from(sortableContainer.querySelectorAll('.sortable-item'));
                    
                    // Sort by title
                    items.sort((a, b) => {
                        const titleA = a.querySelector('h4').textContent.toLowerCase();
                        const titleB = b.querySelector('h4').textContent.toLowerCase();
                        return titleA.localeCompare(titleB);
                    });
                    
                    // Clear container and re-append sorted items
                    sortableContainer.innerHTML = '';
                    items.forEach(item => sortableContainer.appendChild(item));
                    
                    updateOrderNumbers();
                    updateNavPreview();
                }
            });

            // Initial preview update
            updateOrderNumbers();
            updateNavPreview();
        });
    </script>
</body>
</html>