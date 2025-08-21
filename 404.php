<?php
/**
 * 404 Error Page
 * Save this as 404.php in your websitecms root directory
 */

require_once 'config.php';
require_once 'function.php';
require_once 'includes/seo_functions.php';

// Set 404 header
header("HTTP/1.0 404 Not Found");

// Get dynamic settings
$siteName = getSetting('site_name', 'Your Website');
$siteDescription = getSetting('site_description', 'Creating amazing digital experiences for businesses worldwide.');

// Get all pages for navigation
$stmt = $pdo->prepare("SELECT * FROM pages WHERE is_active = 1 ORDER BY sort_order ASC, title ASC");
$stmt->execute();
$allPages = $stmt->fetchAll();

// SEO data for 404 page
$seoData = [
    'meta_title' => '404 - Page Not Found - ' . $siteName,
    'meta_description' => 'The page you are looking for could not be found. Browse our available pages or return to the homepage.',
    'meta_keywords' => '404, page not found, error',
    'robots' => 'noindex,nofollow',
    'canonical_url' => '',
    'og_title' => '404 - Page Not Found',
    'og_description' => 'The page you are looking for could not be found.',
    'og_image' => ''
];

$currentUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php
    // Generate SEO meta tags for 404 page
    echo generateSEOTags($seoData, $currentUrl);
    ?>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .error-animation {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-30px);
            }
            60% {
                transform: translateY(-15px);
            }
        }
        
        .fade-in {
            animation: fadeIn 1s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- Simple Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="text-xl font-bold text-gray-800 hover:text-blue-600 transition-colors duration-200">
                        <?= htmlspecialchars($siteName) ?>
                    </a>
                </div>
                <div class="flex items-center space-x-6">
                    <a href="<?= BASE_URL ?>" class="text-gray-600 hover:text-blue-600 transition duration-200 font-medium">
                        <i class="fas fa-home mr-1"></i>Home
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>/admin/" class="text-gray-600 hover:text-blue-600 transition duration-200 font-medium">
                            <i class="fas fa-cog mr-1"></i>Admin
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- 404 Error Content -->
    <main class="flex-grow flex items-center justify-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl w-full text-center fade-in">
            <!-- Error Icon -->
            <div class="mb-8">
                <i class="fas fa-exclamation-triangle text-yellow-500 text-8xl error-animation"></i>
            </div>
            
            <!-- Error Message -->
            <h1 class="text-6xl font-bold text-gray-900 mb-4">404</h1>
            <h2 class="text-3xl font-semibold text-gray-700 mb-6">Page Not Found</h2>
            <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                Oops! The page you're looking for seems to have wandered off. 
                Don't worry, even the best explorers get lost sometimes.
            </p>
            
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-12">
                <a href="<?= BASE_URL ?>" 
                   class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition duration-200 font-semibold inline-flex items-center">
                    <i class="fas fa-home mr-2"></i>Go Home
                </a>
                <button onclick="history.back()" 
                        class="bg-gray-600 text-white px-8 py-3 rounded-lg hover:bg-gray-700 transition duration-200 font-semibold inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>Go Back
                </button>
                <a href="#search" 
                   class="text-blue-600 hover:text-blue-800 font-semibold inline-flex items-center">
                    <i class="fas fa-search mr-2"></i>Search Site
                </a>
            </div>
            
            <!-- Search Box -->
            <div id="search" class="max-w-md mx-auto mb-12">
                <div class="relative">
                    <input type="text" 
                           id="search-input"
                           placeholder="Search for pages..." 
                           class="w-full px-4 py-3 pl-12 pr-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                </div>
                <div id="search-results" class="mt-4 hidden">
                    <!-- Search results will appear here -->
                </div>
            </div>
            
            <!-- Popular Pages -->
            <?php if (!empty($allPages)): ?>
            <div class="border-t border-gray-200 pt-12">
                <h3 class="text-2xl font-semibold text-gray-800 mb-6">Popular Pages</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-w-4xl mx-auto">
                    <?php 
                    $popularPages = array_slice($allPages, 0, 6); // Show first 6 pages
                    foreach ($popularPages as $page): 
                    ?>
                        <a href="/<?= htmlspecialchars($page['slug'] === 'home' ? '' : $page['slug']) ?>" 
                           class="block p-4 bg-white rounded-lg shadow-md hover:shadow-lg transition duration-200 border border-gray-200 hover:border-blue-300">
                            <div class="flex items-center">
                                <i class="fas fa-file-alt text-blue-500 mr-3"></i>
                                <div>
                                    <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($page['title']) ?></h4>
                                    <?php if (!empty($page['meta_description'])): ?>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <?= htmlspecialchars(substr($page['meta_description'], 0, 80)) ?>...
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-gray-400">
                &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. All rights reserved.
            </p>
            <p class="text-gray-500 text-sm mt-2">
                If you believe this is an error, please contact us.
            </p>
        </div>
    </footer>

    <!-- JavaScript for Search Functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            const searchResults = document.getElementById('search-results');
            
            // Available pages for search
            const pages = <?= json_encode(array_map(function($page) {
                return [
                    'title' => $page['title'],
                    'slug' => $page['slug'],
                    'url' => '/' . ($page['slug'] === 'home' ? '' : $page['slug']),
                    'description' => $page['meta_description'] ?? ''
                ];
            }, $allPages)) ?>;
            
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                
                if (query.length < 2) {
                    searchResults.classList.add('hidden');
                    return;
                }
                
                const matches = pages.filter(page => 
                    page.title.toLowerCase().includes(query) ||
                    page.description.toLowerCase().includes(query)
                ).slice(0, 5); // Limit to 5 results
                
                if (matches.length > 0) {
                    searchResults.innerHTML = matches.map(page => `
                        <a href="${page.url}" 
                           class="block p-3 bg-white rounded-md border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition duration-200 text-left">
                            <div class="font-semibold text-gray-800">${page.title}</div>
                            ${page.description ? `<div class="text-sm text-gray-600 mt-1">${page.description.substring(0, 100)}...</div>` : ''}
                        </a>
                    `).join('');
                    searchResults.classList.remove('hidden');
                } else {
                    searchResults.innerHTML = '<div class="p-3 text-gray-500 text-center">No pages found matching your search.</div>';
                    searchResults.classList.remove('hidden');
                }
            });
            
            // Hide search results when clicking outside
            document.addEventListener('click', function(event) {
                if (!searchInput.contains(event.target) && !searchResults.contains(event.target)) {
                    searchResults.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>