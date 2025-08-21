<?php
// admin/create-settings-table.php
require_once '../config.php';
require_once '../function.php';
requireLogin();

try {
    // Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    echo "<h2>✓ Settings table created successfully!</h2>";
    
    // Insert default settings
    $defaultSettings = [
        'site_name' => 'Your Website',
        'site_tagline' => 'Transform your business with innovative solutions',
        'site_description' => 'Creating amazing digital experiences for businesses worldwide. We build modern, responsive websites that drive results.',
        'admin_email' => 'admin@yourwebsite.com',
        'timezone' => 'UTC',
        'maintenance_mode' => '0',
        'contact_email' => 'info@yourwebsite.com',
        'contact_phone' => '+1 (555) 123-4567',
        'contact_address' => '123 Business St, City, State',
        'business_hours' => "Mon-Fri: 9:00 AM - 5:00 PM\nSat: 10:00 AM - 3:00 PM\nSun: Closed",
        'facebook_url' => '',
        'twitter_url' => '',
        'linkedin_url' => '',
        'instagram_url' => '',
        'youtube_url' => '',
        'default_meta_title' => 'Your Website - Professional Business Solutions',
        'default_meta_description' => 'Transform your business with our innovative solutions. Professional services designed to help your business grow and succeed.',
        'default_meta_keywords' => 'business, solutions, professional, services',
        'google_analytics_id' => '',
        'google_search_console' => '',
        'max_upload_size' => '5',
        'allowed_file_types' => 'jpg,jpeg,png,gif,webp,svg',
        'items_per_page' => '10',
        'enable_comments' => '0',
        'cache_enabled' => '0'
    ];
    
    foreach ($defaultSettings as $key => $value) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
    
    echo "<p>✓ Default settings inserted successfully!</p>";
    echo "<p><strong>Setup completed!</strong></p>";
    echo "<p><a href='settings.php'>Go to Settings Page →</a></p>";
    echo "<p><a href='index.php'>← Back to Admin Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Error creating settings table:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
h2 { color: #333; }
p { margin: 10px 0; }
a { color: #0066cc; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>