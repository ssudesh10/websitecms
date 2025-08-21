<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

$pageId = $_GET['page_id'] ?? 0;
$error = '';
$success = '';

// Get page data
$stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
$stmt->execute([$pageId]);
$page = $stmt->fetch();

if (!$page) {
    redirect(BASE_URL . '/admin/pages.php');
}

if ($_POST) {
    $sectionType = $_POST['section_type'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    
    if (empty($sectionType)) {
        $error = 'Please select a section type';
    } else {
        try {
            // Get the highest sort_order for this page and add 10 for the new section
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) as max_order FROM page_sections WHERE page_id = ?");
            $stmt->execute([$pageId]);
            $maxOrder = $stmt->fetchColumn();
            $newSortOrder = $maxOrder + 10; // Add 10 to allow easy reordering
            
            // Set default content based on section type
            if (empty($content)) {
                switch ($sectionType) {
                    case 'hero':
                        $content = 'This is the hero section for ' . $page['title'] . '. Edit this content to customize your page.';
                        break;
                    case 'content':
                        $content = 'This is a content section. Add your detailed information here.';
                        break;
                    case 'features':
                        $content = '{"features":[{"title":"Professional Service","description":"Expert team with years of experience","icon":"fas fa-magic","featureurl":"https://example.com"},{"title":"Professional Service 2","description":"Expert team with years of experience","icon":"fas fa-mobile-alt","featureurl":"https://example.com"},{"title":"Professional Service 3","description":"Expert team with years of experience","icon":"fas fa-mobile-alt","featureurl":"https://example.com"}],"metadata":{"totalFeatures":3,"lastUpdated":"2025-07-23T09:08:20.949Z","version":"2.0"}}';
                        break;
                    case 'stats':
                        $content = '500+|Happy Clients|1000+|Projects Completed|24/7|Support Available|99%|Customer Satisfaction';
                        break;
                    case 'testimonials':
                        $content = '{"testimonials":[{"name":"John Smith, CEO of TechCorp","quote":"Amazing service and incredible results. Our business grew 300% after working with them.","rating":"5","image":"uploads/6880712871bef_1753248040.png","dateAdded":"2025-07-23T08:05:10.280Z","verified":true},{"name":"Sarah Johnson, Marketing Director","quote":"Professional team that delivers on promises. Highly recommended for any business.","rating":"5","image":"uploads/68674f2157daf_1751600929.jpg","dateAdded":"2025-07-23T08:05:10.280Z","verified":true},{"name":"Mike Davis, Founder","quote":"Best investment we made for our company. The ROI was incredible.","rating":"5","image":"uploads/68674f383e559_1751600952.jpg","dateAdded":"2025-07-23T08:05:10.280Z","verified":true}],"metadata":{"totalTestimonials":3,"lastUpdated":"2025-07-23T08:14:16.619Z","version":"3.0","format":"json","createdBy":"IsolatedTestimonialsManager"}}';
                        break;
                    case 'pricing':
                        $content = 'Starter|99|month|Perfect for small businesses|Basic website|5 pages included|Email support|Standard hosting||Professional|299|month|Great for growing companies|Advanced features|15 pages included|Priority support|Enhanced hosting|Analytics included||Enterprise|999|month|For large organizations|Custom solutions|Unlimited pages|24/7 phone support|Premium hosting|Advanced analytics';
                        break;
                    case 'team':
                        $content = 'John Doe|Chief Executive Officer|Leading the company with vision and strategy|uploads/686ba18e51e21_1751884174.jpg|0||Jane Smith|Chief Technology Officer|Overseeing all technical operations and innovation|uploads/68674f383e559_1751600952.jpg|0||Mike Johnson|Head of Marketing|Driving growth through strategic marketing initiatives|uploads/68674f2157daf_1751600929.jpg|0||Sarah Wilson|Lead Developer|Creating amazing digital experiences for our clients|uploads/68674f4aea15e_1751600970.jpg|0';
                        break;
                    case 'banner':
                        $content = 'Welcome to Our Website!|Discover our services and expertise';
                        break;
                    case 'timeline':
                        $content = '2018|Company Founded|Started with a small team and big dreams|2019|First Major Client|Secured our first enterprise-level contract|2020|Team Expansion|Grew to 25+ talented professionals|2021|International Reach|Expanded services to global markets|2022|Industry Recognition|Won multiple awards for innovation|2023|1000+ Projects|Completed over 1000 successful projects';
                        break;
                    case 'faq':
                        $content = 'How long does a typical project take?|Most projects are completed within 2-8 weeks, depending on complexity and requirements.||What is included in the pricing?|All plans include hosting, basic support, and regular updates. Additional features vary by plan.||Do you offer custom solutions?|Yes, we specialize in creating custom solutions tailored to your specific business needs.||What kind of support do you provide?|We offer various levels of support from email to 24/7 phone support, depending on your plan.||Can I upgrade my plan later?|Absolutely! You can upgrade your plan at any time to access more features and services.';
                        break;
                    case 'newsletter':
                        $content = 'Subscribe to our newsletter for the latest updates and exclusive offers. Stay informed about our new features, industry insights, and special promotions.';
                        break;
                    case 'cta':
                        $content = 'Take your business to the next level with our proven solutions. Contact us today for a free consultation and see the difference we can make.';
                        break;
                    case 'video':
                        $content = '[{"id":1,"url":"https://www.youtube.com/watch?v=AznQtsHfcvI","embed_url":"https://www.youtube.com/embed/AznQtsHfcvI","description":"Test 1","order":1},{"id":2,"url":"https://www.youtube.com/watch?v=PRLGuS-HLec","embed_url":"https://www.youtube.com/embed/PRLGuS-HLec","description":"","order":2},{"id":3,"url":"https://www.youtube.com/watch?v=WWNI4xTTNOA","embed_url":"https://www.youtube.com/embed/WWNI4xTTNOA","description":"","order":3}]';
                        break;
                    case 'slider':
                        $content = 'uploads/686625d529889_1751524821.jpg;;;;||uploads/686623e550492_1751524325.jpg;;;;||uploads/686623ec2d159_1751524332.jpg;;;;||uploads/686623f1a49c4_1751524337.jpg;;;;';
                        break;
                    case 'custom':
                        $content = '<p>This is a custom section. You can add HTML content here.</p>';
                        break;
                    case 'gallery':
                        $content = ''; // Gallery starts empty
                        break;
                    case 'projects':
                        $content = 'Project Name 1|Brief project description goes here|uploads/686625d529889_1751524821.jpg|https://github.com/user/project1|https://project1-demo.com|PHP, JavaScript, MySQL||Project Name 2|Another project with different technologies|uploads/686623e550492_1751524325.jpg|https://github.com/user/project2|https://project2-demo.com|React, Node.js, MongoDB||Project Name 3|Third project showcasing more skills|uploads/686623ec2d159_1751524332.jpg|https://github.com/user/project3|https://project3-demo.com|Python, Django, PostgreSQL';
                        break;
                    case 'textwithimage':
                        $content = '[{"contentType":"Testing 1","title":"Testing 1","text":"As an Authorized Partner of the .LK Domain Registry , we make registering your .LK domain simple, secure, and fast. Place your order through our website with the correct details and finish the entire process in about 15 minutes —we’ll handle every step behind the scenes.","image":"uploads/687dc146eac32_1753071942.webp","position":"right","alt":"","buttonText":"","buttonUrl":""},{"contentType":"Web hosting","title":"Testing 2","text":"As an Authorized Partner of the .LK Domain Registry , we make registering your .LK domain simple, secure, and fast. Place your order through our website with the correct details and finish the entire process in about 15 minutes —we’ll handle every step behind the scenes.","image":"uploads/68805911166a3_1753241873.jpg","position":"left","alt":"River","buttonText":"Learn More","buttonUrl":"peekhosting.com"},{"contentType":"Live Chat","title":"Testing 3","text":"As an Authorized Partner of the .LK Domain Registry , we make registering your .LK domain simple, secure, and fast. Place your order through our website with the correct details and finish the entire process in about 15 minutes —we’ll handle every step behind the scenes.","image":"uploads/6880594a4e417_1753241930.webp","position":"right","alt":"","buttonText":"","buttonUrl":""}]';
                        break;
                    case 'contact_form':
                        $content = ''; // Contact form doesn't need content
                        break;
                }
            }
            
            // Set default title if empty for sections that need it
            if (empty($title)) {
                switch ($sectionType) {
                    case 'hero':
                        $title = 'Welcome to ' . $page['title'];
                        break;
                    case 'content':
                        $title = 'About ' . $page['title'];
                        break;
                    case 'features':
                        $title = 'Why Choose Us';
                        break;
                    case 'stats':
                        $title = 'Our Impact';
                        break;
                    case 'testimonials':
                        $title = 'What Our Clients Say';
                        break;
                    case 'pricing':
                        $title = 'Choose Your Plan';
                        break;
                    case 'team':
                        $title = 'Meet Our Team';
                        break;
                    case 'banner':
                        $title = 'Promotional Banner';
                        break;
                    case 'timeline':
                        $title = 'Our Journey';
                        break;
                    case 'faq':
                        $title = 'Frequently Asked Questions';
                        break;
                    case 'newsletter':
                        $title = 'Stay Updated';
                        break;
                    case 'cta':
                        $title = 'Ready to Get Started?';
                        break;
                    case 'video':
                        $title = 'Watch Our Story';
                        break;
                    case 'slider':
                        $title = 'Featured Content';
                        break;
                    case 'gallery':
                        $title = 'Gallery';
                        break;
                    case 'contact_form':
                        $title = 'Contact Us';
                        break;
                    case 'projects':
                        $title = 'My Projects';
                        break;
                    case 'textwithimage':
                        $title = 'About Our Company';
                        break;
                    case 'custom':
                        $title = 'Custom Section';
                        break;
                }
            }
            
            // Insert section with sort_order instead of section_order
            $stmt = $pdo->prepare("INSERT INTO page_sections (page_id, section_type, title, subtitle, content, image_url, background_color, text_color, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$pageId, $sectionType, $title, '', $content, $imageUrl, '#ffffff', '#000000', $newSortOrder]);
            
            $success = 'Section added successfully with sort order: ' . $newSortOrder;
            
            // Redirect to edit page after 2 seconds
            header("refresh:2;url=edit-page.php?id=$pageId");
            
        } catch (Exception $e) {
            $error = 'Error adding section: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Section - <?= htmlspecialchars($page['title']) ?></title>
    <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="edit-page.php?id=<?= $page['id'] ?>" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Page
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Add Section</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Page: <?= htmlspecialchars($page['title']) ?></span>
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
                <br><small>Redirecting to page editor...</small>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8">
            <!-- Section Type Selection -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Choose Section Type</h2>
                    <p class="text-gray-600 mt-1">Select the type of section you want to add to your page.</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="hero" class="mt-1" 
                                   <?= ($_POST['section_type'] ?? '') === 'hero' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-star text-blue-600 mr-2"></i>Hero Section
                                </div>
                                <div class="text-sm text-gray-500">Large banner with title and description</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="content" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'content' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-align-left text-green-600 mr-2"></i>Content Section
                                </div>
                                <div class="text-sm text-gray-500">Text content with title and description</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="features" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'features' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-th-large text-purple-600 mr-2"></i>Features Grid
                                </div>
                                <div class="text-sm text-gray-500">Grid of features or services</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="stats" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'stats' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-chart-line text-indigo-600 mr-2"></i>Statistics
                                </div>
                                <div class="text-sm text-gray-500">Display important numbers and metrics</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="testimonials" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'testimonials' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-quote-left text-yellow-600 mr-2"></i>Testimonials
                                </div>
                                <div class="text-sm text-gray-500">Customer reviews and feedback</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="pricing" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'pricing' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-tags text-emerald-600 mr-2"></i>Pricing Plans
                                </div>
                                <div class="text-sm text-gray-500">Service packages and pricing</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="team" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'team' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-users text-cyan-600 mr-2"></i>Team Members
                                </div>
                                <div class="text-sm text-gray-500">Meet the team behind your success</div>
                            </div>
                        </label>

                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="banner" class="mt-1"
                                <?= ($_POST['section_type'] ?? '') === 'banner' ? 'checked' : '' ?>
                                onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-rectangle-ad text-orange-600 mr-2"></i>Banner Section
                                </div>
                                <div class="text-sm text-gray-500">Promotional banner with background and text</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="slider" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'slider' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-images text-pink-600 mr-2"></i>Image Slider
                                </div>
                                <div class="text-sm text-gray-500">Slideshow with multiple images and content</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="gallery" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'gallery' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-images text-orange-600 mr-2"></i>Image Gallery
                                </div>
                                <div class="text-sm text-gray-500">Grid of images and portfolio</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="video" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'video' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-video text-rose-600 mr-2"></i>Video Section
                                </div>
                                <div class="text-sm text-gray-500">Embed videos from YouTube/Vimeo</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="timeline" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'timeline' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-timeline text-violet-600 mr-2"></i>Timeline
                                </div>
                                <div class="text-sm text-gray-500">Company history and milestones</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="faq" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'faq' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-question-circle text-amber-600 mr-2"></i>FAQ Section
                                </div>
                                <div class="text-sm text-gray-500">Frequently asked questions</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="newsletter" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'newsletter' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-envelope-open text-teal-600 mr-2"></i>Newsletter
                                </div>
                                <div class="text-sm text-gray-500">Email subscription form</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="cta" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'cta' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-bullhorn text-red-600 mr-2"></i>Call to Action
                                </div>
                                <div class="text-sm text-gray-500">Encourage user action</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="contact_form" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'contact_form' ? 'checked' : '' ?>
                                   onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-envelope text-red-600 mr-2"></i>Contact Form
                                </div>
                                <div class="text-sm text-gray-500">Contact form with fields</div>
                            </div>
                        </label>
                        
                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="projects" class="mt-1"
                                <?= ($_POST['section_type'] ?? '') === 'projects' ? 'checked' : '' ?>
                                onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-briefcase text-blue-600 mr-2"></i>Projects
                                </div>
                                <div class="text-sm text-gray-500">Showcase your portfolio and projects</div>
                            </div>
                        </label>

                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="textwithimage" class="mt-1"
                                <?= ($_POST['section_type'] ?? '') === 'textwithimage' ? 'checked' : '' ?>
                                onchange="updateForm()">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">
                                    <i class="fas fa-image text-indigo-600 mr-2"></i>Text with Image
                                </div>
                                <div class="text-sm text-gray-500">Text content alongside an image</div>
                            </div>
                        </label>

                        <label class="relative flex items-start p-4 border border-gray-300 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="radio" name="section_type" value="custom" class="mt-1"
                                   <?= ($_POST['section_type'] ?? '') === 'custom' ? 'checked' : '' ?>
                                   onchange="updateForm()">
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

            <!-- Section Content -->
            <div id="section-content" class="bg-white rounded-lg shadow" style="display: none;">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Section Content</h2>
                    <p class="text-gray-600 mt-1">Customize the content for your selected section type.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div id="title-field">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Section Title</label>
                        <input type="text" id="title" name="title" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               placeholder="Section title will be auto-generated if left empty">
                        <p class="text-sm text-gray-500 mt-1">Leave empty to use default title for this section type.</p>
                    </div>
                    
                    <div id="content-field" style="display: none;">
                        <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Content</label>
                        <textarea id="content" name="content" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Content will be auto-generated if left empty"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                        <p id="content-help" class="text-sm text-gray-500 mt-1">Default content will be created for this section type.</p>
                    </div>
                    
                    <div id="image-field" style="display: none;">
                        <label for="image_url" class="block text-sm font-medium text-gray-700 mb-2">Background/Feature Image URL</label>
                        <input type="url" id="image_url" name="image_url"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>"
                               placeholder="https://example.com/image.jpg">
                        <p class="text-sm text-gray-500 mt-1">Optional: Add a background or feature image for this section.</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-between">
                <a href="edit-page.php?id=<?= $page['id'] ?>" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition duration-200">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition duration-200">
                    <i class="fas fa-plus mr-2"></i>Add Section
                </button>
            </div>
        </form>
    </div>

    <script>
        function updateForm() {
            const sectionType = document.querySelector('input[name="section_type"]:checked')?.value;
            const contentDiv = document.getElementById('section-content');
            const titleField = document.getElementById('title-field');
            const contentField = document.getElementById('content-field');
            const imageField = document.getElementById('image-field');
            const contentTextarea = document.getElementById('content');
            const contentHelp = document.getElementById('content-help');
            const titleInput = document.getElementById('title');
            
            if (!sectionType) {
                contentDiv.style.display = 'none';
                return;
            }
            
            contentDiv.style.display = 'block';
            
            // Reset fields
            titleField.style.display = 'block';
            contentField.style.display = 'block';
            imageField.style.display = 'none';
            contentField.style.display = 'none';
            titleInput.required = false;
            
            switch (sectionType) {
                case 'hero':
                    titleInput.placeholder = 'Welcome to Our Website (auto-generated if empty)';
                    contentTextarea.placeholder = 'Enter the hero section description...';
                    contentHelp.textContent = 'This will be displayed as the main banner text.';
                    imageField.style.display = 'block';
                    break;
                    
                case 'content':
                    titleInput.placeholder = 'Section Title (auto-generated if empty)';
                    contentTextarea.placeholder = 'Enter your content here...';
                    contentHelp.textContent = 'Add detailed text content for this section.';
                    imageField.style.display = 'block';
                    break;
                    
                case 'features':
                    titleInput.placeholder = 'Our Features (auto-generated if empty)';
                    contentTextarea.placeholder = 'Feature 1|Description 1|Feature 2|Description 2';
                    contentHelp.textContent = 'Format: Title|Description|Title2|Description2 (pairs separated by |)';
                    break;
                    
                case 'stats':
                    titleInput.placeholder = 'Our Statistics (auto-generated if empty)';
                    contentTextarea.placeholder = '500+|Happy Clients|1000+|Projects|24/7|Support|99%|Success Rate';
                    contentHelp.textContent = 'Format: Number|Label|Number2|Label2 (pairs separated by |)';
                    break;
                    
                case 'testimonials':
                    titleInput.placeholder = 'What Our Clients Say (auto-generated if empty)';
                    contentTextarea.placeholder = 'John Doe, CEO|Amazing service!|5|image-url||Jane Smith, Manager|Great results!|5|image-url';
                    contentHelp.textContent = 'Format: Name, Title|Quote|Rating|Image URL (each testimonial separated by ||)';
                    break;
                    
                case 'pricing':
                    titleInput.placeholder = 'Choose Your Plan (auto-generated if empty)';
                    contentTextarea.placeholder = 'Starter|9|/month|Perfect for small businesses|1|Basic website|5 pages included|Email support|Standard hosting||Professional|19|/month|Great for growing companies|0|Advanced features|15 pages included|Priority support|Enhanced hosting|Analytics included|Enterprise||Basic|29|/month|For large organizations|0|Custom solutions|Unlimited pages|24/7 phone support|Premium hosting|Advanced analytics';
                    contentHelp.textContent = 'Format: Plan|Price|Period|Description|Feature1|Feature2 (each plan separated by ||)';
                    break;
                    
                case 'team':
                    titleInput.placeholder = 'Meet Our Team (auto-generated if empty)';
                    contentTextarea.placeholder = 'John Doe|CEO|Bio description|image-url||Jane Smith|CTO|Bio2|image2-url';
                    contentHelp.textContent = 'Format: Name|Position|Bio|Image URL (each member separated by ||)';
                    break;
                    
                case 'slider':
                    titleInput.placeholder = 'Featured Slider (auto-generated if empty)';
                    contentTextarea.placeholder = 'image1.jpg|Title 1|Description 1|Button Text|URL||image2.jpg|Title 2|Description 2|Button Text|URL';
                    contentHelp.textContent = 'Format: ImageURL|Title|Description|ButtonText|ButtonURL (each slide separated by ||)';
                    break;
                    
                case 'gallery':
                    titleInput.placeholder = 'Gallery (auto-generated if empty)';
                    contentTextarea.placeholder = 'image1.jpg|image2.jpg|image3.jpg';
                    contentHelp.textContent = 'Separate each image URL with a pipe (|) symbol.';
                    break;
                    
                case 'video':
                    titleInput.placeholder = 'Video Section (auto-generated if empty)';
                    contentTextarea.placeholder = 'https://www.youtube.com/embed/VIDEO_ID';
                    contentHelp.textContent = 'Enter YouTube embed URL.';
                    break;
                    
                case 'timeline':
                    titleInput.placeholder = 'Our Journey (auto-generated if empty)';
                    contentTextarea.placeholder = '2020|Founded|Started our journey||2021|Growth|Expanded our team||2022|Success|Major milestones';
                    contentHelp.textContent = 'Format: Year|Title|Description (each event separated by ||)';
                    break;
                    
                case 'faq':
                    titleInput.placeholder = 'Frequently Asked Questions (auto-generated if empty)';
                    contentTextarea.placeholder = 'Question 1|Answer 1||Question 2|Answer 2||Question 3|Answer 3';
                    contentHelp.textContent = 'Format: Question|Answer (each FAQ separated by ||)';
                    break;
                    
                case 'newsletter':
                    titleInput.placeholder = 'Stay Updated (auto-generated if empty)';
                    contentTextarea.placeholder = 'Subscribe to our newsletter for the latest updates...';
                    contentHelp.textContent = 'Add description for the newsletter signup.';
                    break;
                    
                case 'cta':
                    titleInput.placeholder = 'Ready to Get Started? (auto-generated if empty)';
                    contentTextarea.placeholder = 'Take action today and transform your business...';
                    contentHelp.textContent = 'Add compelling call-to-action text.';
                    break;
                    
                case 'contact_form':
                    titleInput.placeholder = 'Contact Us (auto-generated if empty)';
                    contentField.style.display = 'none';
                    contentHelp.textContent = 'Contact forms display automatically with form fields.';
                    break;

                case 'projects':
                    titleInput.placeholder = 'My Projects (auto-generated if empty)';
                    contentTextarea.placeholder = 'Project Name|Description|Image URL|GitHub URL|Demo URL|Technologies||Project 2|Description 2|Image 2|GitHub 2|Demo 2|Tech Stack 2';
                    contentHelp.textContent = 'Format: ProjectName|Description|ImageURL|GitHubURL|DemoURL|Technologies (each project separated by ||)';
                    break;

                case 'textwithimage':
                    titleInput.placeholder = 'About Our Company (auto-generated if empty)';
                    contentTextarea.placeholder = 'Enter your text content that will appear alongside the image...';
                    contentHelp.textContent = 'This text will be displayed next to the image. Perfect for about sections, company info, or feature descriptions.';
                    imageField.style.display = 'block';
                    break;
                    
                case 'custom':
                    titleInput.placeholder = 'Custom Section (auto-generated if empty)';
                    contentTextarea.placeholder = '<p>Enter your custom HTML content here...</p>';
                    contentHelp.textContent = 'You can use HTML, CSS, and basic JavaScript.';
                    contentTextarea.classList.add('font-mono', 'text-sm');
                    break;
                    
                default:
                    contentTextarea.classList.remove('font-mono', 'text-sm');
            }
        }
        
        // Initialize form on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateForm();
        });
    </script>
</body>
</html>