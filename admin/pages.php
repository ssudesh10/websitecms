<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

$allPages = getAllPages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages - Admin</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Manage Pages</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="create-page.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-plus mr-2"></i>New Page
                    </a>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">All Website Pages</h2>
                <p class="text-gray-600 mt-1">Manage all pages on your website. Default pages cannot be deleted.</p>
            </div>
            
            <div class="overflow-hidden">
                <?php if (empty($allPages)): ?>
                    <div class="p-6 text-center">
                        <i class="fas fa-file-alt text-gray-400 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No pages found</h3>
                        <p class="text-gray-600 mb-4">Get started by creating your first page.</p>
                        <a href="create-page.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Create Page
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                        <?php foreach ($allPages as $page): ?>
                            <?php
                            $sectionsCount = $pdo->prepare("SELECT COUNT(*) FROM page_sections WHERE page_id = ? AND is_active = 1");
                            $sectionsCount->execute([$page['id']]);
                            $sectionCount = $sectionsCount->fetchColumn();
                            ?>
                            <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-lg transition duration-200">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-file-alt text-blue-600"></i>
                                        </div>
                                    </div>
                                    <?php if ($page['is_default']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-star mr-1"></i>Default
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <i class="fas fa-plus mr-1"></i>Custom
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars($page['title']) ?></h3>
                                <p class="text-sm text-gray-600 mb-4">
                                    <i class="fas fa-link mr-1"></i>/<?= htmlspecialchars($page['slug']) ?>
                                </p>
                                
                                <div class="flex items-center text-sm text-gray-500 mb-4">
                                    <i class="fas fa-puzzle-piece mr-1"></i>
                                    <?= $sectionCount ?> sections
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-clock mr-1"></i>
                                    <?= date('M j, Y', strtotime($page['updated_at'])) ?>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <a href="<?= formatUrl($page['slug']) ?>" target="_blank" 
                                       class="flex-1 bg-gray-100 text-gray-700 px-3 py-2 rounded text-sm hover:bg-gray-200 transition duration-200 text-center">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                    <a href="edit-page.php?id=<?= $page['id'] ?>" 
                                       class="flex-1 bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 transition duration-200 text-center">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </a>
                                    <?php if (!$page['is_default']): ?>
                                        <a href="delete-page.php?id=<?= $page['id'] ?>" 
                                           class="bg-red-600 text-white px-3 py-2 rounded text-sm hover:bg-red-700 transition duration-200"
                                           onclick="return confirm('Are you sure you want to delete this page?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>