
<?php
// File: install/setup.php (Database Tables Creation)

// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);

session_start();

if (!isset($_SESSION['db_config'])) {
    header('Location: database.php');
    exit;
}

$error = '';
$success = '';
$db_config = $_SESSION['db_config'];

// Debug: Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for the parameter in multiple ways
    $create_tables = false;
    if (isset($_POST['create_tables']) && $_POST['create_tables'] == '1') {
        $create_tables = true;
    } elseif (isset($_POST['create_tables'])) {
        $create_tables = true;
    } elseif (array_key_exists('create_tables', $_POST)) {
        $create_tables = true;
    }
    
    if ($create_tables) {
        try {
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            // Create tables based on your existing database structure
            $tables = [
                // Admins table
                "CREATE TABLE IF NOT EXISTS `admins` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(50) NOT NULL,
                    `password` varchar(255) NOT NULL,
                    `email` varchar(100) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `last_login` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
                
                // Pages table
                "CREATE TABLE IF NOT EXISTS `pages` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `title` varchar(200) NOT NULL,
                    `slug` varchar(200) NOT NULL,
                    `sort_order` int(11) DEFAULT 0,
                    `is_default` tinyint(1) DEFAULT 0,
                    `is_active` tinyint(1) DEFAULT 1,
                    `meta_description` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `slug` (`slug`),
                    KEY `idx_pages_sort_order` (`sort_order`,`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

                "CREATE TABLE password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT NOT NULL,              -- Links to admins.id
                    token VARCHAR(64) NOT NULL UNIQUE,  -- 64-char secure token
                    expires_at TIMESTAMP NOT NULL,      -- When token expires
                    used TINYINT(1) DEFAULT 0,         -- 0=unused, 1=used
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
                );",
                
                // Page sections table (simplified - removing JSON constraint for compatibility)
                "CREATE TABLE IF NOT EXISTS `page_sections` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `page_id` int(11) NOT NULL,
                    `section_type` enum('hero','content','features','gallery','contact_form','custom','testimonials','pricing','team','stats','video','timeline','faq','newsletter','blog_posts','cta','projects','slider','banner', 'textwithimage') NOT NULL,
                    `title` varchar(200) DEFAULT NULL,
                    `subtitle` varchar(300) DEFAULT NULL,
                    `content` text DEFAULT NULL,
                    `image_url` varchar(500) DEFAULT NULL,
                    `video_url` varchar(500) DEFAULT NULL,
                    `button_text` varchar(100) DEFAULT NULL,
                    `button_url` varchar(500) DEFAULT NULL,
                    `background_color` varchar(20) DEFAULT '#ffffff',
                    `text_color` varchar(20) DEFAULT '#000000',
                    `background_type` varchar(20) DEFAULT 'solid',
                    `gradient_start` varchar(7) DEFAULT '#3b82f6',
                    `gradient_end` varchar(7) DEFAULT '#8b5cf6',
                    `gradient_direction` varchar(20) DEFAULT 'to right',
                    `gradient_text_color` varchar(7) DEFAULT '#ffffff',
                    `section_order` int(11) DEFAULT 0,
                    `sort_order` int(11) DEFAULT 0,
                    `is_active` tinyint(1) DEFAULT 1,
                    `extra_data` longtext DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `button_bg_color` varchar(20) DEFAULT '#3b82f6',
                    `button_text_color` varchar(20) DEFAULT '#ffffff',
                    `button_text_2` varchar(255) DEFAULT '',
                    `button_url_2` text DEFAULT '',
                    `button_bg_color_2` varchar(20) DEFAULT '#6b7280',
                    `button_text_color_2` varchar(20) DEFAULT '#ffffff',
                    `button_style_2` varchar(20) DEFAULT 'solid',
                    `button_alignment` varchar(20) DEFAULT 'center',
                    `button_layout` varchar(20) DEFAULT 'horizontal',
                    PRIMARY KEY (`id`),
                    KEY `idx_page_sections_sort_order` (`page_id`,`sort_order`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
                
                // Page SEO table
                "CREATE TABLE IF NOT EXISTS `page_seo` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `page_id` int(11) NOT NULL,
                    `meta_title` varchar(255) DEFAULT NULL,
                    `meta_description` text DEFAULT NULL,
                    `meta_keywords` text DEFAULT NULL,
                    `og_title` varchar(255) DEFAULT NULL,
                    `og_description` text DEFAULT NULL,
                    `og_image` varchar(255) DEFAULT NULL,
                    `canonical_url` varchar(255) DEFAULT NULL,
                    `robots` varchar(100) DEFAULT 'index,follow',
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `page_id` (`page_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
                
                // Settings table
                "CREATE TABLE IF NOT EXISTS `settings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `setting_key` varchar(255) NOT NULL,
                    `setting_value` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `setting_key` (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
                
                // Contact submissions table
                "CREATE TABLE IF NOT EXISTS `contact_submissions` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `email` varchar(255) NOT NULL,
                    `subject` varchar(200) NOT NULL,
                    `message` text NOT NULL,
                    `ip_address` varchar(45) DEFAULT NULL,
                    `user_agent` text DEFAULT NULL,
                    `section_id` int(11) DEFAULT NULL,
                    `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `status` varchar(20) DEFAULT 'pending',
                    `email_sent` tinyint(1) DEFAULT 0,
                    `email_sent_at` timestamp NULL DEFAULT NULL,
                    `email_error` text DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_email` (`email`),
                    KEY `idx_submitted_at` (`submitted_at`),
                    KEY `idx_section_id` (`section_id`),
                    KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
                
                // Newsletter subscribers table
                "CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `email` varchar(255) NOT NULL,
                    `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `is_active` tinyint(1) DEFAULT 1,
                    `unsubscribe_token` varchar(64) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `email_unique` (`email`),
                    KEY `subscribed_at` (`subscribed_at`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                
                // Uploaded images table
                "CREATE TABLE IF NOT EXISTS `uploaded_images` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `filename` varchar(255) NOT NULL,
                    `original_name` varchar(255) NOT NULL,
                    `file_path` varchar(500) NOT NULL,
                    `file_size` int(11) DEFAULT NULL,
                    `mime_type` varchar(100) DEFAULT NULL,
                    `uploaded_by` int(11) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `uploaded_by` (`uploaded_by`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
                
                // Themes table
                "CREATE TABLE IF NOT EXISTS `themes` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `theme_name` varchar(100) NOT NULL,
                    `theme_display_name` varchar(150) NOT NULL,
                    `theme_description` text DEFAULT NULL,
                    `theme_author` varchar(100) DEFAULT NULL,
                    `theme_version` varchar(20) DEFAULT '1.0.0',
                    `theme_screenshot` varchar(255) DEFAULT NULL,
                    `theme_config` longtext DEFAULT NULL,
                    `is_active` tinyint(1) DEFAULT 0,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `theme_name` (`theme_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            ];
            
            $success_count = 0;
            foreach ($tables as $sql) {
                try {
                    $pdo->exec($sql);
                    $success_count++;
                } catch (PDOException $e) {
                    throw new PDOException("Error creating table: " . $e->getMessage());
                }
            }
            
            // Add foreign key constraints after tables are created
            try {
                $pdo->exec("ALTER TABLE `page_sections` ADD CONSTRAINT `page_sections_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE");
            } catch (PDOException $e) {
                // Constraint might already exist, ignore this error
            }
            
            try {
                $pdo->exec("ALTER TABLE `page_seo` ADD CONSTRAINT `page_seo_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE");
            } catch (PDOException $e) {
                // Constraint might already exist, ignore this error
            }
            
            try {
                $pdo->exec("ALTER TABLE `uploaded_images` ADD CONSTRAINT `uploaded_images_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL");
            } catch (PDOException $e) {
                // Constraint might already exist, ignore this error
            }
            
            // Insert default settings
            $default_settings = [
                ['site_name', 'Your Website CMS'],
                ['site_tagline', 'Transform your business with innovative solutions'],
                ['site_description', 'Creating amazing digital experiences for businesses worldwide. We build modern, responsive websites that drive results.'],
                ['admin_email', 'admin@yourwebsite.com'],
                ['timezone', 'UTC'],
                ['maintenance_mode', '0'],
                ['contact_email', 'info@yourwebsite.com'],
                ['contact_phone', '+1 (555) 123-4567'],
                ['contact_address', '123 Business St, City, State'],
                ['business_hours', "Mon-Fri: 9:00 AM - 5:00 PM\nSat: 10:00 AM - 3:00 PM\nSun: Closed"],
                ['facebook_url', ''],
                ['twitter_url', ''],
                ['linkedin_url', ''],
                ['instagram_url', ''],
                ['youtube_url', ''],
                ['default_meta_title', 'Your Website - Professional Business Solutions'],
                ['default_meta_description', 'Transform your business with our innovative solutions. Professional services designed to help your business grow and succeed.'],
                ['default_meta_keywords', 'business, solutions, professional, services'],
                ['google_analytics_id', ''],
                ['google_search_console', ''],
                ['max_upload_size', '5'],
                ['allowed_file_types', 'jpg,jpeg,png,gif,webp,svg'],
                ['items_per_page', '10'],
                ['enable_comments', '0'],
                ['cache_enabled', '0'],
                ['recaptcha_enabled', '0'],
                ['recaptcha_site_key', ''],
                ['recaptcha_secret_key', ''],
                ['active_theme', 'default'],
                ['theme_customization', '{}'],
                ['favicon_enabled', '0'],
                ['favicon_url', '{}']
            ];
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO `settings` (setting_key, setting_value) VALUES (?, ?)");
            $settings_count = 0;
            foreach ($default_settings as $setting) {
                if ($stmt->execute($setting)) {
                    $settings_count++;
                }
            }
            
            // Insert default themes
            $default_themes = [
                ['default', 'Default Theme', 'Clean and modern default theme', 'System', '1.0.0', NULL, '{"primary_color": "#3b82f6", "secondary_color": "#6b7280", "font_family": "Inter", "button_style": "rounded"}', 1],
                ['dark', 'Dark Professional', 'Professional dark theme with blue accents', 'System', '1.0.0', NULL, '{"primary_color": "#1e40af", "secondary_color": "#374151", "font_family": "Roboto", "button_style": "rounded"}', 0],
                ['corporate', 'Corporate Blue', 'Conservative corporate theme', 'System', '1.0.0', NULL, '{"primary_color": "#1e3a8a", "secondary_color": "#64748b", "font_family": "Open Sans", "button_style": "square"}', 0],
                ['creative', 'Creative Purple', 'Creative and vibrant purple theme', 'System', '1.0.0', NULL, '{"primary_color": "#7c3aed", "secondary_color": "#a855f7", "font_family": "Poppins", "button_style": "rounded"}', 0],
                ['minimal', 'Minimal Gray', 'Clean minimal grayscale theme', 'System', '1.0.0', NULL, '{"primary_color": "#374151", "secondary_color": "#6b7280", "font_family": "Source Sans Pro", "button_style": "square"}', 0]
            ];
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO `themes` (theme_name, theme_display_name, theme_description, theme_author, theme_version, theme_screenshot, theme_config, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $themes_count = 0;
            foreach ($default_themes as $theme) {
                if ($stmt->execute($theme)) {
                    $themes_count++;
                }
            }
            
            // Insert default pages
            $default_pages = [
                ['Home', 'home', 10, 1, 1, 'Welcome to our website - your gateway to professional business solutions'],
                ['About Us', 'about-us', 20, 1, 1, 'Learn more about our company, mission, and the team behind our success'],
                ['Services', 'services', 30, 1, 1, 'Discover our comprehensive range of professional services and solutions'],
                ['Contact Us', 'contact-us', 40, 1, 1, 'Get in touch with us for inquiries, support, or to start your project']
            ];
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO `pages` (title, slug, sort_order, is_default, is_active, meta_description) VALUES (?, ?, ?, ?, ?, ?)");
            $pages_count = 0;
            foreach ($default_pages as $page) {
                if ($stmt->execute($page)) {
                    $pages_count++;
                }
            }
            
            // Insert minimal page sections (just basic hero sections)
            // Insert default page sections
            $default_page_sections = [
                // Home page sections (page_id = 1)
                [1, 'hero', 'Welcome to Our Websites', 'Transform your business with innovative solutions', 'We provide cutting-edge solutions that help businesses grow and succeed in today\'s competitive market. Join thousands of satisfied customers worldwide.', NULL, NULL, NULL, NULL, '#7c3aed', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 1, 10, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [1, 'slider', 'Featured Content', 'Showcase your best content', 'uploads/686625d529889_1751524821.jpg;;;;||uploads/686623e550492_1751524325.jpg;;;;||uploads/686623ec2d159_1751524332.jpg;;;;||uploads/686623f1a49c4_1751524337.jpg;;;;', '', NULL, '', '', '#ffffff', '#000000', 'gradient', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 2, 50, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [1, 'stats', 'Our Impact', 'Numbers that speak for themselves', '500+|Happy Clients|1000+|Projects Completed|24/7|Support Available|99%|Customer Satisfaction', NULL, NULL, NULL, NULL, '#f8fafc', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 3, 20, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                // [1, 'testimonials', 'What Our Clients Say', 'Real feedback from real customers', 'John Smith, CEO of TechCorp|Amazing service and incredible results. Our business grew 300% after working with them.|5|uploads/68674f1012f0f_1751600912.jpg||Sarah Johnson, Marketing Director|Professional team that delivers on promises. Highly recommended for any business.|5|uploads/68674f2157daf_1751600929.jpg||Mike Davis, Founder|Best investment we made for our company. The ROI was incredible.|5|uploads/68674f383e559_1751600952.jpg', '', NULL, '', '', '#f1f5f9', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 4, 40, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                [1, 'testimonials', 'What Our Clients Say', 'Real feedback from real customers', '{"testimonials":[{"name":"John Smith, CEO of TechCorp","quote":"Amazing service and incredible results. Our business grew 300% after working with them.","rating":"5","image":"uploads/6880712871bef_1753248040.png","dateAdded":"2025-07-23T08:05:10.280Z","verified":true},{"name":"Sarah Johnson, Marketing Director","quote":"Professional team that delivers on promises. Highly recommended for any business.","rating":"5","image":"uploads/68674f2157daf_1751600929.jpg","dateAdded":"2025-07-23T08:05:10.280Z","verified":true},{"name":"Mike Davis, Founder","quote":"Best investment we made for our company. The ROI was incredible.","rating":"5","image":"uploads/68674f383e559_1751600952.jpg","dateAdded":"2025-07-23T08:05:10.280Z","verified":true}],"metadata":{"totalTestimonials":3,"lastUpdated":"2025-07-23T08:14:16.619Z","version":"3.0","format":"json","createdBy":"IsolatedTestimonialsManager"}}', '', null, '', '', '#f1f5f9', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 4, 40, 1, null, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [1, 'cta', 'Ready to Get Started?', 'Join thousands of satisfied customers', 'Take your business to the next level with our proven solutions. Contact us today for a free consultation and see the difference we can make.', '', NULL, 'Get Started Now', '', '#354640', '#000000', 'solid', '#714c94', '#a0225b', 'to right', '#ffffff', 5, 30, 1, NULL, '#4b71af', '#ffffff', 'Learn More', '', '#1251ce', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [1, 'features', 'Why Choose Us', '', '{"features":[{"title":"Professional Service","description":"Expert team with years of experience","icon":"fas fa-magic","featureurl":"https://example.com"},{"title":"Professional Service 2","description":"Expert team with years of experience","icon":"fas fa-mobile-alt","featureurl":"https://example.com"},{"title":"Professional Service 3","description":"Expert team with years of experience","icon":"fas fa-mobile-alt","featureurl":"https://example.com"}],"metadata":{"totalFeatures":3,"lastUpdated":"2025-07-23T09:08:20.949Z","version":"2.0"}}', '', null, '', '', '#ffffff', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 6, 60, 1, null, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                // About Us page sections (page_id = 2)
                [2, 'hero', 'About Our Company', 'Building the future, one project at a time', 'Learn more about our mission, vision, and the dedicated team behind our success story.', NULL, NULL, NULL, NULL, '#7c3aed', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 1, 10, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [2, 'timeline', 'Our Journey', 'Key milestones in our growth', '2018|Company Founded|Started with a small team and big dreams|2019|First Major Client|Secured our first enterprise-level contract|2020|Team Expansion|Grew to 25+ talented professionals|2021|International Reach|Expanded services to global markets|2022|Industry Recognition|Won multiple awards for innovation|2023|1000+ Projects|Completed over 1000 successful projects', NULL, NULL, NULL, NULL, '#ffffff', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 4, 20, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [2, 'team', 'Meet Our Team', 'The experts behind your success', 'John Doe|Chief Executive Officer|Leading the company with vision and strategy|uploads/686ba18e51e21_1751884174.jpg|0||Jane Smith|Chief Technology Officer|Overseeing all technical operations and innovation|uploads/68674f383e559_1751600952.jpg|0||Mike Johnson|Head of Marketing|Driving growth through strategic marketing initiatives|uploads/68674f2157daf_1751600929.jpg|0||Sarah Wilson|Lead Developer|Creating amazing digital experiences for our clients|uploads/68674f4aea15e_1751600970.jpg|0', '', NULL, '', '', '#f8fafc', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 3, 30, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [2, 'content', 'Our Story', 'From humble beginnings to industry leaders', 'Founded with a vision to make a difference, we have been serving clients with dedication and excellence for years. Our journey started with a simple belief: that every business deserves access to world-class solutions that drive real results.', NULL, NULL, NULL, NULL, '#ffffff', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 2, 40, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                // Services page sections (page_id = 3)
                [3, 'hero', 'Our Services', 'Comprehensive solutions for your business', 'Discover the range of professional services we offer to help your business thrive in the digital age.', '', NULL, '', '', '#955f5f', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 1, 10, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [3, 'pricing', 'Choose Your Plan', 'Flexible pricing for every budget', 'Starter|9|/month|Perfect for small businesses|1|Basic website|5 pages included|Email support|Standard hosting||Professional|19|/month|Great for growing companies|0|Advanced features|15 pages included|Priority support|Enhanced hosting|Analytics included|Enterprise||Basic|29|/month|For large organizations|0|Custom solutions|Unlimited pages|24/7 phone support|Premium hosting|Advanced analytics', '', NULL, '', '', '#f1f5f9', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 3, 30, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                // [3, 'features', 'What We Offer', 'Complete digital solutions under one roof', 'Professional Service|Expert team with years of experience|fas fa-magic||Professional Service 2|Expert team with years of experience|fas fa-mobile-alt||Professional Service 3|Expert team with years of experience|fas fa-mobile-alt', '', NULL, '', '', '#ffffff', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 2, 20, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                [3, 'features', 'Why Choose Us', '', '{"features":[{"title":"Professional Service","description":"Expert team with years of experience","icon":"fas fa-magic","featureurl":"https://example.com"},{"title":"Professional Service 2","description":"Expert team with years of experience","icon":"fas fa-mobile-alt","featureurl":"https://example.com"},{"title":"Professional Service 3","description":"Expert team with years of experience","icon":"fas fa-mobile-alt","featureurl":"https://example.com"}],"metadata":{"totalFeatures":3,"lastUpdated":"2025-07-23T09:08:20.949Z","version":"2.0"}}', '', null, '', '', '#ffffff', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 6, 60, 1, null, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [3, 'faq', 'Frequently Asked Questions', 'Got questions? We have answers', 'How long does a typical project take?|Most projects are completed within 2-8 weeks, depending on complexity and requirements.|What is included in the pricing?|All plans include hosting, basic support, and regular updates. Additional features vary by plan.|Do you offer custom solutions?|Yes, we specialize in creating custom solutions tailored to your specific business needs.|What kind of support do you provide?|We offer various levels of support from email to 24/7 phone support, depending on your plan.|Can I upgrade my plan later?|Absolutely! You can upgrade your plan at any time to access more features and services.', NULL, NULL, NULL, NULL, '#ffffff', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 4, 40, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                // Contact Us page sections (page_id = 4)
                [4, 'hero', 'Get In Touch', 'Ready to start your project?', 'Contact us today for a free consultation and let\'s discuss how we can help your business grow.', NULL, NULL, NULL, NULL, '#0891b2', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 1, 10, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [4, 'contact_form', 'Send us a Message', 'We\'d love to hear from you', '', NULL, NULL, NULL, NULL, '#ffffff', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 2, 20, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal'],
                
                [4, 'content', 'Visit Our Office', 'Come say hello', 'Founded with a vision to make a difference, we have been serving clients with dedication and excellence for years. Our journey started with a simple belief: that every business deserves access to world-class solutions that drive real results.', NULL, NULL, NULL, NULL, '#f8fafc', '#000000', 'solid', '#3b82f6', '#8b5cf6', 'to right', '#ffffff', 3, 30, 1, NULL, '#3b82f6', '#ffffff', '', '', '#6b7280', '#ffffff', 'solid', 'center', 'horizontal']
            ];
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO `page_sections` (page_id, section_type, title, subtitle, content, image_url, video_url, button_text, button_url, background_color, text_color, background_type, gradient_start, gradient_end, gradient_direction, gradient_text_color, section_order, sort_order, is_active, extra_data, button_bg_color, button_text_color, button_text_2, button_url_2, button_bg_color_2, button_text_color_2, button_style_2, button_alignment, button_layout) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $sections_count = 0;
            foreach ($default_page_sections as $section) {
                if ($stmt->execute($section)) {
                    $sections_count++;
                }
            }
            
            $success = "Database tables created successfully! Created {$success_count} tables, {$settings_count} settings, {$themes_count} themes, {$pages_count} pages, and {$sections_count} page sections.";
            $_SESSION['tables_created'] = true;
            
            // Use JavaScript redirect instead of PHP header
            echo "<script>setTimeout(function() { window.location.href = 'admin.php'; }, 3000);</script>";
            
        } catch (PDOException $e) {
            $error = 'Failed to create tables: ' . $e->getMessage();
            error_log("Database creation error: " . $e->getMessage());
        }
    } else {
        // More detailed error message
        $error = 'Form submission detected but create_tables parameter not found. POST data: ' . json_encode($_POST);
        error_log("Form submission error: " . print_r($_POST, true));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - CMS Installation</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            max-width: 900px; 
            margin: 50px auto; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1); 
        }
        .header { 
            text-align: center; 
            margin-bottom: 40px; 
        }
        .header h1 { 
            color: #2563eb; 
            margin: 0; 
            font-size: 2.8rem; 
            font-weight: 700;
        }
        .header p { 
            color: #6b7280; 
            margin: 15px 0 0 0; 
            font-size: 1.2rem; 
        }
        .step { 
            background: #f8fafc; 
            padding: 30px; 
            margin: 30px 0; 
            border-radius: 10px; 
            border-left: 5px solid #2563eb; 
        }
        .step h3 { 
            color: #1f2937; 
            margin-top: 0; 
            font-size: 1.5rem;
        }
       .btn { 
            background: linear-gradient(135deg, #2563eb, #3b82f6); 
            color: white; 
            padding: 15px 30px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: 600;
            text-decoration: none; 
            display: inline-block; 
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
        }
        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .error { 
            color: #dc2626; 
            padding: 15px; 
            background: #fef2f2; 
            border: 1px solid #fecaca;
            border-radius: 8px; 
            margin: 15px 0;
            font-weight: 500;
        }
        .success { 
            color: #059669; 
            padding: 15px; 
            background: #f0fdf4; 
            border: 1px solid #bbf7d0;
            border-radius: 8px; 
            margin: 15px 0;
            font-weight: 500;
        }
        .progress { 
            width: 100%; 
            background: #e5e7eb; 
            border-radius: 10px; 
            margin: 25px 0; 
            height: 12px; 
            overflow: hidden;
        }
        .progress-bar { 
            height: 100%; 
            background: linear-gradient(90deg, #2563eb, #3b82f6); 
            border-radius: 10px; 
            transition: width 0.6s ease;
            position: relative;
        }
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-box h4 {
            color: #0369a1;
            margin-top: 0;
        }
        .table-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .table-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .table-item h5 {
            color: #2563eb;
            margin: 0 0 8px 0;
            font-size: 1rem;
        }
        .table-item p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
        }
        .loading {
            display: none;
            align-items: center;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #e5e7eb;
            border-top: 2px solid #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .debug {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏗️ Database Setup</h1>
            <p>Creating your CMS database structure</p>
        </div>
        
        <div class="progress">
            <div class="progress-bar" style="width: 60%"></div>
        </div>
        <p><strong>Step 3 of 5:</strong> Create Database Tables</p>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?> Redirecting to admin setup...</div>
            <div class="loading" style="display: flex;">
                <div class="spinner"></div>
                <span>Creating admin account...</span>
            </div>
        <?php endif; ?>
        
        <div class="step">
            <h3>📊 Database Configuration Summary</h3>
            <div class="info-box">
                <h4>🔗 Connection Details</h4>
                <p><strong>Host:</strong> <?= htmlspecialchars($db_config['host']) ?></p>
                <p><strong>Database:</strong> <?= htmlspecialchars($db_config['dbname']) ?></p>
                <p><strong>Username:</strong> <?= htmlspecialchars($db_config['username']) ?></p>
                <p><strong>Status:</strong> <span style="color: #059669;">✅ Connected Successfully</span></p>
            </div>
            
            <h4>🗃️ Tables to be Created</h4>
            <div class="table-list">
                <div class="table-item">
                    <h5>👤 admins</h5>
                    <p>Administrator accounts and authentication</p>
                </div>
                <div class="table-item">
                    <h5>📄 pages</h5>
                    <p>Website pages and navigation structure</p>
                </div>
                <div class="table-item">
                    <h5>🧩 page_sections</h5>
                    <p>Dynamic content sections for each page</p>
                </div>
                <div class="table-item">
                    <h5>🔍 page_seo</h5>
                    <p>SEO metadata for search optimization</p>
                </div>
                <div class="table-item">
                    <h5>⚙️ settings</h5>
                    <p>System configuration and preferences</p>
                </div>
                <div class="table-item">
                    <h5>📧 contact_submissions</h5>
                    <p>Contact form submissions and inquiries</p>
                </div>
                <div class="table-item">
                    <h5>📰 newsletter_subscribers</h5>
                    <p>Email newsletter subscription management</p>
                </div>
                <div class="table-item">
                    <h5>🖼️ uploaded_images</h5>
                    <p>Media library and file management</p>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">
                <a href="database.php" class="btn-secondary">← Back to Database Config</a>
                <form method="POST" style="margin: 0;" id="createTablesForm" onsubmit="return handleFormSubmit(event)">
                    <input type="hidden" name="create_tables" value="1" id="createTablesInput">
                    <button type="submit" name="submit_create_tables" value="create" class="btn" id="createBtn">
                        <span id="btnText">🚀 Create Database Tables</span>
                        <div class="loading" id="btnLoading" style="display: none;">
                            <div class="spinner"></div>
                            <span>Creating tables...</span>
                        </div>
                    </button>
                </form>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; color: #6b7280;">
            <p>⚡ This process will create all necessary database tables and default content</p>
        </div>
    </div>

    <script>
        function handleFormSubmit(event) {
            event.preventDefault();

            console.log('Form submission started');

            // Disable the button and show the loading spinner
            const btn = document.getElementById('createBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');

            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';

            // Submit the form after slight delay to allow UI update
            setTimeout(() => {
                document.getElementById('createTablesForm').submit();
            }, 300);
            
            return false;
        }
    </script>
</body>
</html>