<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

$allPages = getAllPages();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_pages FROM pages WHERE is_active = 1");
$totalPages = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total_sections FROM page_sections WHERE is_active = 1");
$totalSections = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as default_pages FROM pages WHERE is_default = 1 AND is_active = 1");
$defaultPages = $stmt->fetchColumn();

$customPages = $totalPages - $defaultPages;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
                    <a href="<?= BASE_URL ?>/" target="_blank" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-external-link-alt mr-1"></i>View Site
                    </a>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition duration-200">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-alt text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Pages</h3>
                        <p class="text-2xl font-semibold text-gray-900"><?= $totalPages ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-puzzle-piece text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Total Sections</h3>
                        <p class="text-2xl font-semibold text-gray-900"><?= $totalSections ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-star text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Default Pages</h3>
                        <p class="text-2xl font-semibold text-gray-900"><?= $defaultPages ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-plus text-orange-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-gray-500">Custom Pages</h3>
                        <p class="text-2xl font-semibold text-gray-900"><?= $customPages ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <a href="create-page.php" class="bg-blue-600 text-white p-4 rounded-lg hover:bg-blue-700 transition duration-200 text-center">
                        <i class="fas fa-plus-circle text-2xl mb-2"></i>
                        <p class="font-semibold">Create New Page</p>
                        <p class="text-sm opacity-90">Add a new page to your website</p>
                    </a>
                    
                    <a href="pages.php" class="bg-green-600 text-white p-4 rounded-lg hover:bg-green-700 transition duration-200 text-center">
                        <i class="fas fa-edit text-2xl mb-2"></i>
                        <p class="font-semibold">Manage Pages</p>
                        <p class="text-sm opacity-90">Edit existing pages and sections</p>
                    </a>
                    
                    <a href="manage-page-order.php" class="bg-purple-600 text-white p-4 rounded-lg hover:bg-purple-700 transition duration-200 text-center">
                        <i class="fas fa-sort text-2xl mb-2"></i>
                        <p class="font-semibold">Reorder Pages</p>
                        <p class="text-sm opacity-90">Change navigation order</p>
                    </a>
                    
                    <a href="image-gallery.php" class="bg-sky-300 text-white p-4 rounded-lg hover:bg-sky-400 transition duration-200 text-center">
                        <i class="fas fa-images text-2xl mb-2"></i>
                        <p class="font-semibold">Image Gallery</p>
                        <p class="text-sm opacity-90">Upload and manage images</p>
                    </a>
                    
                    <a href="settings.php" class="bg-gray-600 text-white p-4 rounded-lg hover:bg-gray-700 transition duration-200 text-center">
                        <i class="fas fa-cog text-2xl mb-2"></i>
                        <p class="font-semibold">Settings</p>
                        <p class="text-sm opacity-90">Modify site settings</p>
                    </a>
                </div>
            </div>
        </div>

        <!-- Pages Overview -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">All Pages</h2>
                <a href="create-page.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>New Page
                </a>
            </div>
            <div class="overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Page</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sections</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($allPages as $page): ?>
                            <?php
                            $sectionsCount = $pdo->prepare("SELECT COUNT(*) FROM page_sections WHERE page_id = ? AND is_active = 1");
                            $sectionsCount->execute([$page['id']]);
                            $sectionCount = $sectionsCount->fetchColumn();
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-file-alt text-blue-600 text-sm"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($page['title']) ?></div>
                                            <div class="text-sm text-gray-500">/<?= htmlspecialchars($page['slug']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($page['is_default']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-star mr-1"></i>Default
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-plus mr-1"></i>Custom
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $sectionCount ?> sections
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M j, Y', strtotime($page['updated_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="<?= formatUrl($page['slug']) ?>" target="_blank" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                    <a href="edit-page.php?id=<?= $page['id'] ?>" class="text-green-600 hover:text-green-900 mr-3">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </a>
                                    <?php if (!$page['is_default']): ?>
                                        <a href="delete-page.php?id=<?= $page['id'] ?>" class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this page?')">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>