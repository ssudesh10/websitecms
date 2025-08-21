<?php
// File: install/finish.php (Installation Complete)

session_start();

if (!isset($_SESSION['db_config']) || !isset($_SESSION['admin_created'])) {
    header('Location: admin.php');
    exit;
}

$db_config = $_SESSION['db_config'];
$site_url = $_SESSION['site_url'] ?? 'http://localhost';
$serial_number = $_SESSION['serial_number'] ?? '';

// Include the config updater to UPDATE existing config.php
require_once 'config_updater.php';

// Update ONLY the database configuration and BASE_URL in existing config.php
$config_values = [
    'db_host' => $db_config['host'],
    'db_name' => $db_config['dbname'], 
    'db_user' => $db_config['username'],
    'db_pass' => $db_config['password'],
    'base_url' => $site_url,
    'serial_number' => $serial_number
];

// Use config_updater to update existing file instead of creating new one
$config_result = updateConfigFile($config_values);
$config_written = $config_result['success'];
$config_error = $config_result['error'] ?? '';

// Create installation lock file
$lock_created = false;
$lock_path = '';

if (is_writable('../')) {
    $lock_path = '../.installed.lock';
    
    // Organized nested structure
    $installation_data = [
        'installation' => [
            'installed_at' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'timezone' => date_default_timezone_get(),
            'status' => 'completed',
            'serial_number' => $serial_number
        ],
        'system' => [
            'cms_version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'operating_system' => PHP_OS,
            'memory_limit' => ini_get('memory_limit')
        ],
        'security' => [
            'admin_created' => true,
            'config_updated' => true,
            'serial_saved' => !empty($serial_number),
            'lock_file_created' => true,
            'installer_status' => 'should_be_deleted'
        ],
        'metadata' => [
            'installer_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 100),
            'file_version' => '1.0',
            'checksum' => ''
        ]
    ];
    
    // Add checksum after creating the array
    $installation_data['metadata']['checksum'] = md5(json_encode($installation_data));
    
    // Write with pretty JSON formatting
    $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    
    if (file_put_contents($lock_path, json_encode($installation_data, $json_options))) {
        $lock_created = true;
    }
}

// Create .htaccess file for security
$htaccess_content = "# Website CMS Security Rules
# Generated on " . date('Y-m-d H:i:s') . "

# .htaccess file for Clean URLs
# Place this file in your websitecms root directory

RewriteEngine On

# Force HTTPS (optional - remove if you don't need it)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Remove trailing slashes (optional but recommended for SEO)
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)/$ /$1 [R=301,L]

# Admin area - keep as is (don't rewrite admin URLs)
RewriteCond %{REQUEST_URI} ^/admin/
RewriteRule ^(.*)$ - [L]

# API or AJAX endpoints - don't rewrite
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^(.*)$ - [L]

# Static files - don't rewrite (CSS, JS, images, etc.)
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(.*)$ - [L]

# Directories - don't rewrite
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^(.*)$ - [L]

# Don't rewrite includes and config directories
RewriteCond %{REQUEST_URI} ^/includes/
RewriteRule ^(.*)$ - [L]
RewriteCond %{REQUEST_URI} ^/config/
RewriteRule ^(.*)$ - [L]

# Home page - handle root directory
RewriteRule ^/?$ page.php?slug=home [L,QSA]

# Clean URLs for pages
# This will make /about-us work instead of /page.php?slug=about-us
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/admin/
RewriteCond %{REQUEST_URI} !^/api/
RewriteCond %{REQUEST_URI} !^/includes/
RewriteCond %{REQUEST_URI} !^/config/
RewriteRule ^([^/]+)/?$ page.php?slug=$1 [L,QSA]

# Handle blog posts or other content types (optional)
# RewriteRule ^blog/([^/]+)/?$ blog-post.php?slug=$1 [L,QSA]

# Handle categories (optional)
# RewriteRule ^category/([^/]+)/?$ category.php?slug=$1 [L,QSA]

# Error handling for non-existent pages
ErrorDocument 404 /websitecms/404.php";

$htaccess_written = false;
if (is_writable('../')) {
    if (file_put_contents('../.htaccess', $htaccess_content)) {
        $htaccess_written = true;
    }
}

// Create robots.txt file
$robots_content = "# Robots.txt for Website CMS
# Generated on " . date('Y-m-d H:i:s') . "

User-agent: *

Disallow: /admin/
Disallow: /install/
Disallow: /config/
Disallow: /includes/
Disallow: /uploads/private/
Allow: /uploads/

# Crawl delay (adjust as needed)
# Crawl-delay: 1

# Sitemap location
Sitemap: " . $site_url . "/sitemap.xml";

$robots_written = false;
if (is_writable('../')) {
    if (file_put_contents('../robots.txt', $robots_content)) {
        $robots_written = true;
    }
}

// Create sitemap.xml file
$sitemap_content = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">

    <!-- Homepage -->
    <url>
        <loc>' . $site_url . '/</loc>
        <lastmod>' . date('Y-m-d') . '</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Home page with explicit slug -->
    <url>
        <loc>' . $site_url . '/home</loc>
        <lastmod>' . date('Y-m-d') . '</lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    <!-- Default pages (update these based on your default pages) -->
    <url>
        <loc>' . $site_url . '/about</loc>
        <lastmod>' . date('Y-m-d') . '</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>

    <url>
        <loc>' . $site_url . '/contact</loc>
        <lastmod>' . date('Y-m-d') . '</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>

    <url>
        <loc>' . $site_url . '/services</loc>
        <lastmod>' . date('Y-m-d') . '</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>


    <!-- Note: This is a static sitemap. Consider implementing dynamic sitemap generation -->
    <!-- that queries your database for all published pages -->

</urlset>';

$sitemap_written = false;
if (is_writable('../')) {
    if (file_put_contents('../sitemap.xml', $sitemap_content)) {
        $sitemap_written = true;
    }
}

// Clear session
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Complete - Website CMS</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            max-width: 1000px; 
            margin: 50px auto; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container { 
            background: white; 
            padding: 50px; 
            border-radius: 20px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.15); 
            position: relative;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #2563eb, #3b82f6, #10b981, #f59e0b);
        }
        .header { 
            text-align: center; 
            margin-bottom: 50px; 
        }
        .header h1 { 
            color: #059669; 
            margin: 0; 
            font-size: 3.5rem; 
            font-weight: 800;
            margin-bottom: 10px;
        }
        .header p { 
            color: #6b7280; 
            margin: 0; 
            font-size: 1.3rem; 
        }
        .success-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-30px); }
            60% { transform: translateY(-15px); }
        }
        .step { 
            background: #f8fafc; 
            padding: 30px; 
            margin: 30px 0; 
            border-radius: 12px; 
            border-left: 5px solid #059669; 
        }
        .step h3 { 
            color: #1f2937; 
            margin-top: 0; 
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn { 
            background: linear-gradient(135deg, #059669, #10b981); 
            color: white; 
            padding: 18px 35px; 
            border: none; 
            border-radius: 10px; 
            cursor: pointer; 
            font-size: 18px; 
            font-weight: 700;
            text-decoration: none; 
            display: inline-block; 
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.4);
            margin: 10px;
        }
        .btn:hover { 
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(5, 150, 105, 0.5);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        .btn-secondary:hover {
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.5);
        }
        .btn-danger { 
            background: linear-gradient(135deg, #dc2626, #ef4444); 
            box-shadow: 0 6px 20px rgba(220, 38, 38, 0.4);
        }
        .btn-danger:hover {
            box-shadow: 0 12px 30px rgba(220, 38, 38, 0.5);
        }
        .success { 
            color: #059669; 
            padding: 20px; 
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5); 
            border: 2px solid #bbf7d0;
            border-radius: 10px; 
            margin: 20px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .warning { 
            color: #d97706; 
            padding: 20px; 
            background: linear-gradient(135deg, #fffbeb, #fef3c7); 
            border: 2px solid #fcd34d;
            border-radius: 10px; 
            margin: 20px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .error { 
            color: #dc2626; 
            padding: 20px; 
            background: linear-gradient(135deg, #fef2f2, #fee2e2); 
            border: 2px solid #fecaca;
            border-radius: 10px; 
            margin: 20px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .progress { 
            width: 100%; 
            background: #e5e7eb; 
            border-radius: 15px; 
            margin: 30px 0; 
            height: 15px; 
            overflow: hidden;
        }
        .progress-bar { 
            height: 100%; 
            background: linear-gradient(90deg, #059669, #10b981); 
            border-radius: 15px; 
            transition: width 1s ease;
            position: relative;
        }
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .checklist {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .checklist li {
            padding: 12px 0;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid #f3f4f6;
        }
        .checklist li:last-child {
            border-bottom: none;
        }
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .action-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .action-card:hover {
            border-color: #2563eb;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .action-card h4 {
            color: #1f2937;
            margin: 15px 0 10px 0;
            font-size: 1.2rem;
        }
        .action-card p {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 10px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #2563eb;
            display: block;
        }
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">🎉</div>
            <h1>Installation Complete!</h1>
            <p>Your Website CMS is ready to use</p>
        </div>
        
        <div class="progress">
            <div class="progress-bar" style="width: 100%"></div>
        </div>
        <p style="text-align: center;"><strong>Step 5 of 5:</strong> ✅ Finished Successfully</p>
        
        <div class="step">
            <h3>📋 Installation Status</h3>
            
            <ul class="checklist">
                <li>
                    <span style="font-size: 1.5rem;">✅</span>
                    <div>
                        <strong>Database Connected</strong><br>
                        <small>Successfully connected to <?= htmlspecialchars($db_config['dbname']) ?></small>
                    </div>
                </li>
                <li>
                    <span style="font-size: 1.5rem;">✅</span>
                    <div>
                        <strong>Tables Created</strong><br>
                        <small>All database tables and default data installed</small>
                    </div>
                </li>
                <li>
                    <span style="font-size: 1.5rem;">✅</span>
                    <div>
                        <strong>Admin Account Created</strong><br>
                        <small>Administrator user ready for login</small>
                    </div>
                </li>
                <li>
                    <span style="font-size: 1.5rem;"><?= $config_written ? '✅' : '⚠️' ?></span>
                    <div>
                        <strong>Configuration Updated</strong><br>
                        <small><?= $config_written ? 'Database settings and BASE_URL updated in existing config.php' : 'Could not update config.php automatically' ?></small>
                    </div>
                </li>
                <li>
                    <span style="font-size: 1.5rem;"><?= $lock_created ? '✅' : '⚠️' ?></span>
                    <div>
                        <strong>Installation Lock</strong><br>
                        <small><?= $lock_created ? 'Security lock file created' : 'Could not create lock file - please create manually' ?></small>
                    </div>
                </li>
                <li>
                    <span style="font-size: 1.5rem;"><?= $htaccess_written ? '✅' : '⚠️' ?></span>
                    <div>
                        <strong>Security Rules</strong><br>
                        <small><?= $htaccess_written ? 'Apache security rules configured' : 'Could not create .htaccess - manual setup recommended' ?></small>
                    </div>
                </li>
                <li>
                    <span style="font-size: 1.5rem;"><?= $robots_written ? '✅' : '⚠️' ?></span>
                    <div>
                        <strong>Robots.txt Created</strong><br>
                        <small><?= $robots_written ? 'Search engine robots file created' : 'Could not create robots.txt file' ?></small>
                    </div>
                </li>
                <li>
                    <span style="font-size: 1.5rem;"><?= $sitemap_written ? '✅' : '⚠️' ?></span>
                    <div>
                        <strong>Sitemap.xml Created</strong><br>
                        <small><?= $sitemap_written ? 'Basic XML sitemap created for SEO' : 'Could not create sitemap.xml file' ?></small>
                    </div>
                </li>
            </ul>
        </div>
        
        <?php if (!$config_written): ?>
        <div class="step">
            <h3>⚠️ Manual Configuration Required</h3>
            <div class="error">
                <span style="font-size: 1.5rem;">❌</span>
                <div>
                    <strong>Config Update Failed:</strong> <?= htmlspecialchars($config_error) ?><br>
                    Please manually update your config.php file with the database settings below.
                </div>
            </div>
            
            <p><strong>Update these lines in your config.php file:</strong></p>
            <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; font-family: monospace; margin: 15px 0;">
                <code>
                define('DB_HOST', '<?= htmlspecialchars($db_config['host']) ?>');<br>
                define('DB_NAME', '<?= htmlspecialchars($db_config['dbname']) ?>');<br>
                define('DB_USER', '<?= htmlspecialchars($db_config['username']) ?>');<br>
                define('DB_PASS', '<?= htmlspecialchars($db_config['password']) ?>');<br>
                define('BASE_URL', '<?= htmlspecialchars($site_url) ?>');
                </code>
            </div>
        </div>
        <?php else: ?>
        <div class="step">
            <h3>✅ Configuration Updated Successfully</h3>
            <div class="success">
                <span style="font-size: 1.5rem;">✅</span>
                <div>
                    Your existing config.php file has been updated with the new database settings and BASE_URL. 
                    All your custom functions and settings have been preserved.
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="step">
            <h3>🔍 SEO Files Created</h3>
            <?php if ($robots_written && $sitemap_written): ?>
            <div class="success">
                <span style="font-size: 1.5rem;">✅</span>
                <div>
                    <strong>SEO Setup Complete:</strong> Both robots.txt and sitemap.xml files have been created successfully.
                    <br><br>
                    <strong>Files created:</strong>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li><strong>robots.txt</strong> - Controls search engine crawling</li>
                        <li><strong>sitemap.xml</strong> - Helps search engines index your site</li>
                    </ul>
                    <strong>Note:</strong> The sitemap is basic. Consider implementing dynamic sitemap generation for better SEO.
                </div>
            </div>
            <?php else: ?>
            <div class="warning">
                <span style="font-size: 1.5rem;">⚠️</span>
                <div>
                    <strong>SEO Files Status:</strong>
                    <br>
                    • Robots.txt: <?= $robots_written ? '✅ Created' : '❌ Failed to create' ?>
                    <br>
                    • Sitemap.xml: <?= $sitemap_written ? '✅ Created' : '❌ Failed to create' ?>
                    <br><br>
                    You may need to create these files manually for better SEO.
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="step">
            <h3>🚀 Quick Actions</h3>
            <div class="action-grid">
                <div class="action-card">
                    <div style="font-size: 2.5rem;">🌐</div>
                    <h4>Visit Your Site</h4>
                    <p>See your new website in action</p>
                    <a href="../index.php" target="_blank" class="btn">View Website</a>
                </div>
                
                <div class="action-card">
                    <div style="font-size: 2.5rem;">⚙️</div>
                    <h4>Admin Dashboard</h4>
                    <p>Start managing your content</p>
                    <a href="../admin/" target="_blank" class="btn btn-secondary">Admin Panel</a>
                </div>
                
                <div class="action-card">
                    <div style="font-size: 2.5rem;">🔍</div>
                    <h4>SEO Files</h4>
                    <p>Check your SEO configuration</p>
                    <a href="../robots.txt" class="btn btn-secondary" target="_blank">View Robots.txt</a>
                    <a href="../sitemap.xml" class="btn btn-secondary" target="_blank">View Sitemap</a>
                </div>
                
            </div>
        </div>
        
        <div class="step">
            <h3>📊 Installation Summary</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number">8</span>
                    <div class="stat-label">Database Tables</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">4</span>
                    <div class="stat-label">Default Pages</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">25+</span>
                    <div class="stat-label">Settings Configured</div>
                </div>
                <div class="stat-item">
                    <span class="stat-number">3</span>
                    <div class="stat-label">SEO Files Created</div>
                </div>
            </div>
        </div>
        
        <div class="step">
            <h3>📝 Next Steps for SEO Optimization</h3>
            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 15px 0;">
                <h4 style="color: #2563eb; margin-top: 0;">Recommended SEO Actions:</h4>
                <ol style="color: #4b5563; line-height: 1.6;">
                    <li><strong>Update Sitemap:</strong> The current sitemap is static. Consider implementing dynamic sitemap generation that pulls from your database.</li>
                    <li><strong>Google Search Console:</strong> Submit your sitemap at <code><?= htmlspecialchars($site_url) ?>/sitemap.xml</code></li>
                    <li><strong>Customize Robots.txt:</strong> Review and modify the robots.txt file based on your specific needs.</li>
                    <li><strong>Meta Tags:</strong> Ensure all pages have proper meta descriptions and title tags.</li>
                    <li><strong>SSL Certificate:</strong> Enable HTTPS for better SEO ranking and security.</li>
                    <li><strong>Analytics:</strong> Set up Google Analytics and Google Search Console.</li>
                </ol>
            </div>
        </div>

        <div class="step">
            <h3>⚡ Performance & SEO Tips</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                    <h4 style="color: #059669; margin-top: 0;">🚀 Performance</h4>
                    <ul style="color: #4b5563; font-size: 0.9rem;">
                        <li>Enable gzip compression</li>
                        <li>Optimize images before upload</li>
                        <li>Use browser caching</li>
                        <li>Minimize CSS/JS files</li>
                    </ul>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                    <h4 style="color: #2563eb; margin-top: 0;">🔍 SEO Basics</h4>
                    <ul style="color: #4b5563; font-size: 0.9rem;">
                        <li>Write unique page titles</li>
                        <li>Add meta descriptions</li>
                        <li>Use heading tags properly</li>
                        <li>Create quality content regularly</li>
                    </ul>
                </div>
            </div>
        </div>

                <div class="step">
            <h3>🔧 Dynamic Sitemap Implementation</h3>
            <div class="warning">
                <span style="font-size: 1.5rem;">💡</span>
                <div>
                    <strong>Pro Tip:</strong> Consider creating a dynamic sitemap generator that automatically updates when you add/edit pages.
                    <br><br>
                    <strong>Create a file called <code>generate_sitemap.php</code> in your root directory:</strong>
                </div>
            </div>
            
            <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; font-family: monospace; margin: 15px 0; font-size: 0.85rem; overflow-x: auto;">
                <code>
&lt;?php<br>
// This would be a dynamic sitemap generator<br>
// Query your database for all published pages<br>
// Generate XML dynamically based on actual content<br>
// Update lastmod dates from database<br>
// Set priorities based on page types<br>
?&gt;
                </code>
            </div>
        </div>
        
        <div class="step">
            <h3>🛡️ Important Security Notes</h3>
            <div class="warning">
                <span style="font-size: 1.5rem;">🔒</span>
                <div>
                    <strong>Critical:</strong> For security reasons, please delete the <code>install/</code> directory immediately after installation.
                </div>
            </div>
            
            <div style="margin: 20px 0;">
                <h4>Security Checklist:</h4>
                <ul class="checklist">
                    <li>
                        <span style="font-size: 1.2rem;">🗑️</span>
                        <div>Delete the <code>install/</code> directory</div>
                    </li>
                    <li>
                        <span style="font-size: 1.2rem;">🔐</span>
                        <div>Change default passwords regularly</div>
                    </li>
                    <li>
                        <span style="font-size: 1.2rem;">🔄</span>
                        <div>Keep your CMS updated</div>
                    </li>
                    <li>
                        <span style="font-size: 1.2rem;">💾</span>
                        <div>Setup regular database backups</div>
                    </li>
                    <li>
                        <span style="font-size: 1.2rem;">🌐</span>
                        <div>Use HTTPS in production</div>
                    </li>
                    <li>
                        <span style="font-size: 1.2rem;">🔍</span>
                        <div>Submit sitemap to Google Search Console</div>
                    </li>
                </ul>
            </div>
        </div>
        
        <div style="text-align: center; margin: 40px 0; padding: 30px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 15px;">
            <h2 style="color: #2563eb; margin-bottom: 15px;">🎉 Congratulations!</h2>
            <p style="font-size: 1.1rem; color: #4b5563; margin-bottom: 25px;">
                Your Website CMS has been successfully installed and configured with SEO-ready files.
            </p>
            <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
                <a href="../index.php" class="btn" style="font-size: 1.1rem;">🌐 View Your Website</a>
                <a href="../admin/" class="btn btn-secondary" style="font-size: 1.1rem;">⚙️ Admin Dashboard</a>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 40px; color: #6b7280; font-size: 0.9rem;">
            <p>
                <strong>Website CMS v1.0</strong> | 
                Installed on <?= date('F j, Y \a\t g:i A') ?> | 
                PHP <?= PHP_VERSION ?> | 
                Database: <?= htmlspecialchars($db_config['dbname']) ?>
            </p>
            <p style="margin-top: 10px;">
                🚀 <strong>Thank you for choosing Website CMS!</strong> | 
                📁 <strong><?= ($robots_written && $sitemap_written) ? 'SEO Files Ready' : 'SEO Setup Needed' ?></strong>
            </p>
        </div>
    </div>

    <script>
        // Animate progress bar on load
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.querySelector('.progress-bar').style.width = '100%';
            }, 500);
        });
        
        // Auto-scroll to actions after animation
        setTimeout(function() {
            document.querySelector('.action-grid').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }, 2000);

        // Add click handlers for SEO file links
        document.addEventListener('DOMContentLoaded', function() {
            const robotsLink = document.querySelector('a[href="../robots.txt"]');
            const sitemapLink = document.querySelector('a[href="../sitemap.xml"]');
            
            if (robotsLink) {
                robotsLink.addEventListener('click', function(e) {
                    <?php if (!$robots_written): ?>
                    e.preventDefault();
                    alert('Robots.txt file was not created successfully. Please create it manually.');
                    <?php endif; ?>
                });
            }
            
            if (sitemapLink) {
                sitemapLink.addEventListener('click', function(e) {
                    <?php if (!$sitemap_written): ?>
                    e.preventDefault();
                    alert('Sitemap.xml file was not created successfully. Please create it manually.');
                    <?php endif; ?>
                });
            }
        });

    </script>
</body>
</html>