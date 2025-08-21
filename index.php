<?php
declare(strict_types=1);

// Include config.php first to get SERIAL_NUMBER
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/license_check.php'; // The license function file

if (_0x4f8a()) {
    // Validation passed - continue
} else {
    // Show simple error page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Critical Error</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                background: #f1f1f1; 
                margin: 0; 
                padding: 50px 20px; 
                text-align: center; 
            }
            .error-box { 
                background: white; 
                max-width: 500px; 
                margin: 0 auto; 
                padding: 40px; 
                border: 1px solid #ccc; 
                box-shadow: 0 1px 3px rgba(0,0,0,0.1); 
            }
            h1 { 
                color: #666; 
                font-size: 24px; 
                margin-bottom: 20px; 
            }
            p { 
                color: #444; 
                font-size: 16px; 
                line-height: 1.5; 
            }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>Critical Error</h1>
            <p>There has been a critical error on this website.</p>
            <p><strong>Please check back later.</strong></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}


// Get license status using serial number from config
$licenseData = checkLicenseWithConfig(SERIAL_NUMBER);

// Extract license data for display
$licenseStatus = $licenseData['status'];
$licenseOutput = $licenseData['output'];
$isLicenseCache = $licenseData['is_cache'];
$showLicenseBanner = $licenseData['show_banner'];
$licenseKey = $licenseData['license_key'];

// Check if license allows navbar (but site content is always visible)
$showNavbar = !in_array(strtolower($licenseStatus), ['suspended', 'invalid', 'expired', 'unknown']);

// Installation Directory Check - Must be after license check
if (is_dir(__DIR__ . '/install')) {
    // If install directory exists, redirect to install-check.php
    if (file_exists(__DIR__ . '/install-check.php')) {
        header('Location: install-check.php');
        exit();
    } else {
        // If install-check.php doesn't exist, show a basic warning
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Installation Required</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 50px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
                .warning { color: #d32f2f; font-size: 18px; margin-bottom: 20px; }
                .instructions { color: #666; line-height: 1.6; }
                .code { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>⚠️ Installation Directory Found</h1>
                <p class="warning">For security reasons, this website cannot be accessed while the installation directory exists.</p>
                <div class="instructions">
                    <p><strong>To continue:</strong></p>
                    <ol style="text-align: left; display: inline-block;">
                        <li>Delete or rename the <code class="code">install</code> directory</li>
                        <li>Refresh this page</li>
                    </ol>
                    <p>This is a security measure to prevent unauthorized access to installation files.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

require_once 'function.php';
require_once 'page-sections.php';
require_once 'includes/seo_functions.php';
require_once 'includes/clean_url_helpers.php';

if (!isDatabaseConnected()) {
    showDatabaseError("The website is temporarily unavailable due to database connection issues.");
}



// Only load page content if license allows it
$currentSlug = '';
if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $currentSlug = $_GET['slug'];
} else {
    // Try to get slug from URL path
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $path = trim($path, '/');
    $currentSlug = empty($path) ? 'home' : $path;
}
// Get homepage
$page = getPageBySlug('home');
if (!$page) {
    header("HTTP/1.0 404 Not Found");
    include '404.php';
    exit();
}

$sections = getPageSections($page['id']);

// Get page-specific SEO data using the new function
$seoData = getPageSEOData($page['id']);

// Get canonical URL (clean format)
$currentUrl = getCanonicalUrl();

// Generate breadcrumbs for homepage
$breadcrumbs = generateBreadcrumbs('home', $page['title']);

// Get pages with correct ordering
$stmt = $pdo->prepare("SELECT * FROM pages WHERE is_active = 1 ORDER BY sort_order ASC, title ASC");
$stmt->execute();
$allPages = $stmt->fetchAll();

// Format URLs for navigation
$allPages = formatNavigationUrls($allPages);

// Split for navigation
$mainNavPages = array_slice($allPages, 0, 6);
$dropdownPages = array_slice($allPages, 6);

// Get dynamic settings (always load these for basic site info)
$siteName = getSetting('site_name', 'Your Website');
$siteTagline = getSetting('site_tagline', '');
$siteDescription = getSetting('site_description', 'Creating amazing digital experiences for businesses worldwide.');
$contactEmail = getSetting('contact_email', 'info@yourwebsite.com');
$contactPhone = getSetting('contact_phone', '+1 (555) 123-4567');
$contactAddress = getSetting('contact_address', '123 Business St, City, State');
$businessHours = getSetting('business_hours', '');
$cacheenabled = getSetting('cache_enabled', '0');
$maintenance_mode = getSetting('maintenance_mode', '0');

// Social Media Links
$facebookUrl = getSetting('facebook_url', '');
$twitterUrl = getSetting('twitter_url', '');
$linkedinUrl = getSetting('linkedin_url', '');
$instagramUrl = getSetting('instagram_url', '');
$youtubeUrl = getSetting('youtube_url', '');

// Get favicon settings
$faviconEnabled = getSetting('favicon_enabled', '0');
$faviconUrl = getSetting('favicon_url', '');

// Get site logo settings
$siteLogoEnabled = getSetting('site_logo_enabled', '0');
$siteLogoUrl = getSetting('site_logo_url', '');

// Maintenance Mode Check - Show maintenance page for non-admin users
if ($maintenance_mode === '1') {
    // Check if user is NOT an admin
    if (!isLoggedIn()) {
        // Include the maintenance page instead of continuing with normal page load
        include __DIR__ . '/maintenance.php';
        exit();
    } else {
        // Admin is logged in - show a notice but continue to normal site
        $showMaintenanceBanner = true;
    }
} else {
    $showMaintenanceBanner = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= generateThemeCSS() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php
    // Generate all SEO meta tags using the new function
    echo generateSEOTags($seoData, $currentUrl);
    
    // Generate structured data for homepage
    $structuredData = [
        "@type" => "WebPage",
        "publisher" => [
            "@type" => "Organization",
            "name" => $siteName,
            "url" => formatUrl('home', true),
            "description" => $siteDescription
        ],
        "datePublished" => $page['created_at'] ?? date('c'),
        "dateModified" => $page['updated_at'] ?? date('c'),
        "mainEntityOfPage" => [
            "@type" => "WebPage",
            "@id" => $currentUrl
        ],
        "isPartOf" => [
            "@type" => "WebSite",
            "name" => $siteName,
            "url" => formatUrl('home', true)
        ]
    ];
    echo generateStructuredData($seoData, $currentUrl, $structuredData);
    
    // Add Google Analytics if configured
    if (!empty($seoData['google_analytics_id'])) {
        echo generateGoogleAnalytics($seoData['google_analytics_id']);
    }
    ?>
    
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
      <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!--  Display favicon if enabled and URL exists -->
    <?php
        if ($faviconEnabled === '1' && !empty($faviconUrl) && file_exists($faviconUrl)) {
            $faviconPath = BASE_URL . '/' . $faviconUrl;
            $cacheParam = '?v=' . filemtime($faviconUrl);
            
            echo "\n    <!-- Favicon -->\n";
            echo "    <link rel=\"icon\" href=\"{$faviconPath}{$cacheParam}\">\n";
            echo "    <link rel=\"shortcut icon\" href=\"{$faviconPath}{$cacheParam}\">\n";
        }
    ?>
    <style>
        /* Your existing styles stay the same */
        .navbar { z-index: 1000 !important; }
        .mobile-menu-overlay { z-index: 999 !important; }
        .desktop-dropdown { z-index: 998 !important; }
        .admin-edit-btn { z-index: 40 !important; }
        .slider-controls { z-index: 30 !important; }
        .license-banner { z-index: 1001 !important; } /* Higher than navbar */
        .license-status { z-index: 1002 !important; } /* Higher than banner */
        
        body.menu-open { overflow: hidden; }
        .transition-all { transition: all 0.3s ease; }
        section { position: relative; z-index: 1; }
        .admin-edit-btn { position: relative; z-index: 40; }
        
        .dropdown-menu {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        
        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-arrow { transition: transform 0.3s ease; }
        .dropdown:hover .dropdown-arrow { transform: rotate(180deg); }
        
        @media (max-width: 1023px) {
            .desktop-dropdown { display: none !important; }
        }
        .max-w-6xl2{  
            max-width: 80rem;
        }
        
        /* License Banner Styles - Very Top of Page */
        .license-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001 !important;
            animation: slideInDown 0.5s ease-out;
            padding: 12px 20px;
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .banner-suspended {
            background: linear-gradient(90deg, #dc2626, #b91c1c);
            color: white;
        }
        
        .banner-expired {
            background: linear-gradient(90deg, #ea580c, #c2410c);
            color: white;
        }
        
        .banner-invalid {
            background: linear-gradient(90deg, #7c2d12, #92400e);
            color: white;
        }
        
        .banner-unknown {
            background: linear-gradient(90deg, #374151, #4b5563);
            color: white;
        }
        
        /* Adjust body top margin when banner is shown */
        body.has-license-banner {
            margin-top: 60px;
        }

        /* When navbar is hidden due to license issues, adjust top margin */
        body.navbar-hidden {
            margin-top: 60px; /* Only banner height, no navbar space */
        }
        
        body.navbar-hidden.has-license-banner {
            margin-top: 60px; /* Only banner height */
        }

        /* License Status Output Styles */
        .license-status {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            position: fixed;
            top: 70px; /* Below banner if it exists */
            right: 10px;
            border: 1px solid #333;
        }
        
        .license-status.good {
            background: rgba(34, 197, 94, 0.9);
            border-color: #22c55e;
        }
        
        .license-status.error {
            background: rgba(239, 68, 68, 0.9);
            border-color: #ef4444;
        }

        /* License restriction message styles */
        .license-restriction-message {
            min-height: calc(100vh - 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .restriction-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            margin: 20px;
        }

        .restriction-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .restriction-icon.suspended {
            color: #dc2626;
        }

        .restriction-icon.invalid {
            color: #7c2d12;
        }
    </style>
</head>
<body class="bg-gray-50 <?= $showLicenseBanner ? 'has-license-banner' : '' ?> <?= !$showNavbar ? 'navbar-hidden' : '' ?>">
    
    <!-- License Status Banner - Shows for all license issues -->
<!-- License Status Banner - Shows for all license issues -->
<?php if ($showLicenseBanner): ?>
    <?php
    $bannerClass = 'banner-unknown';
    $bannerIcon = 'fas fa-exclamation-triangle';
    $bannerMessage = 'License Status: ' . $licenseStatus;
    $showSerialNumber = false;
    $showLicenseLink = true;
    $linkText = 'Fix License';
    
    switch (strtolower($licenseStatus)) {
        case 'suspended':
            $bannerClass = 'banner-suspended';
            $bannerIcon = 'fas fa-ban';
            $bannerMessage = 'License Suspended - Please contact support';
            $showSerialNumber = false;
            $showLicenseLink = true;
            $linkText = 'Fix License';
            break;
        case 'expired':
            $bannerClass = 'banner-expired';
            $bannerIcon = 'fas fa-clock';
            $bannerMessage = 'License Expired - Please renew your license';
            $showLicenseLink = true;
            $linkText = 'Fix License';
            break;
        case 'invalid':
            $bannerClass = 'banner-invalid';
            $bannerIcon = 'fas fa-times-circle';
            $bannerMessage = 'Invalid License - Please check your license key';
            $showLicenseLink = true;
            $linkText = 'Fix License';
            break;
        case 'unknown':
            $bannerClass = 'banner-unknown';
            $bannerIcon = 'fas fa-question-circle';
            $bannerMessage = 'License Status Unknown - Please contact support';
            $showLicenseLink = true;
            $linkText = 'Fix License';
            break;
    }
    
    if ($isLicenseCache) {
        $bannerMessage .= ' (cached)';
    }
    ?>
    <div class="license-banner <?= $bannerClass ?>">
        <div class="max-w-7xl mx-auto flex items-center justify-center space-x-3 flex-wrap">
            <div class="flex items-center space-x-3">
                <i class="<?= $bannerIcon ?> text-lg"></i>
                <span><?= htmlspecialchars($bannerMessage) ?></span>
            </div>
            
            <?php if ($showLicenseLink): ?>
                <span class="text-sm">
                    <a href="<?= BASE_URL ?>/admin/settings.php#license" 
                       class="inline-flex items-center px-3 py-1 bg-opacity-10 rounded-full text-white hover:bg-opacity-20 transition-all duration-200 font-medium">
                        <i class="fas fa-cog mr-1"></i>
                        Fix License
                    </a>
                </span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
    
    <!-- Navigation with Clean URLs - Only show if license allows navbar -->
    <?php if ($showNavbar): ?>
    <nav class="bg-white shadow-lg sticky top-0 navbar" style="z-index: 1000 !important;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Dynamic Logo -->
                <div class="flex items-center">
    <a href="<?= formatUrl('home') ?>" class="flex items-center space-x-3 hover:opacity-90 transition-opacity duration-200">
        <?php if ($siteLogoEnabled === '1' && !empty($siteLogoUrl) && file_exists($siteLogoUrl)): ?>
            <!-- Site Logo -->
            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($siteLogoUrl) ?>?v=<?= filemtime($siteLogoUrl) ?>" 
                 alt="<?= htmlspecialchars($siteName) ?>" 
                 class="h-8 w-auto max-w-40 object-contain">
        <?php else: ?>
            <!-- Fallback to text-only logo -->
            <span class="text-xl font-bold text-gray-800 hover:text-blue-600 transition-colors duration-200">
                <?= htmlspecialchars($siteName) ?>
            </span>
        <?php endif; ?>
    </a>

                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden lg:flex items-center space-x-6">
                    <!-- Main Navigation Pages (First 6) -->
                    <?php foreach ($mainNavPages as $navPage): ?>
                        <a href="<?= formatUrl($navPage['slug']) ?>" 
                        class="text-gray-600 hover:text-blue-600 transition duration-200 font-medium px-3 py-1 rounded-md <?= $navPage['slug'] === $currentSlug ? 'text-blue-600 font-bold bg-blue-50' : 'hover:bg-gray-50' ?>">
                            <?= htmlspecialchars($navPage['title']) ?>
                        </a>
                    <?php endforeach; ?>
                    
                    <!-- Dropdown for Additional Pages -->
                    <?php if (!empty($dropdownPages)): ?>
                        <div class="relative dropdown">
                            <button class="text-gray-600 hover:text-blue-600 transition duration-200 font-medium flex items-center space-x-1 focus:outline-none">
                                <span>More</span>
                                <i class="fas fa-chevron-down text-sm dropdown-arrow"></i>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div class="dropdown-menu absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-2 desktop-dropdown">
                                <?php foreach ($dropdownPages as $dropdownPage): ?>
                                <a href="<?= $dropdownPage['url'] ?>" 
                                class="block px-4 py-2 text-gray-700 hover:bg-gray-50 hover:text-blue-600 transition duration-200 font-medium <?= isCurrentPage($dropdownPage['slug']) ? 'text-blue-600 bg-blue-50 font-bold' : '' ?>">
                                    <?= htmlspecialchars($dropdownPage['title']) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Admin/Login Button -->
                    <div class="border-l border-gray-200 pl-6">
                        <?php if (isLoggedIn()): ?>
                            <a href="<?= BASE_URL ?>/admin/" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 font-semibold">
                                <i class="fas fa-cog mr-2"></i>Admin
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mobile menu button -->
                <div class="lg:hidden flex items-center">
                    <button id="mobile-menu-button" class="text-gray-600 hover:text-blue-600 focus:outline-none focus:text-blue-600 transition duration-200 p-2" aria-label="Toggle menu">
                        <i class="fas fa-bars text-xl" id="menu-icon"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="lg:hidden hidden bg-white border-t border-gray-200 absolute left-0 right-0 top-full shadow-lg mobile-menu-overlay" style="z-index: 999 !important;">
            <div class="px-4 pt-2 pb-4 space-y-1 max-w-7xl mx-auto max-h-96 overflow-y-auto">
                <!-- All pages in mobile menu -->
                <?php foreach ($allPages as $navPage): ?>
                    <a href="<?= $navPage['url'] ?>" 
                    class="block px-3 py-3 text-gray-600 hover:text-blue-600 hover:bg-gray-50 rounded-md transition duration-200 font-medium <?= isCurrentPage($navPage['slug']) ? 'text-blue-600 bg-blue-50 font-bold' : '' ?>">
                        <?= htmlspecialchars($navPage['title']) ?>
                    </a>
                <?php endforeach; ?>
                
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?>/admin/" class="block px-3 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-200 font-semibold">
                            <i class="fas fa-cog mr-2"></i>Admin Panel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Page Content -->
    <main>
        <?php 
        foreach ($sections as $section): 
            renderSection($section, isLoggedIn());
        endforeach; 
        ?>
    </main>

    <!-- SEO Debug Information (only shows when ?debug_seo=1 and admin is logged in) -->
    <?php if (isset($_GET['debug_seo']) && isLoggedIn()): ?>
        <div class="fixed bottom-4 right-4 bg-black bg-opacity-90 text-white p-3 rounded-lg text-xs max-w-xs z-50">
            <strong>SEO Debug (Homepage):</strong><br>
            <strong>Title:</strong> <?= htmlspecialchars($seoData['meta_title']) ?><br>
            <strong>Description:</strong> <?= htmlspecialchars(substr($seoData['meta_description'], 0, 50)) ?>...<br>
            <strong>Keywords:</strong> <?= htmlspecialchars($seoData['meta_keywords']) ?><br>
            <strong>Canonical:</strong> <?= htmlspecialchars($currentUrl) ?>
        </div>
    <?php endif; ?>

    <!-- License Debug Information -->
    <?php if (isset($_GET['debug_license']) && isLoggedIn()): ?>
        <div class="fixed bottom-20 right-4 bg-black bg-opacity-90 text-white p-3 rounded-lg text-xs max-w-xs z-50">
            <strong>License Debug:</strong><br>
            <strong>Serial:</strong> <?= htmlspecialchars(SERIAL_NUMBER) ?><br>
            <strong>Status:</strong> <?= htmlspecialchars($licenseStatus) ?><br>
            <strong>Output:</strong> <?= htmlspecialchars($licenseOutput) ?><br>
            <strong>Cache:</strong> <?= $isLicenseCache ? 'Yes' : 'No' ?><br>
            <strong>Show Banner:</strong> <?= $showLicenseBanner ? 'Yes' : 'No' ?><br>
            <strong>Show Navbar:</strong> <?= $showNavbar ? 'Yes' : 'No' ?>
        </div>
    <?php endif; ?>

    <!-- Dynamic Footer with Clean URLs -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="max-w-7xl  mx-auto px-4 sm:px-6 lg:px-8">
            <?php 
            // Split pages into chunks of 5 for columns
            $pageChunks = array_chunk($allPages, 5);
            $totalChunks = count($pageChunks);
            
            // Determine grid layout based on number of chunks
            if ($totalChunks == 1) {
                $gridCols = "md:grid-cols-3"; // Company info + 1 Quick Links column + Contact
            } elseif ($totalChunks == 2) {
                $gridCols = "md:grid-cols-4"; // Company info + 2 Quick Links columns + Contact
            } elseif ($totalChunks == 3) {
                $gridCols = "md:grid-cols-5"; // Company info + 3 Quick Links columns + Contact
            } else {
                $gridCols = "md:grid-cols-6"; // Company info + 4 Quick Links columns + Contact (max)
            }
            
            // Get YouTube URL from settings (match your database field name)
            $youtubeUrl = getSetting('youtube_url', '');
            ?>
            
            <div class="grid grid-cols-1 <?= $gridCols ?> gap-4 justify-center items-start">
                <!-- Company Info Column -->
                <div class="pr-2">
                    <a href="<?= formatUrl('home') ?>" class="inline-block hover:opacity-90 transition-opacity duration-200">
                        <?php if ($siteLogoEnabled === '1' && !empty($siteLogoUrl) && file_exists($siteLogoUrl)): ?>
                            <!-- Site Logo in Footer -->
                            <img src="<?= BASE_URL ?>/<?= htmlspecialchars($siteLogoUrl) ?>?v=<?= filemtime($siteLogoUrl) ?>" 
                                 alt="<?= htmlspecialchars($siteName) ?>" 
                                 class="h-10 w-auto max-w-48 object-contain mb-2 filter">
                            <!-- Logo with white filter for dark background -->
                        <?php else: ?>
                            <!-- Fallback to text logo -->
                            <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($siteName) ?></h3>
                        <?php endif; ?>
                    </a>
                    <p class="text-gray-400 leading-relaxed"><?= htmlspecialchars($siteDescription) ?></p>
                    
                    <?php if ($businessHours): ?>
                    <div class="mt-4">
                        <h5 class="text-sm font-semibold mb-2 text-gray-300">Business Hours</h5>
                        <p class="text-gray-400 text-sm whitespace-pre-line"><?= htmlspecialchars($businessHours) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Links Columns (Dynamic based on page count) -->
                <?php foreach ($pageChunks as $chunkIndex => $pageChunk): ?>
                <div class="px-2">
                    <h4 class="text-lg font-semibold mb-4">
                        <?= $chunkIndex == 0 ? 'Quick Links' : 'More Links' ?>
                    </h4>
                    <ul class="space-y-2">
                        <?php foreach ($pageChunk as $footerPage): ?>
                            <li>
                                <a href="<?= $footerPage['url'] ?>" 
                                   class="text-gray-400 hover:text-white transition duration-200 inline-flex items-center">
                                    <i class="fas fa-chevron-right text-xs mr-2 opacity-50"></i>
                                    <?= htmlspecialchars($footerPage['title']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
                
                <!-- Contact Info Column -->
                <div class="pl-2">
                    <h4 class="text-lg font-semibold mb-4">Contact Info</h4>
                    <div class="space-y-3">
                        <p class="text-gray-400 flex items-center">
                            <i class="fas fa-envelope mr-3 text-blue-400"></i>
                            <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="hover:text-white transition duration-200"><?= htmlspecialchars($contactEmail) ?></a>
                        </p>
                        <p class="text-gray-400 flex items-center">
                            <i class="fas fa-phone mr-3 text-blue-400"></i>
                            <a href="tel:<?= htmlspecialchars(str_replace([' ', '(', ')', '-'], '', $contactPhone)) ?>" class="hover:text-white transition duration-200"><?= htmlspecialchars($contactPhone) ?></a>
                        </p>
                        <?php if ($contactAddress): ?>
                        <p class="text-gray-400 flex items-start">
                            <i class="fas fa-map-marker-alt mr-3 text-blue-400 mt-1"></i>
                            <span class="whitespace-pre-line"><?= htmlspecialchars($contactAddress) ?></span>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Dynamic Social Media Links -->
                    <?php 
                    // Include YouTube URL in the check
                    $hasAnySocial = $facebookUrl || $twitterUrl || $linkedinUrl || $instagramUrl || $youtubeUrl;
                    if ($hasAnySocial): 
                    ?>
                    <div class="mt-6">
                        <h5 class="text-sm font-semibold mb-3 text-gray-300">Follow Us</h5>
                        <div class="flex space-x-3">
                            <?php if ($facebookUrl): ?>
                            <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" class="text-gray-400 hover:text-blue-400 transition duration-200">
                                <i class="fab fa-facebook text-xl"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($twitterUrl): ?>
                            <a href="<?= htmlspecialchars($twitterUrl) ?>" target="_blank" class="text-gray-400 hover:text-blue-400 transition duration-200">
                                <i class="fab fa-twitter text-xl"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($linkedinUrl): ?>
                            <a href="<?= htmlspecialchars($linkedinUrl) ?>" target="_blank" class="text-gray-400 hover:text-blue-400 transition duration-200">
                                <i class="fab fa-linkedin text-xl"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($instagramUrl): ?>
                            <a href="<?= htmlspecialchars($instagramUrl) ?>" target="_blank" class="text-gray-400 hover:text-blue-400 transition duration-200">
                                <i class="fab fa-instagram text-xl"></i>
                            </a>
                            <?php endif; ?>

                            <?php if ($youtubeUrl): ?>
                            <a href="<?= htmlspecialchars($youtubeUrl) ?>" target="_blank" class="text-gray-400 hover:text-red-400 transition duration-200">
                                <i class="fab fa-youtube text-xl"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center">
                <p class="text-gray-400">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. All rights reserved. 
                    <!-- |  <a href="#" class="hover:text-white transition duration-200">Privacy Policy</a> | 
                    <a href="#" class="hover:text-white transition duration-200">Terms of Service</a> -->
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript for mobile menu and interactions -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Only initialize navigation JavaScript if navbar exists
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = document.getElementById('menu-icon');
            const body = document.body;
            let isMenuOpen = false;

            // Check if navigation elements exist (they won't if license is restricted)
            if (mobileMenuButton && mobileMenu && menuIcon) {
                function closeMobileMenu() {
                    if (!isMenuOpen) return;
                    
                    mobileMenu.classList.add('hidden');
                    menuIcon.className = 'fas fa-bars text-xl';
                    body.classList.remove('menu-open');
                    isMenuOpen = false;
                    body.style.overflow = 'auto';
                }

                function openMobileMenu() {
                    if (isMenuOpen) return;
                    
                    mobileMenu.classList.remove('hidden');
                    menuIcon.className = 'fas fa-times text-xl';
                    body.classList.add('menu-open');
                    isMenuOpen = true;
                    body.style.overflow = 'hidden';
                }

                function toggleMobileMenu() {
                    if (isMenuOpen) {
                        closeMobileMenu();
                    } else {
                        openMobileMenu();
                    }
                }

                mobileMenuButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    toggleMobileMenu();
                });

                const mobileLinks = mobileMenu.querySelectorAll('a');
                mobileLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        setTimeout(closeMobileMenu, 100);
                    });
                });

                document.addEventListener('click', function(event) {
                    if (isMenuOpen && 
                        !mobileMenu.contains(event.target) && 
                        !mobileMenuButton.contains(event.target)) {
                        closeMobileMenu();
                    }
                });

                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 1024 && isMenuOpen) {
                        closeMobileMenu();
                    }
                });

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && isMenuOpen) {
                        closeMobileMenu();
                    }
                });

                mobileMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            // Desktop dropdown functionality (only if dropdown exists)
            const dropdown = document.querySelector('.dropdown');
            const dropdownMenu = document.querySelector('.dropdown-menu');
            
            if (dropdown && dropdownMenu) {
                document.addEventListener('click', function(event) {
                    if (!dropdown.contains(event.target)) {
                        dropdownMenu.style.opacity = '0';
                        dropdownMenu.style.visibility = 'hidden';
                        dropdownMenu.style.transform = 'translateY(-10px)';
                    }
                });

                dropdown.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        const isVisible = dropdownMenu.style.visibility === 'visible';
                        
                        if (isVisible) {
                            dropdownMenu.style.opacity = '0';
                            dropdownMenu.style.visibility = 'hidden';
                            dropdownMenu.style.transform = 'translateY(-10px)';
                        } else {
                            dropdownMenu.style.opacity = '1';
                            dropdownMenu.style.visibility = 'visible';
                            dropdownMenu.style.transform = 'translateY(0)';
                        }
                    }
                });
            }
        });

        // Smooth scrolling for anchor links (works regardless of license status)
        document.addEventListener('DOMContentLoaded', function() {
            const anchorLinks = document.querySelectorAll('a[href^="#"]');
            
            anchorLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href === '#') return;
                    
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Page load handlers
        window.addEventListener('load', function() {
            const loadingElements = document.querySelectorAll('.loading');
            loadingElements.forEach(el => el.style.display = 'none');
            
            const mainContent = document.querySelector('main');
            if (mainContent) {
                mainContent.style.opacity = '1';
            }
        });

        // Enhanced Analytics for Homepage
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof gtag !== 'undefined') {
                // Track homepage view with enhanced data
                gtag('event', 'page_view', {
                    page_title: '<?= addslashes($seoData['meta_title']) ?>',
                    page_location: '<?= addslashes($currentUrl) ?>',
                    custom_parameter_page_type: 'homepage'
                });
            }
        });

        // Force scroll to top on page load/refresh
        window.addEventListener('beforeunload', function() {
            // Store that we're about to refresh/navigate
            sessionStorage.setItem('scrollToTop', 'true');
        });

        // Immediately scroll to top when page starts loading
        if (sessionStorage.getItem('scrollToTop') === 'true') {
            // Remove the flag
            sessionStorage.removeItem('scrollToTop');
            
            // Force scroll to top immediately
            window.scrollTo(0, 0);
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
        }

        // Also force scroll to top on page load
        window.addEventListener('load', function() {
            window.scrollTo(0, 0);
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
        });

        // Alternative method - disable scroll restoration
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        // License Banner dismiss functionality (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const licenseBanner = document.querySelector('.license-banner');
            if (licenseBanner) {
                // Add click to dismiss functionality (optional)
                licenseBanner.style.cursor = 'pointer';
                licenseBanner.title = 'Click to dismiss temporarily';
                
                licenseBanner.addEventListener('click', function() {
                    this.style.transform = 'translateY(-100%)';
                    setTimeout(() => {
                        this.style.display = 'none';
                        document.body.classList.remove('has-license-banner');
                        document.body.classList.remove('navbar-hidden');
                    }, 300);
                });
            }
        });

    </script>
</body>
</html>