<?php
$navigation_pages = getNavigationPages();
$current_slug = $_GET['slug'] ?? 'home';
$theme_colors = getThemeColors();
?>

<header class="bg-white shadow-lg sticky top-0 z-50">
    <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <!-- Logo/Site Title -->
            <div class="flex items-center">
                <a href="<?php echo base_url(); ?>" class="text-2xl font-bold <?php echo $theme_colors['text']; ?> hover:opacity-80 transition-opacity">
                    <?php echo e(getSetting('site_title', 'My Website')); ?>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-8">
                <?php foreach ($navigation_pages as $page): ?>
                    <?php 
                    $url = page_url($page['slug']);
                    $is_active = is_active_page($page['slug']);
                    ?>
                    <a href="<?php echo e($url); ?>" 
                       class="nav-link text-gray-700 hover:<?php echo $theme_colors['text']; ?> font-medium <?php echo $is_active ? 'active ' . $theme_colors['text'] : ''; ?>">
                        <?php echo e($page['title']); ?>
                    </a>
                <?php endforeach; ?>
                
                <!-- Admin Link (if logged in) -->
                <?php if (isAdmin()): ?>
                    <a href="<?php echo admin_url('dashboard.php'); ?>" 
                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md font-medium transition-colors">
                        Admin
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button type="button" 
                        id="mobile-menu-button"
                        class="text-gray-700 hover:<?php echo $theme_colors['text']; ?> focus:outline-none focus:<?php echo $theme_colors['text']; ?>"
                        aria-expanded="false"
                        aria-controls="mobile-menu">
                    <span class="sr-only">Open main menu</span>
                    <!-- Hamburger icon -->
                    <svg class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <!-- Close icon (hidden by default) -->
                    <svg class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div class="md:hidden" id="mobile-menu">
            <div class="mobile-menu fixed inset-y-0 left-0 w-64 bg-white shadow-lg z-50 px-4 py-6">
                <!-- Close button -->
                <div class="flex justify-between items-center mb-8">
                    <span class="text-lg font-bold <?php echo $theme_colors['text']; ?>">
                        Menu
                    </span>
                    <button type="button" 
                            id="mobile-menu-close"
                            class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Mobile menu items -->
                <div class="space-y-4">
                    <?php foreach ($navigation_pages as $page): ?>
                        <?php 
                        $url = page_url($page['slug']);
                        $is_active = is_active_page($page['slug']);
                        ?>
                        <a href="<?php echo e($url); ?>" 
                           class="block text-gray-700 hover:<?php echo $theme_colors['text']; ?> font-medium py-2 px-4 rounded-md transition-colors <?php echo $is_active ? $theme_colors['text'] . ' bg-gray-100' : ''; ?>">
                            <?php echo e($page['title']); ?>
                        </a>
                    <?php endforeach; ?>
                    
                    <!-- Admin Link (if logged in) -->
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo admin_url('dashboard.php'); ?>" 
                           class="block bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md transition-colors">
                            Admin Panel
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Overlay -->
            <div class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden" id="mobile-overlay"></div>
        </div>
    </nav>
</header>

<!-- Breadcrumbs (if enabled) -->
<?php if (getSetting('show_breadcrumbs', '1') === '1' && isset($current_page) && $current_page['slug'] !== 'home'): ?>
    <div class="bg-gray-100 border-b">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <nav class="flex" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm">
                    <?php 
                    $breadcrumbs = getBreadcrumbs($current_page);
                    foreach ($breadcrumbs as $index => $crumb): 
                    ?>
                        <li class="flex items-center">
                            <?php if ($index > 0): ?>
                                <svg class="w-4 h-4 text-gray-400 mx-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                            <?php endif; ?>
                            
                            <?php if ($index === count($breadcrumbs) - 1): ?>
                                <span class="text-gray-500 font-medium"><?php echo e($crumb['title']); ?></span>
                            <?php else: ?>
                                <a href="<?php echo e($crumb['url']); ?>" 
                                   class="text-gray-700 hover:<?php echo $theme_colors['text']; ?> font-medium">
                                    <?php echo e($crumb['title']); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.querySelector('.mobile-menu');
    const mobileOverlay = document.getElementById('mobile-overlay');
    const mobileMenuClose = document.getElementById('mobile-menu-close');
    
    function openMobileMenu() {
        mobileMenu.classList.add('active');
        mobileOverlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Toggle icons
        mobileMenuButton.querySelector('svg:first-child').classList.add('hidden');
        mobileMenuButton.querySelector('svg:last-child').classList.remove('hidden');
    }
    
    function closeMobileMenu() {
        mobileMenu.classList.remove('active');
        mobileOverlay.classList.add('hidden');
        document.body.style.overflow = '';
        
        // Toggle icons
        mobileMenuButton.querySelector('svg:first-child').classList.remove('hidden');
        mobileMenuButton.querySelector('svg:last-child').classList.add('hidden');
    }
    
    mobileMenuButton.addEventListener('click', function() {
        if (mobileMenu.classList.contains('active')) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
    });
    
    mobileMenuClose.addEventListener('click', closeMobileMenu);
    mobileOverlay.addEventListener('click', closeMobileMenu);
    
    // Close menu when clicking on a link
    const mobileMenuLinks = mobileMenu.querySelectorAll('a');
    mobileMenuLinks.forEach(link => {
        link.addEventListener('click', closeMobileMenu);
    });
    
    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
            closeMobileMenu();
        }
    });
});
</script>