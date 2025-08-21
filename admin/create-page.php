<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

$error = '';
$success = '';

if ($_POST) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $selectedSections = $_POST['sections'] ?? [];
    
    if (empty($title)) {
        $error = 'Page title is required';
    } elseif (empty($slug)) {
        $error = 'Page slug is required';
    } else {
        // Generate slug if needed
        if ($slug === 'auto') {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
            $slug = trim($slug, '-');
        }
        
        // Check if slug already exists
        $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $error = 'A page with this slug already exists';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Insert page first
                $stmt = $pdo->prepare("INSERT INTO pages (title, slug, meta_description, is_default, is_active) VALUES (?, ?, ?, 0, 1)");
                $stmt->execute([$title, $slug, $metaDescription]);
                $pageId = $pdo->lastInsertId();
                
                // Calculate sort order based on actual page ID (page_id * 10)
                $sortOrder = $pageId * 10;
                
                // Try to update the sort_order field (check if column exists)
                try {
                    $stmt = $pdo->prepare("UPDATE pages SET sort_order = ? WHERE id = ?");
                    $stmt->execute([$sortOrder, $pageId]);
                } catch (PDOException $e) {
                    // If sort_order column doesn't exist, we'll continue without it
                    error_log("sort_order column might not exist: " . $e->getMessage());
                }
                
                // Insert selected sections with proper sort_order
                $sectionSortOrder = 10; // Start from 10, increment by 10
                foreach ($selectedSections as $sectionType) {
                    $sectionTitle = '';
                    $sectionContent = '';
                    
                    switch ($sectionType) {
                        case 'hero':
                            $sectionTitle = 'Welcome to ' . $title;
                            $sectionContent = 'This is the hero section for ' . $title . '. Edit this content to customize your page.';
                            break;
                        case 'content':
                            $sectionTitle = 'About ' . $title;
                            $sectionContent = 'This is a content section. Add your detailed information here.';
                            break;
                        case 'features':
                            $sectionTitle = 'Key Features';
                            $sectionContent = '{"features":[{"title":"Professional Service","description":"Expert team with years of experience","icon":"fas fa-magic","featureurl":"https://example.com"},{"title":"Professional Service 2","description":"Expert team with years of experience","icon":"fas fa-mobile-alt","featureurl":"https://example.com"},{"title":"Professional Service 3","description":"Expert team with years of experience","icon":"fas fa-mobile-alt","featureurl":"https://example.com"}],"metadata":{"totalFeatures":3,"lastUpdated":"2025-07-23T09:08:20.949Z","version":"2.0"}}';
                            break;
                        case 'slider':
                            $sectionTitle = 'Featured Slider';
                            $sectionContent = 'uploads/686625d529889_1751524821.jpg;;;;||uploads/686623e550492_1751524325.jpg;;;;||uploads/686623ec2d159_1751524332.jpg;;;;||uploads/686623f1a49c4_1751524337.jpg;;;;';
                            break;
                        case 'gallery':
                            $sectionTitle = 'Gallery';
                            $sectionContent = '';
                            break;
                        case 'contact_form':
                            $sectionTitle = 'Contact Us';
                            $sectionContent = '';
                            break;
                        case 'testimonials':
                            $sectionTitle = 'What Our Clients Say';
                            $sectionContent = '{"testimonials":[{"name":"John Smith, CEO of TechCorp","quote":"Amazing service and incredible results. Our business grew 300% after working with them.","rating":"5","image":"uploads/6880712871bef_1753248040.png","dateAdded":"2025-07-23T08:05:10.280Z","verified":true},{"name":"Sarah Johnson, Marketing Director","quote":"Professional team that delivers on promises. Highly recommended for any business.","rating":"5","image":"uploads/68674f2157daf_1751600929.jpg","dateAdded":"2025-07-23T08:05:10.280Z","verified":true},{"name":"Mike Davis, Founder","quote":"Best investment we made for our company. The ROI was incredible.","rating":"5","image":"uploads/68674f383e559_1751600952.jpg","dateAdded":"2025-07-23T08:05:10.280Z","verified":true}],"metadata":{"totalTestimonials":3,"lastUpdated":"2025-07-23T08:14:16.619Z","version":"3.0","format":"json","createdBy":"IsolatedTestimonialsManager"}}';
                            break;
                        case 'pricing':
                            $sectionTitle = 'Choose Your Plan';
                            $sectionContent = 'Starter|9|/month|Perfect for small businesses|1|Basic website|5 pages included|Email support|Standard hosting||Professional|19|/month|Great for growing companies|0|Advanced features|15 pages included|Priority support|Enhanced hosting|Analytics included|Enterprise||Basic|29|/month|For large organizations|0|Custom solutions|Unlimited pages|24/7 phone support|Premium hosting|Advanced analytics';
                            break;
                        case 'team':
                            $sectionTitle = 'Meet Our Team';
                            $sectionContent = 'John Doe|Chief Executive Officer|Leading the company with vision and strategy|uploads/686ba18e51e21_1751884174.jpg|0||Jane Smith|Chief Technology Officer|Overseeing all technical operations and innovation|uploads/68674f383e559_1751600952.jpg|0||Mike Johnson|Head of Marketing|Driving growth through strategic marketing initiatives|uploads/68674f2157daf_1751600929.jpg|0||Sarah Wilson|Lead Developer|Creating amazing digital experiences for our clients|uploads/68674f4aea15e_1751600970.jpg|0';
                            break;
                        case 'banner':
                            $sectionTitle = 'Promotional Banner';
                            $sectionContent = 'Welcome to Our Website!|Discover our services and expertise';
                            break;
                        case 'stats':
                            $sectionTitle = 'Our Impact';
                            $sectionContent = '100+|Happy Clients|500+|Projects Done|24/7|Support|99%|Satisfaction';
                            break;
                        case 'video':
                            $sectionTitle = 'Watch Our Story';
                            $sectionContent = '[{"id":1,"url":"https://www.youtube.com/watch?v=AznQtsHfcvI","embed_url":"https://www.youtube.com/embed/AznQtsHfcvI","description":"","order":1},{"id":2,"url":"https://www.youtube.com/watch?v=PRLGuS-HLec","embed_url":"https://www.youtube.com/embed/PRLGuS-HLec","description":"","order":2},{"id":3,"url":"https://www.youtube.com/watch?v=WWNI4xTTNOA","embed_url":"https://www.youtube.com/embed/WWNI4xTTNOA","description":"","order":3}]';
                            break;
                        case 'timeline':
                            $sectionTitle = 'Our Journey';
                            $sectionContent = '2020|Company Founded|Started our journey|2021|First Milestone|Achieved major goals|2022|Expansion|Grew our team|2023|Recognition|Industry awards';
                            break;
                        case 'faq':
                            $sectionTitle = 'Frequently Asked Questions';
                            $sectionContent = 'How long does a typical project take?|Most projects are completed within 2-8 weeks, depending on complexity and requirements.||What is included in the pricing?|All plans include hosting, basic support, and regular updates. Additional features vary by plan.||Do you offer custom solutions?|Yes, we specialize in creating custom solutions tailored to your specific business needs.||What kind of support do you provide?|We offer various levels of support from email to 24/7 phone support, depending on your plan.||Can I upgrade my plan later?|Absolutely! You can upgrade your plan at any time to access more features and services.';
                            break;
                        case 'newsletter':
                            $sectionTitle = 'Stay Updated';
                            $sectionContent = 'Subscribe to our newsletter for the latest updates and offers.';
                            break;
                        case 'blog_posts':
                            $sectionTitle = 'Latest Blog Posts';
                            $sectionContent = '';
                            break;
                        case 'cta':
                            $sectionTitle = 'Ready to Get Started?';
                            $sectionContent = 'Take action today and transform your business with our solutions.';
                            break;
                        case 'projects':
                            $sectionTitle = 'My Projects';
                            $sectionContent = 'Project Name 1|Brief project description goes here|uploads/686625d529889_1751524821.jpg|https://github.com/user/project1|https://project1-demo.com|PHP, JavaScript, MySQL||Project Name 2|Another project with different technologies|uploads/686623e550492_1751524325.jpg|https://github.com/user/project2|https://project2-demo.com|React, Node.js, MongoDB||Project Name 3|Third project showcasing more skills|uploads/686623ec2d159_1751524332.jpg|https://github.com/user/project3|https://project3-demo.com|Python, Django, PostgreSQL';
                            break;
                        case 'textwithimage':
                            $sectionTitle = 'About Our Company';
                            $sectionContent = '[{"contentType":"Testing 1","title":"Testing 1","text":"As an Authorized Partner of the .LK Domain Registry , we make registering your .LK domain simple, secure, and fast. Place your order through our website with the correct details and finish the entire process in about 15 minutes —we’ll handle every step behind the scenes.","image":"uploads/687dc146eac32_1753071942.webp","position":"right","alt":"","buttonText":"","buttonUrl":""},{"contentType":"Web hosting","title":"Testing 2","text":"As an Authorized Partner of the .LK Domain Registry , we make registering your .LK domain simple, secure, and fast. Place your order through our website with the correct details and finish the entire process in about 15 minutes —we’ll handle every step behind the scenes.","image":"uploads/68805911166a3_1753241873.jpg","position":"left","alt":"River","buttonText":"Learn More","buttonUrl":"peekhosting.com"},{"contentType":"Live Chat","title":"Testing 3","text":"As an Authorized Partner of the .LK Domain Registry , we make registering your .LK domain simple, secure, and fast. Place your order through our website with the correct details and finish the entire process in about 15 minutes —we’ll handle every step behind the scenes.","image":"uploads/6880594a4e417_1753241930.webp","position":"right","alt":"","buttonText":"","buttonUrl":""}]';
                            break;
                        case 'custom':
                            $sectionTitle = 'Custom Section';
                            $sectionContent = '<p>This is a custom section. You can add HTML content here.</p>';
                            break;
                    }
                    
                    // Insert section with sort_order instead of section_order
                    $stmt = $pdo->prepare("INSERT INTO page_sections (page_id, section_type, title, content, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$pageId, $sectionType, $sectionTitle, $sectionContent, $sectionSortOrder]);
                    $sectionSortOrder += 10; // Increment by 10 for next section
                }
                
                $pdo->commit();
                $success = 'Page created successfully with ' . count($selectedSections) . ' sections!';
                
                // Redirect to edit page after 2 seconds
                header("refresh:2;url=edit-page.php?id=$pageId");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error creating page: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Page - Admin</title>
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
                    <a href="pages.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Pages
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Create New Page</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
                <br><small>Redirecting to edit page...</small>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <!-- Page Details -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Page Details</h2>
                    <p class="text-gray-600 mt-1">Basic information about your new page.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Page Title *</label>
                        <input type="text" id="title" name="title" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter page title" 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               onkeyup="generateSlug()">
                    </div>
                    
                    <div>
                        <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">Page Slug (URL) *</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                <?= BASE_URL ?>/page.php?slug=
                            </span>
                            <input type="text" id="slug" name="slug" required 
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-r-md focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="page-url-slug" 
                                   value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Leave empty to auto-generate from title. Use lowercase letters, numbers, and hyphens only.</p>
                    </div>
                    
                    <div>
                        <label for="meta_description" class="block text-sm font-medium text-gray-700 mb-2">Meta Description</label>
                        <textarea id="meta_description" name="meta_description" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Brief description for search engines (optional)"><?= htmlspecialchars($_POST['meta_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Section Selection -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Page Sections</h2>
                    <p class="text-gray-600 mt-1">Choose which sections to include in your page. You can reorder and edit these after creation.</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="hero" class="mt-1" 
                                   <?= in_array('hero', $_POST['sections'] ?? ['hero']) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-star text-blue-600 mr-2"></i>Hero Section
                                </div>
                                <div class="text-sm text-gray-500">Large banner with title and description</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="content" class="mt-1"
                                   <?= in_array('content', $_POST['sections'] ?? ['content']) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-align-left text-green-600 mr-2"></i>Content Section
                                </div>
                                <div class="text-sm text-gray-500">Text content with title and description</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="features" class="mt-1"
                                   <?= in_array('features', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-th-large text-purple-600 mr-2"></i>Features Grid
                                </div>
                                <div class="text-sm text-gray-500">Grid of features or services</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="stats" class="mt-1"
                                   <?= in_array('stats', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-chart-line text-indigo-600 mr-2"></i>Statistics
                                </div>
                                <div class="text-sm text-gray-500">Display important numbers and metrics</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="testimonials" class="mt-1"
                                   <?= in_array('testimonials', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-quote-left text-yellow-600 mr-2"></i>Testimonials
                                </div>
                                <div class="text-sm text-gray-500">Customer reviews and feedback</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="pricing" class="mt-1"
                                   <?= in_array('pricing', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-tags text-emerald-600 mr-2"></i>Pricing Plans
                                </div>
                                <div class="text-sm text-gray-500">Service packages and pricing</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="team" class="mt-1"
                                   <?= in_array('team', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-users text-cyan-600 mr-2"></i>Team Members
                                </div>
                                <div class="text-sm text-gray-500">Meet the team behind your success</div>
                            </div>
                        </label>

                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="banner" class="mt-1"
                                <?= in_array('banner', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-rectangle-ad text-orange-600 mr-2"></i>Banner Section
                                </div>
                                <div class="text-sm text-gray-500">Promotional banner with background and text</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="slider" class="mt-1"
                                   <?= in_array('slider', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-images text-pink-600 mr-2"></i>Image Slider
                                </div>
                                <div class="text-sm text-gray-500">Slideshow with multiple images and content</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="gallery" class="mt-1"
                                   <?= in_array('gallery', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-images text-orange-600 mr-2"></i>Image Gallery
                                </div>
                                <div class="text-sm text-gray-500">Grid of images and portfolio</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="video" class="mt-1"
                                   <?= in_array('video', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-video text-rose-600 mr-2"></i>Video Section
                                </div>
                                <div class="text-sm text-gray-500">Embed videos from YouTube/Vimeo</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="timeline" class="mt-1"
                                   <?= in_array('timeline', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-timeline text-violet-600 mr-2"></i>Timeline
                                </div>
                                <div class="text-sm text-gray-500">Company history and milestones</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="faq" class="mt-1"
                                   <?= in_array('faq', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-question-circle text-amber-600 mr-2"></i>FAQ Section
                                </div>
                                <div class="text-sm text-gray-500">Frequently asked questions</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="newsletter" class="mt-1"
                                   <?= in_array('newsletter', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-envelope-open text-teal-600 mr-2"></i>Newsletter
                                </div>
                                <div class="text-sm text-gray-500">Email subscription form</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="cta" class="mt-1"
                                   <?= in_array('cta', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-bullhorn text-red-600 mr-2"></i>Call to Action
                                </div>
                                <div class="text-sm text-gray-500">Encourage user action</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="contact_form" class="mt-1"
                                   <?= in_array('contact_form', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-envelope text-red-600 mr-2"></i>Contact Form
                                </div>
                                <div class="text-sm text-gray-500">Contact form with fields</div>
                            </div>
                        </label>

                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="projects" class="mt-1"
                                <?= in_array('projects', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-briefcase text-blue-600 mr-2"></i>Projects
                                </div>
                                <div class="text-sm text-gray-500">Showcase your portfolio and projects</div>
                            </div>
                        </label>

                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="textwithimage" class="mt-1"
                                <?= in_array('textwithimage', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-image text-indigo-600 mr-2"></i>Text with Image
                                </div>
                                <div class="text-sm text-gray-500">Text content alongside an image</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" name="sections[]" value="custom" class="mt-1"
                                   <?= in_array('custom', $_POST['sections'] ?? []) ? 'checked' : '' ?>>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-code text-gray-600 mr-2"></i>Custom HTML
                                </div>
                                <div class="text-sm text-gray-500">Custom HTML content section</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between">
                <a href="pages.php" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-save mr-2"></i>Create Page
                </button>
            </div>
        </form>
    </div>

    <script>
        function generateSlug() {
            const title = document.getElementById('title').value;
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
        }
        
        // Auto-generate slug when page loads if title exists but slug is empty
        document.addEventListener('DOMContentLoaded', function() {
            const titleInput = document.getElementById('title');
            const slugInput = document.getElementById('slug');
            
            if (titleInput.value && !slugInput.value) {
                generateSlug();
            }
        });
    </script>
</body>
</html>