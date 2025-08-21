<?php
declare(strict_types=1);

// Include config.php first to get database connection and settings
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/function.php';

// Get dynamic settings for maintenance page
$siteName = getSetting('site_name', 'Your Website');
$siteDescription = getSetting('site_description', 'Creating amazing digital experiences for businesses worldwide.');
$contactEmail = getSetting('contact_email', 'info@yourwebsite.com');
$contactPhone = getSetting('contact_phone', '+1 (555) 123-4567');

// Get site logo settings
$siteLogoEnabled = getSetting('site_logo_enabled', '0');
$siteLogoUrl = getSetting('site_logo_url', '');

// Get favicon settings
$faviconEnabled = getSetting('favicon_enabled', '0');
$faviconUrl = getSetting('favicon_url', '');

// Get social media settings
$facebookUrl = getSetting('facebook_url', '');
$twitterUrl = getSetting('twitter_url', '');
$linkedinUrl = getSetting('linkedin_url', '');
$instagramUrl = getSetting('instagram_url', '');
$youtubeUrl = getSetting('youtube_url', '');

// Get custom maintenance settings
$maintenanceMessage = getSetting('maintenance_message', 'We are currently performing scheduled maintenance to improve your experience.');
$maintenanceEta = getSetting('maintenance_eta', '');

// Set proper HTTP status code
http_response_code(503);
header('Retry-After: 3600'); // Retry after 1 hour

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - <?= htmlspecialchars($siteName) ?></title>
    <meta name="description" content="<?= htmlspecialchars($siteName) ?> is currently under maintenance. We'll be back soon!">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon -->
    <?php if ($faviconEnabled === '1' && !empty($faviconUrl) && file_exists($faviconUrl)): ?>
        <?php
        $faviconPath = BASE_URL . '/' . $faviconUrl;
        $cacheParam = '?v=' . filemtime($faviconUrl);
        ?>
        <link rel="icon" href="<?= $faviconPath . $cacheParam ?>">
        <link rel="shortcut icon" href="<?= $faviconPath . $cacheParam ?>">
    <?php endif; ?>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            color: #333;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .logo {
            margin-bottom: 2rem;
        }
        
        .logo img {
            height: 60px;
            max-width: 200px;
            object-fit: contain;
        }
        
        .logo h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }
        
        .icon i {
            font-size: 2rem;
            color: white;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        
        .message {
            font-size: 1rem;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .eta {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #0369a1;
            font-weight: 500;
        }
        
        .eta i {
            margin-right: 0.5rem;
        }
        
        .contact {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        
        .contact a {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 12px;
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .contact a:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        .contact i {
            margin-right: 0.5rem;
            color: #667eea;
        }
        
        .social {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .social a {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            transition: all 0.2s;
        }
        
        .social a:hover {
            transform: translateY(-2px);
        }
        
        .social .facebook { background: #1877f2; }
        .social .twitter { background: #1da1f2; }
        .social .linkedin { background: #0a66c2; }
        .social .instagram { background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); }
        .social .youtube { background: #ff0000; }
        
        .footer {
            font-size: 0.875rem;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 1.5rem;
        }
        
        .refresh-notice {
            font-size: 0.875rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .refresh-notice i {
            margin-right: 0.5rem;
            animation: spin 2s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .contact {
                grid-template-columns: 1fr;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .logo h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Logo/Site Name -->
        <div class="logo">
            <?php if ($siteLogoEnabled === '1' && !empty($siteLogoUrl) && file_exists($siteLogoUrl)): ?>
                <img src="<?= BASE_URL ?>/<?= htmlspecialchars($siteLogoUrl) ?>?v=<?= filemtime($siteLogoUrl) ?>" 
                     alt="<?= htmlspecialchars($siteName) ?>">
            <?php else: ?>
                <h1><?= htmlspecialchars($siteName) ?></h1>
            <?php endif; ?>
        </div>
        
        <!-- Maintenance Icon -->
        <div class="icon">
            <i class="fas fa-cog"></i>
        </div>
        
        <!-- Main Message -->
        <h2>Under Maintenance</h2>
        
        <p class="message">
            <?= htmlspecialchars($maintenanceMessage) ?>
        </p>
        
        <!-- ETA -->
        <?php if (!empty($maintenanceEta)): ?>
        <div class="eta">
            <i class="fas fa-clock"></i>
            Expected completion: <?= htmlspecialchars($maintenanceEta) ?>
        </div>
        <?php endif; ?>
        
        <!-- Contact Information -->
        <div class="contact">
            <a href="mailto:<?= htmlspecialchars($contactEmail) ?>">
                <i class="fas fa-envelope"></i>
                Email
            </a>
            <a href="tel:<?= htmlspecialchars(str_replace([' ', '(', ')', '-'], '', $contactPhone)) ?>">
                <i class="fas fa-phone"></i>
                Call
            </a>
        </div>
        
        <!-- Social Media Links -->
        <?php if ($facebookUrl || $twitterUrl || $linkedinUrl || $instagramUrl || $youtubeUrl): ?>
        <div class="social">
            <?php if ($facebookUrl): ?>
            <a href="<?= htmlspecialchars($facebookUrl) ?>" target="_blank" class="facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            <?php endif; ?>
            
            <?php if ($twitterUrl): ?>
            <a href="<?= htmlspecialchars($twitterUrl) ?>" target="_blank" class="twitter">
                <i class="fab fa-twitter"></i>
            </a>
            <?php endif; ?>
            
            <?php if ($linkedinUrl): ?>
            <a href="<?= htmlspecialchars($linkedinUrl) ?>" target="_blank" class="linkedin">
                <i class="fab fa-linkedin-in"></i>
            </a>
            <?php endif; ?>
            
            <?php if ($instagramUrl): ?>
            <a href="<?= htmlspecialchars($instagramUrl) ?>" target="_blank" class="instagram">
                <i class="fab fa-instagram"></i>
            </a>
            <?php endif; ?>
            
            <?php if ($youtubeUrl): ?>
            <a href="<?= htmlspecialchars($youtubeUrl) ?>" target="_blank" class="youtube">
                <i class="fab fa-youtube"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Auto-refresh notice -->
        <div class="refresh-notice">
            <i class="fas fa-sync-alt"></i>
            Auto-refresh in 5 minutes
        </div>
        
        <!-- Footer -->
        <div class="footer">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. All rights reserved.
        </div>
    </div>
    
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(() => window.location.reload(), 300000);
        
        // Prevent right-click and F12
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && e.key === 'I')) {
                e.preventDefault();
            }
        });
        
        // Simple hover effects
        document.querySelectorAll('.contact a, .social a').forEach(link => {
            link.addEventListener('mouseenter', () => {
                link.style.transform = 'translateY(-2px)';
            });
            link.addEventListener('mouseleave', () => {
                link.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>