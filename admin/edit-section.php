<?php
require_once '../config.php';
require_once '../function.php';
requireLogin();

// ===== SECURITY VALIDATION FUNCTIONS =====

function validateSectionId($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => PHP_INT_MAX
        ]
    ]);
    
    if ($id === false) {
        throw new InvalidArgumentException("Invalid section ID provided");
    }
    
    return $id;
}

function validateTextInput($input, $maxLength = 1000, $allowEmpty = false) {
    if (!$allowEmpty && empty(trim($input))) {
        return false;
    }
    
    // Remove null bytes and normalize
    $input = str_replace("\0", '', $input);
    $input = trim($input);
    
    // Check length
    if (strlen($input) > $maxLength) {
        throw new InvalidArgumentException("Input exceeds maximum length of {$maxLength} characters");
    }
    
    return $input;
}

function validateHexColor($color) {
    if (empty($color)) {
        return '#ffffff';
    }
    
    $color = preg_replace('/[^#0-9A-Fa-f]/', '', $color);
    
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        return '#ffffff';
    }
    
    return $color;
}

function validateUrl($url, $allowEmpty = true) {
    if (empty(trim($url)) && $allowEmpty) {
        return '';
    }
    
    $url = str_replace("\0", '', trim($url));
    
    if (!filter_var($url, FILTER_VALIDATE_URL) && !empty($url)) {
        if (!preg_match('/^(\/|uploads\/|https?:\/\/)/', $url)) {
            throw new InvalidArgumentException("Invalid URL format");
        }
    }
    
    return $url;
}

function validateBackgroundType($type) {
    $allowedTypes = ['solid', 'gradient', 'image'];
    
    if (!in_array($type, $allowedTypes, true)) {
        return 'solid';
    }
    
    return $type;
}

function validateGradientDirection($direction) {
    $allowedDirections = ['to right', 'to left', 'to bottom', 'to top', '45deg', '135deg'];
    
    if (!in_array($direction, $allowedDirections, true)) {
        return 'to right';
    }
    
    return $direction;
}

function validateButtonAlignment($alignment) {
    $allowedAlignments = ['left', 'center', 'right'];
    
    if (!in_array($alignment, $allowedAlignments, true)) {
        return 'center';
    }
    
    return $alignment;
}

function validateButtonLayout($layout) {
    $allowedLayouts = ['horizontal', 'vertical'];
    
    if (!in_array($layout, $allowedLayouts, true)) {
        return 'horizontal';
    }
    
    return $layout;
}

function validateButtonStyle($style) {
    $allowedStyles = ['solid', 'outline', 'ghost'];
    
    if (!in_array($style, $allowedStyles, true)) {
        return 'solid';
    }
    
    return $style;
}

function logSecurityEvent($event, $details = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    error_log("SECURITY EVENT: " . json_encode($logData));
}

// ===== SECURE INITIALIZATION =====

$error = '';
$success = '';

// Validate section ID with proper error handling
try {
    $sectionId = validateSectionId($_GET['id'] ?? 0);
} catch (InvalidArgumentException $e) {
    logSecurityEvent("Invalid section ID attempt", ['id' => $_GET['id'] ?? 'null']);
    header('Location: ' . BASE_URL . '/admin/pages.php?error=invalid_section');
    exit;
}

// Get section data with prepared statement (already secure)
$stmt = $pdo->prepare("SELECT ps.*, p.title as page_title, p.id as page_id FROM page_sections ps JOIN pages p ON ps.page_id = p.id WHERE ps.id = ?");
$stmt->execute([$sectionId]);
$section = $stmt->fetch();

if (!$section) {
    logSecurityEvent("Section not found", ['section_id' => $sectionId]);
    redirect(BASE_URL . '/admin/pages.php');
}

$stmt = $pdo->prepare("SELECT slug FROM pages WHERE id = ?");
$stmt->execute([$section['page_id']]);
$pageSlug = $stmt->fetchColumn();

// Create the URL using slug if available, otherwise fallback to ID
if ($pageSlug) {
    $viewPageUrl = BASE_URL . '/' . $pageSlug;
} else {
    $viewPageUrl = BASE_URL . '/page.php?id=' . $section['page_id'];
}
// ===== ENHANCED SECURE IMAGE URL CLEANING =====

function cleanImageUrl($imageUrl) {
    if (empty($imageUrl)) {
        return '';
    }
    
    // Remove null bytes and normalize
    $imageUrl = str_replace("\0", '', trim($imageUrl));
    
    // If it already starts with uploads/, validate and return
    if (strpos($imageUrl, 'uploads/') === 0) {
        // Check for directory traversal attempts
        if (strpos($imageUrl, '..') !== false || strpos($imageUrl, '//') !== false) {
            logSecurityEvent("Directory traversal attempt in image URL", ['url' => $imageUrl]);
            return '';
        }
        return $imageUrl;
    }
    
    // Find the position of 'uploads/' in the URL
    $uploadsPos = strpos($imageUrl, 'uploads/');
    
    if ($uploadsPos !== false) {
        // Extract from 'uploads/' onwards
        $cleanPath = substr($imageUrl, $uploadsPos);
        
        // Validate the extracted path
        if (strpos($cleanPath, '..') !== false || strpos($cleanPath, '//') !== false) {
            logSecurityEvent("Directory traversal attempt in image URL", ['url' => $imageUrl]);
            return '';
        }
        
        return $cleanPath;
    }
    
    // If no 'uploads/' found, return empty (invalid)
    return '';
}

// ===== SECURE FORM PROCESSING =====

if ($_POST) {
    try {
        // Validate all inputs
        $title = validateTextInput($_POST['title'] ?? '', 255, true);
        $subtitle = validateTextInput($_POST['subtitle'] ?? '', 500, true);
        $content = validateTextInput($_POST['content'] ?? '', 500000, true);
        
        // Clean and validate image URL
        $rawImageUrl = $_POST['image_url'] ?? '';
        $imageUrl = '';
        if (!empty($rawImageUrl)) {
            $validatedUrl = validateUrl($rawImageUrl, true);
            $imageUrl = cleanImageUrl($validatedUrl);
        }
        
        // Log URL cleaning for debugging (remove in production)
        if ($rawImageUrl !== $imageUrl && !empty($rawImageUrl)) {
            //error_log("Cleaned image URL: '$rawImageUrl' -> '$imageUrl'");
        }
        
        // Validate colors
        $backgroundColor = validateHexColor($_POST['background_color'] ?? '#ffffff');
        $textColor = validateHexColor($_POST['text_color'] ?? '#000000');
        
        // Validate button fields
        $buttonText = validateTextInput($_POST['button_text'] ?? '', 100, true);
        $buttonUrl = validateUrl($_POST['button_url'] ?? '', true);
        
        // Enhanced CTA button fields
        $buttonBgColor = validateHexColor($_POST['button_bg_color'] ?? '#3b82f6');
        $buttonTextColor = validateHexColor($_POST['button_text_color'] ?? '#ffffff');
        $buttonText2 = validateTextInput($_POST['button_text_2'] ?? '', 100, true);
        $buttonUrl2 = validateUrl($_POST['button_url_2'] ?? '', true);
        $buttonBgColor2 = validateHexColor($_POST['button_bg_color_2'] ?? '#6b7280');
        $buttonTextColor2 = validateHexColor($_POST['button_text_color_2'] ?? '#ffffff');
        $buttonStyle2 = validateButtonStyle($_POST['button_style_2'] ?? 'solid');
        $buttonAlignment = validateButtonAlignment($_POST['button_alignment'] ?? 'center');
        $buttonLayout = validateButtonLayout($_POST['button_layout'] ?? 'horizontal');
        
        // Gradient styling fields
        $backgroundType = validateBackgroundType($_POST['background_type'] ?? 'solid');
        $gradientStart = validateHexColor($_POST['gradient_start'] ?? '#3b82f6');
        $gradientEnd = validateHexColor($_POST['gradient_end'] ?? '#8b5cf6');
        $gradientDirection = validateGradientDirection($_POST['gradient_direction'] ?? 'to right');
        $gradientTextColor = validateHexColor($_POST['gradient_text_color'] ?? '#ffffff');
        
        // Auto-switch background type based on image
        if (!empty($imageUrl)) {
            $backgroundType = 'image';
        } elseif ($backgroundType === 'image') {
            $backgroundType = 'solid';
        }
        
        // Validate title requirement
        if (empty($title) && $section['section_type'] !== 'contact_form') {
            throw new InvalidArgumentException('Section title is required');
        }
        
        // Execute database update with prepared statement
        $stmt = $pdo->prepare("UPDATE page_sections SET 
            title = ?, subtitle = ?, content = ?, image_url = ?, 
            background_color = ?, text_color = ?, button_text = ?, button_url = ?,
            button_bg_color = ?, button_text_color = ?, button_text_2 = ?, button_url_2 = ?,
            button_bg_color_2 = ?, button_text_color_2 = ?, button_style_2 = ?,
            button_alignment = ?, button_layout = ?,
            background_type = ?, gradient_start = ?, gradient_end = ?, gradient_direction = ?,
            gradient_text_color = ?
            WHERE id = ?");
            
        $result = $stmt->execute([
            $title, $subtitle, $content, $imageUrl,
            $backgroundColor, $textColor, $buttonText, $buttonUrl,
            $buttonBgColor, $buttonTextColor, $buttonText2, $buttonUrl2,
            $buttonBgColor2, $buttonTextColor2, $buttonStyle2,
            $buttonAlignment, $buttonLayout,
            $backgroundType, $gradientStart, $gradientEnd, $gradientDirection,
            $gradientTextColor, $sectionId
        ]);
        
        if (!$result) {
            throw new Exception('Database update failed');
        }
        
        $success = 'Section updated successfully!';
        
        // Refresh section data
        $stmt = $pdo->prepare("SELECT ps.*, p.title as page_title, p.id as page_id FROM page_sections ps JOIN pages p ON ps.page_id = p.id WHERE ps.id = ?");
        $stmt->execute([$sectionId]);
        $section = $stmt->fetch();
        
        if (!$section) {
            throw new Exception('Section not found after update');
        }
        
    } catch (InvalidArgumentException $e) {
        $error = 'Validation error: ' . $e->getMessage();
        logSecurityEvent("Form validation error", [
            'section_id' => $sectionId,
            'error' => $e->getMessage()
        ]);
        
    } catch (Exception $e) {
        $error = 'Error updating section: ' . $e->getMessage();
        logSecurityEvent("Database error", [
            'section_id' => $sectionId,
            'error' => $e->getMessage()
        ]);
    }
}

// ===== SECURE CONTACT FORM CONFIG PROCESSING =====

function processContactFormConfig($postData) {
    try {
        // Validate input data
        if (!is_array($postData)) {
            throw new InvalidArgumentException("Invalid post data format");
        }
        
        // Parse the content field which contains the configuration
        $emailConfig = [];
        if (!empty($postData['content'])) {
            $content = validateTextInput($postData['content'], 10000, true);
            $configLines = explode("\n", $content);
            
            foreach ($configLines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '=') === false) {
                    continue;
                }
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Validate key names (whitelist approach)
                $allowedKeys = [
                    'recipient_email', 'recipient_name', 'smtp_host', 'smtp_port',
                    'smtp_username', 'smtp_password', 'smtp_encryption',
                    'recaptcha_enabled', 'recaptcha_site_key', 'recaptcha_secret_key'
                ];
                
                if (!in_array($key, $allowedKeys, true)) {
                    logSecurityEvent("Invalid config key attempted", ['key' => $key]);
                    continue;
                }
                
                $emailConfig[$key] = $value;
            }
        }
        
        // Process and validate the config
        $validatedConfig = [];
        foreach ($emailConfig as $key => $value) {
            switch ($key) {
                case 'smtp_password':
                    if ($value === 'KEEP_EXISTING_PASSWORD') {
                        // Don't update password, keep existing one
                        continue 2;
                    } elseif (!empty($value) && strpos($value, ':') !== false) {
                        // Valid hashed password format (salt:hash)
                        $validatedConfig[$key] = $value;
                    } else {
                        throw new InvalidArgumentException("Invalid password format");
                    }
                    break;
                    
                case 'recipient_email':
                case 'smtp_username':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new InvalidArgumentException("Invalid email format for {$key}");
                    }
                    $validatedConfig[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    break;
                    
                case 'smtp_port':
                    $port = filter_var($value, FILTER_VALIDATE_INT, [
                        'options' => ['min_range' => 1, 'max_range' => 65535]
                    ]);
                    if ($port === false) {
                        throw new InvalidArgumentException("Invalid port number");
                    }
                    $validatedConfig[$key] = $port;
                    break;
                    
                case 'smtp_host':
                    // Basic hostname validation
                    if (!empty($value) && !preg_match('/^[a-zA-Z0-9.-]+$/', $value)) {
                        throw new InvalidArgumentException("Invalid hostname format");
                    }
                    $validatedConfig[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    break;
                    
                case 'smtp_encryption':
                    $allowedEncryption = ['tls', 'ssl', 'none'];
                    if (!in_array($value, $allowedEncryption, true)) {
                        $value = 'tls'; // Default
                    }
                    $validatedConfig[$key] = $value;
                    break;
                    
                case 'recaptcha_enabled':
                    $validatedConfig[$key] = in_array($value, ['true', 'false'], true) ? $value : 'false';
                    break;
                    
                case 'recaptcha_site_key':
                case 'recaptcha_secret_key':
                    // Basic validation for reCAPTCHA keys
                    if (!empty($value) && !preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
                        throw new InvalidArgumentException("Invalid reCAPTCHA key format");
                    }
                    $validatedConfig[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    break;
                    
                default:
                    $validatedConfig[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    break;
            }
        }
        
        // Convert back to key=value format for storage
        $configString = '';
        foreach ($validatedConfig as $key => $value) {
            $configString .= $key . '=' . $value . "\n";
        }
        
        return $configString;
        
    } catch (Exception $e) {
        logSecurityEvent("Contact form config processing error", [
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

// ===== SECURE PASSWORD VERIFICATION =====

function verifyPassword($inputPassword, $storedHash) {
    try {
        // Validate inputs
        if (empty($inputPassword) || empty($storedHash)) {
            return false;
        }
        
        // Check hash format
        if (strpos($storedHash, ':') === false) {
            return false; // Invalid hash format
        }
        
        $parts = explode(':', $storedHash, 2);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($salt, $hash) = $parts;
        
        // Validate salt and hash
        if (empty($salt) || empty($hash)) {
            return false;
        }
        
        // Create salted password
        $saltedPassword = $salt . $inputPassword;
        
        // Hash the input password with the same salt
        $newHash = hash('sha256', $saltedPassword);
        
        // Use timing-safe comparison
        return hash_equals($hash, $newHash);
        
    } catch (Exception $e) {
        logSecurityEvent("Password verification error", [
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

// ===== OUTPUT SANITIZATION FUNCTIONS =====

function safeOutput($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function safeUrlOutput($url) {
    if (empty($url)) {
        return '';
    }
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^(\/|uploads\/)/', $url)) {
        return '';
    }
    
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Section - <?= htmlspecialchars($section['page_title']) ?></title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
/* Enhanced Features Section Styles */
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.feature-notification {
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

[data-feature-index] {
    transition: all 0.2s ease;
    position: relative;
}

[data-feature-index]:hover {
    transform: translateY(-2px);
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

#iconGrid > div:hover {
    transform: scale(1.05);
    border-color: #6366f1;
}

#iconGrid > div:hover i {
    color: #6366f1;
}

/* Color input styling */
input[type="color"] {
    border: 2px solid #e5e7eb;
    border-radius: 0.375rem;
    cursor: pointer;
}

input[type="color"]:hover {
    border-color: #3b82f6;
}
</style>

</head>
<body class="bg-gray-100">
    <!-- Admin Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="edit-page.php?id=<?= $section['page_id'] ?>" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Page
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">Edit Section</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="<?= htmlspecialchars($viewPageUrl) ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-external-link-alt mr-1"></i>View Page
                    </a>
                    <span class="text-gray-600">Page: <?= htmlspecialchars($section['page_title']) ?></span>
                    <a href="logout.php" class="text-red-600 hover:text-red-800">
                        <i class="fas fa-sign-out-alt mr-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <?php
                    $icons = [
                        'hero' => 'fas fa-star text-blue-600',
                        'content' => 'fas fa-align-left text-green-600',
                        'features' => 'fas fa-th-large text-purple-600',
                        'stats' => 'fas fa-chart-line text-indigo-600',
                        'testimonials' => 'fas fa-quote-left text-yellow-600',
                        'pricing' => 'fas fa-tags text-emerald-600',
                        'team' => 'fas fa-users text-cyan-600',
                        'gallery' => 'fas fa-images text-orange-600',
                        'image_slider' => 'fas fa-sliders-h text-purple-600',
                        'slider' => 'fas fa-sliders-h text-purple-600',
                        'video' => 'fas fa-video text-rose-600',
                        'timeline' => 'fas fa-timeline text-violet-600',
                        'faq' => 'fas fa-question-circle text-amber-600',
                        'newsletter' => 'fas fa-envelope-open text-teal-600',
                        'cta' => 'fas fa-bullhorn text-red-600',
                        'contact_form' => 'fas fa-envelope text-red-600',
                        'custom' => 'fas fa-code text-gray-600',
                        'textwithimage' => 'fas fa-image text-indigo-600'
                    ];
                    $iconClass = $icons[$section['section_type']] ?? 'fas fa-puzzle-piece text-gray-600';
                    ?>
                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="<?= $iconClass ?>"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900"><?= ucfirst(str_replace('_', ' ', $section['section_type'])) ?> Section</h2>
                        <p class="text-gray-600">Section #<?= $section['section_order'] ?> on <?= htmlspecialchars($section['page_title']) ?></p>
                    </div>
                </div>
            </div>
            
            <form method="POST" class="space-y-8 p-6">
                <!-- Basic Section Information -->
                <div id="basicinfo" class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if ($section['section_type'] !== 'contact_form'): ?>
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Section Title *</label>
                                <input type="text" id="title" name="title" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       value="<?= htmlspecialchars($section['title']) ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <label for="subtitle" class="block text-sm font-medium text-gray-700 mb-2">Subtitle</label>
                            <input type="text" id="subtitle" name="subtitle"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                   value="<?= htmlspecialchars($section['subtitle'] ?? '') ?>"
                                   placeholder="Optional subtitle">
                        </div>
                    </div>
                </div>

                <!-- Styling Options -->
                <!-- Enhanced Styling Options Section (Fixed) -->
                <div id= "styling-options" class="bg-gray-50 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Styling Options</h3>
                    
                    <!-- Background Type Tabs -->
                    <div class="mb-4">
                        <div class="flex space-x-1 bg-gray-100 p-1 rounded-lg">
                            <button type="button" id="tab-solid" class="tab-button active px-3 py-2 text-sm font-medium rounded-md bg-white shadow-sm" onclick="switchBackgroundType('solid')">
                                Solid Color
                            </button>
                            <button type="button" id="tab-gradient" class="tab-button px-3 py-2 text-sm font-medium rounded-md text-gray-500 hover:text-gray-700" onclick="switchBackgroundType('gradient')">
                                Gradient
                            </button>
                        </div>
                    </div>

                    <!-- Hidden input to store background type -->
                    <input type="hidden" id="background_type" name="background_type" value="<?= htmlspecialchars($section['background_type'] ?? 'solid') ?>">

                    <!-- Solid Color Options -->
                    <div id="solid-options" class="background-option">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Background Color</label>
                                <div class="flex space-x-2">
                                    <input type="color" id="background_color_picker" name="background_color_picker"
                                        class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                                        value="<?= htmlspecialchars($section['background_color'] ?? '#60a48e') ?>">
                                    <input type="text" id="background_color" name="background_color"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                        value="<?= htmlspecialchars($section['background_color'] ?? '#60a48e') ?>"
                                        placeholder="#60a48e">
                                </div>
                                <!-- <div class="mt-2 flex space-x-2">
                                    <button type="button" class="px-3 py-1 text-xs bg-white border text-black rounded hover:bg-gray-50" onclick="setBackgroundColor('#ffffff')">White</button>
                                    <button type="button" class="px-3 py-1 text-xs bg-gray-400 text-white rounded hover:bg-gray-500" onclick="setBackgroundColor('#9ca3af')">Light Gray</button>
                                    <button type="button" class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700" onclick="setBackgroundColor('#2563eb')">Blue</button>
                                    <button type="button" class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" onclick="setBackgroundColor('#16a34a')">Green</button>
                                </div> -->
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Text Color</label>
                                <div class="flex space-x-2">
                                    <input type="color" id="text_color_picker" name="text_color_picker"
                                        class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                                        value="<?= htmlspecialchars($section['text_color'] ?? '#000000') ?>">
                                    <input type="text" id="text_color" name="text_color"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                        value="<?= htmlspecialchars($section['text_color'] ?? '#000000') ?>"
                                        placeholder="#000000">
                                </div>
                                <!-- <div class="mt-2 flex space-x-2">
                                    <button type="button" class="px-3 py-1 text-xs bg-black text-white rounded hover:bg-gray-800" onclick="setTextColor('#000000')">Black</button>
                                    <button type="button" class="px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700" onclick="setTextColor('#4b5563')">Gray</button>
                                    <button type="button" class="px-3 py-1 text-xs bg-white border text-black rounded hover:bg-gray-50" onclick="setTextColor('#ffffff')">White</button>
                                    <button type="button" class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700" onclick="setTextColor('#3b82f6')">Blue</button>
                                </div> -->
                            </div>
                        </div>
                    </div>

                    <!-- Gradient Options -->
                    <div id="gradient-options" class="background-option hidden">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Color</label>
                                    <div class="flex space-x-2">
                                        <input type="color" id="gradient_start_picker" name="gradient_start_picker"
                                            class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                                            value="<?= htmlspecialchars($section['gradient_start'] ?? '#3b82f6') ?>">
                                        <input type="text" id="gradient_start" name="gradient_start"
                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            value="<?= htmlspecialchars($section['gradient_start'] ?? '#3b82f6') ?>"
                                            placeholder="#3b82f6">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">End Color</label>
                                    <div class="flex space-x-2">
                                        <input type="color" id="gradient_end_picker" name="gradient_end_picker"
                                            class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                                            value="<?= htmlspecialchars($section['gradient_end'] ?? '#8b5cf6') ?>">
                                        <input type="text" id="gradient_end" name="gradient_end"
                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            value="<?= htmlspecialchars($section['gradient_end'] ?? '#8b5cf6') ?>"
                                            placeholder="#8b5cf6">
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Direction</label>
                                    <select id="gradient_direction" name="gradient_direction" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        <option value="to right" <?= ($section['gradient_direction'] ?? 'to right') === 'to right' ? 'selected' : '' ?>>Left to Right</option>
                                        <option value="to left" <?= ($section['gradient_direction'] ?? 'to right') === 'to left' ? 'selected' : '' ?>>Right to Left</option>
                                        <option value="to bottom" <?= ($section['gradient_direction'] ?? 'to right') === 'to bottom' ? 'selected' : '' ?>>Top to Bottom</option>
                                        <option value="to top" <?= ($section['gradient_direction'] ?? 'to right') === 'to top' ? 'selected' : '' ?>>Bottom to Top</option>
                                        <option value="45deg" <?= ($section['gradient_direction'] ?? 'to right') === '45deg' ? 'selected' : '' ?>>Diagonal (45°)</option>
                                        <option value="135deg" <?= ($section['gradient_direction'] ?? 'to right') === '135deg' ? 'selected' : '' ?>>Diagonal (135°)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Text Color</label>
                                    <div class="flex space-x-2">
                                        <input type="color" id="gradient_text_color_picker" name="text_color_picker"
                                            class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                                            value="<?= htmlspecialchars($section['text_color'] ?? '#ffffff') ?>">
                                        <input type="text" id="gradient_text_color" name="text_color"
                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                            value="<?= htmlspecialchars($section['text_color'] ?? '#ffffff') ?>"
                                            placeholder="#ffffff">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Gradient Preview</label>
                                <div id="gradient-preview" class="gradient-preview" style="background: linear-gradient(<?= htmlspecialchars($section['gradient_direction'] ?? 'to right') ?>, <?= htmlspecialchars($section['gradient_start'] ?? '#3b82f6') ?>, <?= htmlspecialchars($section['gradient_end'] ?? '#8b5cf6') ?>);"></div>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button" class="px-3 py-1 text-xs bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded" onclick="setGradient('#3b82f6', '#8b5cf6')">Blue to Purple</button>
                                <button type="button" class="px-3 py-1 text-xs bg-gradient-to-r from-green-400 to-blue-500 text-white rounded" onclick="setGradient('#10b981', '#3b82f6')">Green to Blue</button>
                                <button type="button" class="px-3 py-1 text-xs bg-gradient-to-r from-pink-500 to-orange-400 text-white rounded" onclick="setGradient('#ec4899', '#fb923c')">Pink to Orange</button>
                                <button type="button" class="px-3 py-1 text-xs bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded" onclick="setGradient('#9333ea', '#db2777')">Purple to Pink</button>
                                <button type="button" class="px-3 py-1 text-xs bg-gradient-to-r from-red-500 to-yellow-500 text-white rounded" onclick="setGradient('#ef4444', '#eab308')">Red to Yellow</button>
                                <button type="button" class="px-3 py-1 text-xs bg-gradient-to-r from-indigo-500 to-cyan-500 text-white rounded" onclick="setGradient('#6366f1', '#06b6d4')">Indigo to Cyan</button>
                            </div>
                        </div>
                    </div>

                    <!-- Live Preview -->
                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Live Preview</label>
                        <div id="styling-preview" class="p-6 rounded-lg border-2 border-gray-200 transition-all duration-300" style="<?= getPreviewStyle($section) ?>">
                            <h3 class="text-xl font-bold mb-2" style="color: <?= getPreviewTextColor($section) ?>">Sample Heading</h3>
                            <p style="color: <?= getPreviewTextColor($section) ?>">This is how your content will look with the selected styling options.</p>
                        </div>
                    </div>
                </div>

                <!-- Required CSS -->
                <style>
                .gradient-preview {
                    width: 100%;
                    height: 80px;
                    border-radius: 8px;
                    border: 2px solid #e5e7eb;
                    position: relative;
                    overflow: hidden;
                }

                .tab-button.active {
                    background-color: white;
                    color: #1f2937;
                    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                }

                .tab-button:not(.active) {
                    background-color: transparent;
                    color: #6b7280;
                }

                .background-option.hidden {
                    display: none;
                }
                </style>

                <!-- Complete JavaScript -->
                <script>
                // Main function to switch background types
                function switchBackgroundType(type) {
                    // Update hidden input
                    document.getElementById('background_type').value = type;
                    
                    // Update tab appearances
                    const solidTab = document.getElementById('tab-solid');
                    const gradientTab = document.getElementById('tab-gradient');
                    
                    // Remove active class from all tabs
                    solidTab.classList.remove('active');
                    gradientTab.classList.remove('active');
                    
                    // Add active class to selected tab and show/hide options
                    if (type === 'solid') {
                        solidTab.classList.add('active');
                        document.getElementById('solid-options').classList.remove('hidden');
                        document.getElementById('gradient-options').classList.add('hidden');
                    } else if (type === 'gradient') {
                        gradientTab.classList.add('active');
                        document.getElementById('gradient-options').classList.remove('hidden');
                        document.getElementById('solid-options').classList.add('hidden');
                    }
                    
                    // Update the live preview
                    updatePreview();
                }

                // Function to update the live preview
                function updatePreview() {
                    const preview = document.getElementById('styling-preview');
                    const heading = preview.querySelector('h3');
                    const paragraph = preview.querySelector('p');
                    const backgroundType = document.getElementById('background_type').value;
                    
                    if (backgroundType === 'gradient') {
                        const direction = document.getElementById('gradient_direction').value;
                        const start = document.getElementById('gradient_start').value;
                        const end = document.getElementById('gradient_end').value;
                        const textColor = document.getElementById('text_color').value; // Changed to use text_color
                        
                        preview.style.background = `linear-gradient(${direction}, ${start}, ${end})`;
                        heading.style.color = textColor;
                        paragraph.style.color = textColor;
                    } else {
                        const bgColor = document.getElementById('background_color').value;
                        const textColor = document.getElementById('text_color').value;
                        
                        preview.style.background = bgColor;
                        heading.style.color = textColor;
                        paragraph.style.color = textColor;
                    }
                }

                // Function to sync all text color fields
                function syncTextColor(color) {
                    // Update all text color fields to keep them in sync
                    document.getElementById('text_color').value = color;
                    document.getElementById('text_color_picker').value = color;
                    document.getElementById('gradient_text_color').value = color;
                    document.getElementById('gradient_text_color_picker').value = color;
                    updatePreview();
                }

                // Color picker synchronization functions
                function setBackgroundColor(color) {
                    document.getElementById('background_color').value = color;
                    document.getElementById('background_color_picker').value = color;
                    updatePreview();
                }

                function setTextColor(color) {
                    syncTextColor(color); // Use the sync function
                }

                function setGradient(startColor, endColor) {
                    document.getElementById('gradient_start').value = startColor;
                    document.getElementById('gradient_start_picker').value = startColor;
                    document.getElementById('gradient_end').value = endColor;
                    document.getElementById('gradient_end_picker').value = endColor;
                    updateGradientPreview();
                    updatePreview();
                }

                function updateGradientPreview() {
                    const direction = document.getElementById('gradient_direction').value;
                    const start = document.getElementById('gradient_start').value;
                    const end = document.getElementById('gradient_end').value;
                    
                    document.getElementById('gradient-preview').style.background = 
                        `linear-gradient(${direction}, ${start}, ${end})`;
                }

                // Initialize the form based on current background type
                function initializeForm() {
                    const currentType = document.getElementById('background_type').value;
                    switchBackgroundType(currentType);
                }

                // Event listeners for real-time updates
                document.addEventListener('DOMContentLoaded', function() {
                    // Initialize form state
                    initializeForm();
                    
                    // Sync color pickers with text inputs for solid colors - Updated to use sync function
                    document.getElementById('background_color_picker').addEventListener('input', function() {
                        document.getElementById('background_color').value = this.value;
                        updatePreview();
                    });
                    
                    document.getElementById('background_color').addEventListener('input', function() {
                        document.getElementById('background_color_picker').value = this.value;
                        updatePreview();
                    });
                    
                    document.getElementById('text_color_picker').addEventListener('input', function() {
                        syncTextColor(this.value); // Updated to use sync function
                    });
                    
                    document.getElementById('text_color').addEventListener('input', function() {
                        syncTextColor(this.value); // Updated to use sync function
                    });
                    
                    // Gradient controls
                    document.getElementById('gradient_start_picker').addEventListener('input', function() {
                        document.getElementById('gradient_start').value = this.value;
                        updateGradientPreview();
                        updatePreview();
                    });
                    
                    document.getElementById('gradient_start').addEventListener('input', function() {
                        document.getElementById('gradient_start_picker').value = this.value;
                        updateGradientPreview();
                        updatePreview();
                    });
                    
                    document.getElementById('gradient_end_picker').addEventListener('input', function() {
                        document.getElementById('gradient_end').value = this.value;
                        updateGradientPreview();
                        updatePreview();
                    });
                    
                    document.getElementById('gradient_end').addEventListener('input', function() {
                        document.getElementById('gradient_end_picker').value = this.value;
                        updateGradientPreview();
                        updatePreview();
                    });
                    
                    document.getElementById('gradient_direction').addEventListener('change', function() {
                        updateGradientPreview();
                        updatePreview();
                    });
                    
                    // Updated gradient text color event listeners to use sync function
                    document.getElementById('gradient_text_color_picker').addEventListener('input', function() {
                        syncTextColor(this.value); // Updated to use sync function
                    });
                    
                    document.getElementById('gradient_text_color').addEventListener('input', function() {
                        syncTextColor(this.value); // Updated to use sync function
                    });
                });
                </script>

                <?php
                // Helper functions for preview (simplified - no image support)
                function getPreviewStyle($section) {
                    $backgroundType = $section['background_type'] ?? 'solid';
                    
                    switch ($backgroundType) {
                        case 'gradient':
                            $direction = $section['gradient_direction'] ?? 'to right';
                            $start = $section['gradient_start'] ?? '#3b82f6';
                            $end = $section['gradient_end'] ?? '#8b5cf6';
                            return "background: linear-gradient($direction, $start, $end);";
                            
                        default: // solid
                            $bgColor = $section['background_color'] ?? '#60a48e';
                            return "background-color: $bgColor;";
                    }
                }

                function getPreviewTextColor($section) {
                    // Always use text_color field regardless of background type
                    return $section['text_color'] ?? '#000000';
                }
                ?>

                <!-- Content Section -->
                <div class="bg-white border border-gray-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Content</h3>
                    
                    <?php if ($section['section_type'] === 'hero'): ?>
                        <div class="space-y-4">
                            <div>
                                <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Hero Description *</label>
                                <textarea id="content" name="content" rows="4" required
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Enter the hero section description"><?= htmlspecialchars($section['content']) ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="button_text" class="block text-sm font-medium text-gray-700 mb-2">Button Text</label>
                                    <input type="text" id="button_text" name="button_text"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($section['button_text'] ?? '') ?>"
                                           placeholder="Get Started">
                                </div>
                                <div>
                                    <label for="button_url" class="block text-sm font-medium text-gray-700 mb-2">Button URL</label>
                                    <input type="url" id="button_url" name="button_url"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($section['button_url'] ?? '') ?>"
                                           placeholder="https://example.com">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="button_text_2" class="block text-sm font-medium text-gray-700 mb-2">Button Text</label>
                                    <input type="text" id="button_text_2" name="button_text_2"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($section['button2_text'] ?? '') ?>"
                                           placeholder="Get Started">
                                </div>
                                <div>
                                    <label for="button_url_2" class="block text-sm font-medium text-gray-700 mb-2">Button URL</label>
                                    <input type="url" id="button_url_2" name="button_url_2"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($section['button2_url'] ?? '') ?>"
                                           placeholder="https://example.com">
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($section['section_type'] === 'content'): ?>
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                            <textarea id="content" name="content" rows="6" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Enter your content here..."><?= htmlspecialchars($section['content']) ?></textarea>
                        </div>
                        
<?php elseif ($section['section_type'] === 'features'): ?>
<div class="space-y-6">
    <!-- Enhanced Features Management -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-4">
            <i class="fas fa-th-large mr-2"></i>Features Management (JSON Format)
        </label>
        
        <!-- Visual Preview Section with Scrolling -->
        <div id="features-preview" class="mb-6 p-6 border-2 border-gray-200 rounded-lg bg-gradient-to-br from-gray-50 to-purple-50">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-eye mr-2 text-purple-600"></i>Preview Your Features
                </h3>
                <div class="text-sm text-gray-600">
                    <span id="features-count">0</span> features configured
                </div>
            </div>
            
            <!-- Scrollable Preview Container -->
            <div class="relative">
                <div id="features-display" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-h-96 overflow-y-auto pr-2 scrollbar-thin scrollbar-thumb-purple-300 scrollbar-track-gray-100">
                    <!-- Features will be displayed here -->
                </div>
                
                <!-- Scroll Indicator -->
                <div id="scroll-indicator" class="hidden absolute bottom-0 left-0 right-0 bg-gradient-to-t from-purple-50 to-transparent h-8 pointer-events-none flex items-end justify-center">
                    <div class="text-xs text-purple-600 mb-1 animate-bounce">
                        <i class="fas fa-chevron-down"></i> Scroll for more
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Management Interface -->
        <div class="bg-white border-2 border-gray-300 rounded-lg p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New Feature
                </h4>
                <div class="text-sm text-gray-500">
                    Fill out the form below to create a new feature
                </div>
            </div>
            
            <!-- Feature Details Form -->
            <div class="space-y-4">
                <!-- Basic Feature Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tag mr-1"></i>Feature Title *
                        </label>
                        <input type="text" id="new-feature-title" placeholder="e.g., Fast Performance, 24/7 Support" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-align-left mr-1"></i>Feature Description *
                        </label>
                        <input type="text" id="new-feature-description" placeholder="Describe this feature..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
                
                <!-- Feature URL Field -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-link mr-1"></i>Feature URL (Optional)
                    </label>
                    <input type="text" id="new-feature-url" placeholder="https://example.com/feature-page" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <p class="text-xs text-gray-500 mt-1">Optional: Link to more information about this feature</p>
                </div>

                <!-- Icon and Colors -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-icons mr-1"></i>Feature Icon
                        </label>
                        <div class="flex gap-2">
                            <input type="text" id="new-feature-icon" placeholder="fas fa-rocket" 
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"
                                   value="fas fa-star">
                            <button type="button" onclick="openIconPicker()" 
                                    class="bg-purple-600 text-white px-3 py-2 rounded hover:bg-purple-700 transition duration-200 text-sm">
                                <i class="fas fa-icons"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon Background</label>
                        <div class="flex gap-2">
                            <input type="color" id="new-feature-icon-bg" 
                                   class="w-12 h-10 border border-gray-300 rounded cursor-pointer"
                                   value="#3b82f6">
                            <input type="text" id="new-feature-icon-bg-text"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"
                                   value="#3b82f6" placeholder="#3b82f6">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon Color</label>
                        <div class="flex gap-2">
                            <input type="color" id="new-feature-icon-color" 
                                   class="w-12 h-10 border border-gray-300 rounded cursor-pointer"
                                   value="#ffffff">
                            <input type="text" id="new-feature-icon-color-text"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"
                                   value="#ffffff" placeholder="#ffffff">
                        </div>
                    </div>
                </div>

                <!-- Feature Image (Optional) -->
                <!-- <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-image mr-1"></i>Feature Image (Optional)
                    </label>
                    <div class="flex gap-2 mb-2">
                        <input type="url" id="new-feature-image" placeholder="https://example.com/feature-image.jpg" 
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <button type="button" onclick="openImagePickerForFeature()" 
                                class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700 transition duration-200 text-sm">
                            <i class="fas fa-images"></i> Pick
                        </button>
                        <button type="button" onclick="openImageUploadForFeature()" 
                                class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 transition duration-200 text-sm">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div> -->
                    
                    <!-- Image Preview -->
                    <div id="feature-image-preview" class="hidden mb-2">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border">
                            <img id="preview-feature-image" class="w-16 h-16 object-cover rounded border" alt="Preview">
                            <div class="flex-1">
                                <p class="text-sm text-gray-600">Feature image preview</p>
                                <button type="button" onclick="clearFeatureImagePreview()" 
                                        class="text-xs text-red-600 hover:text-red-800">Remove image</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="addNewFeature()" 
                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>➕ Add Feature
                    </button>
                    <button type="button" onclick="cancelFeatureEdit()" style="display: none;"
                            class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel Edit
                    </button>
                    <button type="button" onclick="clearAllFeatures()" 
                            class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-trash mr-2"></i>Clear All Features
                    </button>
                </div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
            <h4 class="font-medium text-indigo-900 mb-3">
                <i class="fas fa-magic mr-2"></i>Quick Actions
            </h4>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="addSampleFeatures()" 
                        class="px-4 py-2 bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition duration-200 text-sm">
                    <i class="fas fa-plus-square mr-1"></i>Add Sample Features
                </button>
                <button type="button" onclick="sortFeaturesAlphabetically()" 
                        class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition duration-200 text-sm">
                    <i class="fas fa-sort-alpha-down mr-1"></i>Sort A-Z
                </button>
                <button type="button" onclick="exportFeaturesAsJSON()" 
                        class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition duration-200 text-sm">
                    <i class="fas fa-file-code mr-1"></i>Export JSON
                </button>
                <label class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition duration-200 text-sm cursor-pointer">
                    <i class="fas fa-upload mr-1"></i>Import JSON
                    <input type="file" accept=".json" onchange="importFeatures(event)" class="hidden">
                </label>
            </div>
        </div>

        <!-- Feature Tips -->
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
            <h4 class="font-medium text-purple-900 mb-2">
                <i class="fas fa-lightbulb mr-2"></i>Feature Tips
            </h4>
            <ul class="text-sm text-purple-800 space-y-1">
                <li>• <strong>Clear Titles:</strong> Use concise, benefit-focused titles (3-5 words max)</li>
                <li>• <strong>Descriptive Content:</strong> Explain the value each feature provides to users</li>
                <li>• <strong>Feature URLs:</strong> Add links to detailed feature pages or documentation</li>
                <li>• <strong>Visual Icons:</strong> Choose relevant FontAwesome icons to enhance understanding</li>
                <li>• <strong>Consistent Colors:</strong> Use consistent colors that match your brand</li>
                <li>• <strong>JSON Format:</strong> Data is automatically saved in clean JSON format</li>
                <li>• <strong>Edit/Delete:</strong> Click the buttons on each feature card to modify or remove</li>
            </ul>
        </div>

        <!-- JSON textarea for form submission -->
        <textarea id="content" name="content" rows="4" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm font-mono hidden"
                  placeholder="JSON feature data will appear here automatically..."><?= htmlspecialchars($section['content'] ?? '') ?></textarea>
        
        <div class="flex justify-between items-center mt-2">
            <p class="text-sm text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                <span>Data is automatically saved in JSON format</span>
            </p>
            <div class="text-sm text-gray-600">
                <span>JSON: <span id="features-textarea-length">0</span> chars</span>
            </div>
        </div>
    </div>
</div>

<!-- Icon Picker Modal -->
<div id="iconPickerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[80vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">Choose Icon</h3>
            <button onclick="closeIconPicker()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-96">
            <div class="mb-4">
                <input type="text" id="iconSearch" placeholder="Search icons..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                       onkeyup="filterIcons()">
            </div>
            <div id="iconGrid" class="grid grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-4">
                <!-- Icons will be loaded here -->
            </div>
        </div>
    </div>
</div>
                        
<?php elseif ($section['section_type'] === 'stats'): ?>
<div class="space-y-6">
    <!-- Enhanced Statistics Management -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-4">
            <i class="fas fa-chart-line mr-2"></i>Statistics Management with Animation
        </label>
        
        <!-- Visual Preview Section -->
        <div id="stats-preview" class="mb-6 p-6 border-2 border-gray-200 rounded-lg bg-gradient-to-br from-gray-50 to-indigo-50">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-eye mr-2 text-indigo-600"></i>Preview Your Statistics
                </h3>
                <div class="text-sm text-gray-600">
                    <span id="stats-count">0</span> statistics configured
                </div>
            </div>
            
            <!-- Animated Preview Container -->
            <div id="stats-display" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Stats will be displayed here -->
            </div>
            
            <!-- Animation Controls -->
            <div class="mt-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <button type="button" onclick="previewAnimation()" 
                            class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition duration-200 text-sm">
                        <i class="fas fa-play mr-2"></i>Preview Animation
                    </button>
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-600">Animation Speed:</label>
                        <select id="animation-speed" class="text-sm border border-gray-300 rounded px-2 py-1">
                            <option value="slow">Slow (3s)</option>
                            <option value="medium" selected>Medium (2s)</option>
                            <option value="fast">Fast (1s)</option>
                        </select>
                    </div>
                </div>
                <div class="text-xs text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>Statistics will animate when they come into view
                </div>
            </div>
        </div>

        <!-- Statistics Management Interface -->
        <div class="bg-white border-2 border-gray-300 rounded-lg p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New Statistic
                </h4>
                <div class="text-sm text-gray-500">
                    Create impressive animated statistics
                </div>
            </div>
            
            <!-- Statistics Form -->
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-hashtag mr-1"></i>Number/Value *
                        </label>
                        <input type="text" id="new-stat-number" placeholder="e.g., 500+, 24/7, 99%" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <p class="text-xs text-gray-500 mt-1">Can include +, %, /, or any suffix</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tag mr-1"></i>Label *
                        </label>
                        <input type="text" id="new-stat-label" placeholder="e.g., Happy Clients, Projects Done" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>
                
                <!-- Optional Customization -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-icons mr-1"></i>Icon (Optional)
                        </label>
                        <div class="flex gap-2">
                            <input type="text" id="new-stat-icon" placeholder="fas fa-users" 
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <button type="button" onclick="openStatsIconPicker()" 
                                    class="bg-purple-600 text-white px-3 py-2 rounded hover:bg-purple-700 transition duration-200 text-sm">
                                <i class="fas fa-icons"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Number Color</label>
                        <div class="flex gap-2">
                            <input type="color" id="new-stat-color" 
                                   class="w-12 h-10 border border-gray-300 rounded cursor-pointer"
                                   value="#3b82f6">
                            <input type="text" id="new-stat-color-text"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"
                                   value="#3b82f6" placeholder="#3b82f6">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Animation Type</label>
                        <select id="new-stat-animation" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="countup">Count Up</option>
                            <option value="fade">Fade In</option>
                            <option value="slide">Slide Up</option>
                            <option value="bounce">Bounce In</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="addNewStat()" 
                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>➕ Add Statistic
                    </button>
                    <button type="button" onclick="cancelStatEdit()" style="display: none;"
                            class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel Edit
                    </button>
                    <button type="button" onclick="clearAllStats()" 
                            class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-trash mr-2"></i>Clear All
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
            <h4 class="font-medium text-indigo-900 mb-3">
                <i class="fas fa-magic mr-2"></i>Quick Actions & Tips
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h5 class="font-medium text-indigo-800 mb-2">Sample Statistics</h5>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="addSampleStats('business')" 
                                class="px-3 py-1 bg-indigo-500 text-white rounded text-xs hover:bg-indigo-600 transition duration-200">
                            Business Stats
                        </button>
                        <button type="button" onclick="addSampleStats('tech')" 
                                class="px-3 py-1 bg-blue-500 text-white rounded text-xs hover:bg-blue-600 transition duration-200">
                            Tech Stats
                        </button>
                        <button type="button" onclick="addSampleStats('service')" 
                                class="px-3 py-1 bg-green-500 text-white rounded text-xs hover:bg-green-600 transition duration-200">
                            Service Stats
                        </button>
                    </div>
                </div>
                <div>
                    <h5 class="font-medium text-indigo-800 mb-2">Pro Tips</h5>
                    <ul class="text-sm text-indigo-700 space-y-1">
                        <li>• Use round numbers for better impact (500+ vs 487)</li>
                        <li>• Keep labels short and descriptive</li>
                        <li>• Include meaningful suffixes (+, %, /7, etc.)</li>
                        <li>• Choose colors that match your brand</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Hidden textarea for form submission -->
        <textarea id="content" name="content" rows="4" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm font-mono hidden"
                  placeholder="Statistics data will appear here automatically..."><?= htmlspecialchars($section['content']) ?></textarea>
        
        <div class="flex justify-between items-center mt-2">
            <p class="text-sm text-gray-500">
                <!-- <i class="fas fa-info-circle mr-1"></i><span id="stats-format-display">📊 Format: Number|Label|Icon|Color|Animation (separated by ||)</span> -->
            </p>
            <div class="text-sm text-gray-600">
                <span id="stats-textarea-length">0</span> characters
            </div>
        </div>
    </div>
</div>

<!-- Icon Picker Modal for Stats-->
<div id="statsIconPickerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[80vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">Choose Icon for Statistics</h3>
            <button onclick="closeStatsIconPicker()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-96">
            <div class="mb-4">
                <input type="text" id="statsIconSearch" placeholder="Search icons..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                       onkeyup="filterStatsIcons()">
            </div>
            <div id="statsIconGrid" class="grid grid-cols-6 md:grid-cols-8 lg:grid-cols-12 gap-4">
                <!-- Icons will be loaded here -->
            </div>
        </div>
    </div>
</div>
                        
<?php elseif ($section['section_type'] === 'testimonials'): ?>
<div class="space-y-6">
    <!-- Enhanced Testimonials Management -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-4">
            <i class="fas fa-quote-left mr-2"></i>Customer Testimonials Management
        </label>
        
        <!-- Visual Preview Section -->
        <div id="testimonials-preview" class="mb-6 p-6 border-2 border-gray-200 rounded-lg bg-gradient-to-br from-gray-50 to-yellow-50">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-eye mr-2 text-yellow-600"></i>Preview Your Testimonials
                </h3>
                <div class="text-sm text-gray-600">
                    <span id="testimonials-count">0</span> testimonials
                </div>
            </div>
            <div id="testimonials-display" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Testimonials will be displayed here -->
            </div>
        </div>

        <!-- Testimonial Management Interface -->
        <div class="bg-white border-2 border-gray-300 rounded-lg p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New Testimonial
                </h4>
                <div class="text-sm text-gray-500">
                    Fill out the form below to add a customer testimonial
                </div>
            </div>
            
            <!-- Testimonial Details Form -->
            <div class="space-y-4">
                <!-- Basic Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-user mr-1"></i>Customer Name & Title *
                        </label>
                        <input type="text" id="new-testimonial-name" placeholder="e.g., John Doe, CEO of Company" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-star mr-1"></i>Rating
                        </label>
                        <select id="new-testimonial-rating" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="5">⭐⭐⭐⭐⭐ (5 Stars)</option>
                            <option value="4">⭐⭐⭐⭐ (4 Stars)</option>
                            <option value="3">⭐⭐⭐ (3 Stars)</option>
                            <option value="2">⭐⭐ (2 Stars)</option>
                            <option value="1">⭐ (1 Star)</option>
                        </select>
                    </div>
                </div>

                <!-- Testimonial Quote -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-quote-left mr-1"></i>Testimonial Quote *
                    </label>
                    <textarea id="new-testimonial-quote" rows="3" placeholder="Amazing service and incredible results. Highly recommend to anyone looking for..." 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                </div>

                <!-- Customer Photo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <i class="fas fa-camera mr-1"></i>Customer Photo
                    </label>
                    <div class="flex gap-2 mb-2">
                        <input type="text" id="new-testimonial-image" placeholder="https://example.com/customer-photo.jpg" 
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <button type="button" onclick="IsolatedTestimonials.openImageUpload()" 
                                class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 transition duration-200 text-sm">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                    
                    <!-- Verified Checkbox -->
                    <div class="flex items-center mt-2">
                        <input type="checkbox" id="new-testimonial-verified" checked class="mr-2 rounded">
                        <label class="text-sm text-gray-700">
                            <i class="fas fa-check-circle mr-1 text-green-600"></i>Verified testimonial
                        </label>
                    </div>
                    
                    <!-- Image Preview -->
                    <div id="testimonial-image-preview" class="hidden mb-2">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border">
                            <img id="preview-testimonial-image" class="w-16 h-16 object-cover rounded-full border" alt="Preview">
                            <div class="flex-1">
                                <p class="text-sm text-gray-600">Customer photo preview</p>
                                <button type="button" onclick="clearTestimonialImagePreview()" 
                                        class="text-xs text-red-600 hover:text-red-800">Remove image</button>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Optional: Add a photo of the customer for more authentic testimonials</p>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="IsolatedTestimonials.add()" 
                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>➕ Add Testimonial
                    </button>
                    <button type="button" onclick="IsolatedTestimonials.cancel()" style="display: none;"
                            class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel Edit
                    </button>
                    <button type="button" onclick="IsolatedTestimonials.clear()" 
                            class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-trash mr-2"></i>Clear All
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h4 class="font-medium text-yellow-900 mb-2">
                <i class="fas fa-lightbulb mr-2"></i>Testimonial Tips
            </h4>
            <ul class="text-sm text-yellow-800 space-y-1">
                <li>• <strong>Authentic Reviews:</strong> Use real customer feedback for credibility</li>
                <li>• <strong>Include Photos:</strong> Customer photos make testimonials more trustworthy</li>
                <li>• <strong>Complete Details:</strong> Include customer name, title, and company for authority</li>
                <li>• <strong>Variety:</strong> Mix different types of customers and industries</li>
            </ul>
        </div>

        <!-- Hidden textarea for form submission -->
        <textarea id="content" name="content" rows="4" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm font-mono hidden"
                  placeholder="Testimonial data will appear here automatically..."><?= htmlspecialchars($section['content']) ?></textarea>
        
        <div class="flex justify-between items-center mt-2">
            <p class="text-sm text-gray-500">
                <!-- <i class="fas fa-info-circle mr-1"></i>Format: Name|Quote|Rating|ImageURL (separated by ||) -->
            </p>
            <div class="text-sm text-gray-600">
                <span id="testimonials-textarea-length">0</span> characters
            </div>
        </div>
    </div>
</div>

<script>
// Add this wrapper function for image preview clearing
function clearTestimonialImagePreview() {
    const previewContainer = document.getElementById('testimonial-image-preview');
    const imageField = document.getElementById('new-testimonial-image');
    
    if (previewContainer) previewContainer.classList.add('hidden');
    if (imageField) imageField.value = '';
}
</script>


<?php elseif ($section['section_type'] === 'pricing'): ?>
<div class="space-y-6">
    <!-- Enhanced Pricing Plans Management -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-4">
            <i class="fas fa-tags mr-2"></i>Pricing Plans Management
        </label>
        
        <!-- Visual Preview Section -->
        <div id="pricing-preview" class="mb-6 p-6 border-2 border-gray-200 rounded-lg bg-gradient-to-br from-gray-50 to-blue-50">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-eye mr-2 text-blue-600"></i>Preview Your Plans
                </h3>
                <div class="text-sm text-gray-600">
                    <span id="plan-count">0</span> plans configured
                </div>
            </div>
            <div id="plans-display" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Plans will be displayed here -->
            </div>
        </div>

        <!-- Plan Management Interface -->
        <div class="bg-white border-2 border-gray-300 rounded-lg p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-900 flex items-center" id="form-header">
                    <i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New Pricing Plan
                </h4>
                <div class="text-sm text-gray-500">
                    <span id="form-subtitle">Fill out the form below to create a new plan</span>
                </div>
            </div>
            
            <!-- Plan Details Form -->
            <div class="space-y-4">
                <!-- Basic Plan Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tag mr-1"></i>Plan Name *
                        </label>
                        <input type="text" id="new-plan-name" placeholder="e.g., Starter, Professional, Enterprise" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-dollar-sign mr-1"></i>Price *
                        </label>
                        <input type="text" id="new-plan-price" placeholder="e.g., 99, Free, Custom" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-calendar mr-1"></i>Billing Period
                        </label>
                        <select id="new-plan-period" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="/month">Monthly</option>
                            <option value="/year">Yearly</option>
                            <option value="/week">Weekly</option>
                            <option value="/lifetime">Lifetime</option>
                            <option value="">Custom</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-info-circle mr-1"></i>Description
                        </label>
                        <input type="text" id="new-plan-description" placeholder="e.g., Perfect for small businesses" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>
                </div>

                <!-- Features Section -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-list-ul mr-1"></i>Plan Features
                        </label>
                        <button type="button" onclick="addFeatureInput()" 
                                class="text-sm bg-green-100 text-green-700 px-3 py-1 rounded hover:bg-green-200 transition duration-200">
                            <i class="fas fa-plus mr-1"></i>Add Feature
                        </button>
                    </div>
                    <div id="features-list" class="space-y-2">
                        <div class="flex gap-2 feature-row">
                            <input type="text" placeholder="Feature description (e.g., 24/7 Support)" 
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm feature-input">
                            <button type="button" onclick="removeFeatureInput(this)" 
                                    class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition duration-200">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Plan Options -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <label class="flex items-center text-sm font-medium text-gray-700">
                        <input type="checkbox" id="mark-popular" class="mr-2 rounded">
                        <i class="fas fa-star mr-1 text-yellow-500"></i>
                        Mark as "Most Popular" Plan
                    </label>
                    <div class="text-xs text-gray-500">This will highlight the plan with a special badge</div>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-gray-200">
                    <button type="button" onclick="addNewPlan()" id="main-action-btn"
                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>➕ Add Plan
                    </button>
                    <button type="button" onclick="cancelEdit()" id="cancel-edit-btn" style="display: none;"
                            class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-times mr-2"></i>Cancel Edit
                    </button>
                    <button type="button" onclick="clearAllPlans()" 
                            class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200 text-sm font-medium">
                        <i class="fas fa-trash mr-2"></i>Clear All Plans
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="font-medium text-blue-900 mb-2">
                <i class="fas fa-lightbulb mr-2"></i>Quick Tips
            </h4>
            <ul class="text-sm text-blue-800 space-y-1">
                <li>• <strong>Edit:</strong> Click the "Edit" button on any plan to modify it</li>
                <li>• <strong>Copy:</strong> Use "Copy" to duplicate a plan and modify it</li>
                <li>• <strong>Popular:</strong> Mark your best plan as "Most Popular" to highlight it</li>
                <li>• <strong>Features:</strong> Add compelling features that highlight the value of each plan</li>
            </ul>
        </div>

        <!-- Hidden textarea for form submission -->
        <textarea id="content" name="content" rows="4" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm font-mono hidden"
                  placeholder="Plan data will appear here automatically..."><?= htmlspecialchars($section['content']) ?></textarea>
        
        <div class="flex justify-between items-center mt-2">
            <p class="text-sm text-gray-500">
                <!-- <i class="fas fa-info-circle mr-1"></i>Plan data format: Plan|Price|Period|Description|Feature1|Feature2 (separated by ||) -->
            </p>
            <div class="text-sm text-gray-600">
                <span id="textarea-length">0</span> characters
            </div>
        </div>
    </div>
</div>

                        
 <?php elseif ($section['section_type'] === 'team'): ?>
                        <div class="space-y-6">
                            <!-- Enhanced Team Members Management -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-4">
                                    <i class="fas fa-users mr-2"></i>Team Members Management
                                </label>
                                
                                <!-- Visual Preview Section -->
                                <div id="team-preview" class="mb-6 p-6 border-2 border-gray-200 rounded-lg bg-gradient-to-br from-gray-50 to-cyan-50">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-eye mr-2 text-cyan-600"></i>Preview Your Team
                                        </h3>
                                        <div class="text-sm text-gray-600">
                                            <span id="team-count">0</span> team members
                                        </div>
                                    </div>
                                    <div id="team-display" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <!-- Team members will be displayed here -->
                                    </div>
                                </div>

                                <!-- Team Member Management Interface -->
                                <div class="bg-white border-2 border-gray-300 rounded-lg p-6 mb-4">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-user-plus mr-2 text-green-600"></i>Add New Team Member
                                        </h4>
                                        <div class="text-sm text-gray-500">
                                            Fill out the form below to add a team member
                                        </div>
                                    </div>
                                    
                                    <!-- Team Member Details Form -->
                                    <div class="space-y-4">
                                        <!-- Basic Info -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    <i class="fas fa-user mr-1"></i>Full Name *
                                                </label>
                                                <input type="text" id="new-member-name" placeholder="e.g., John Doe" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    <i class="fas fa-briefcase mr-1"></i>Position/Title *
                                                </label>
                                                <input type="text" id="new-member-position" placeholder="e.g., CEO, Senior Developer" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            </div>
                                        </div>

                                        <!-- Bio/Description -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-align-left mr-1"></i>Bio/Description *
                                            </label>
                                            <textarea id="new-member-bio" rows="3" placeholder="Brief description about the team member, their role, and expertise..." 
                                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                                        </div>

                                        <!-- Profile Image -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-camera mr-1"></i>Profile Image
                                            </label>
                                            <div class="flex gap-2 mb-2">
                                                <input type="text" id="new-member-image" placeholder="https://example.com/profile.jpg" 
                                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                                <!-- <button type="button" onclick="openImagePickerForMember()" 
                                                        class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700 transition duration-200 text-sm">
                                                    <i class="fas fa-images"></i> Pick
                                                </button> -->
                                                <button type="button" onclick="openImageUploadForMember()" 
                                                        class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 transition duration-200 text-sm">
                                                    <i class="fas fa-upload"></i> Upload
                                                </button>
                                            </div>
                                            
                                            <!-- Image Preview -->
                                            <div id="member-image-preview" class="hidden mb-2">
                                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border">
                                                    <img id="preview-member-image" class="w-16 h-16 object-cover rounded-full border" alt="Preview">
                                                    <div class="flex-1">
                                                        <p class="text-sm text-gray-600">Image preview</p>
                                                        <button type="button" onclick="clearMemberImagePreview()" 
                                                                class="text-xs text-red-600 hover:text-red-800">Remove image</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                                            <button type="button" onclick="addNewTeamMember()" 
                                                    class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200 text-sm font-medium">
                                                <i class="fas fa-plus mr-2"></i>➕ Add Member
                                            </button>
                                            <button type="button" onclick="cancelTeamMemberEdit()" style="display: none;"
                                                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200 text-sm font-medium">
                                                <i class="fas fa-times mr-2"></i>Cancel Edit
                                            </button>
                                            <button type="button" onclick="clearAllTeamMembers()" 
                                                    class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200 text-sm font-medium">
                                                <i class="fas fa-trash mr-2"></i>Clear All
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="bg-cyan-50 border border-cyan-200 rounded-lg p-4">
                                    <h4 class="font-medium text-cyan-900 mb-2">
                                        <i class="fas fa-lightbulb mr-2"></i>Team Management Tips
                                    </h4>
                                    <ul class="text-sm text-cyan-800 space-y-1">
                                        <li>• <strong>Professional Photos:</strong> Use high-quality headshots for best results</li>
                                        <li>• <strong>Consistent Bios:</strong> Keep bio lengths similar for a professional look</li>
                                        <li>• <strong>Clear Titles:</strong> Use specific job titles that reflect actual roles</li>
                                        <li>• <strong>Edit & Reorder:</strong> Click edit to modify members or drag to reorder</li>
                                    </ul>
                                </div>

                                <!-- Hidden textarea for form submission -->
                                <textarea id="content" name="content" rows="4" required
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm font-mono hidden"
                                          placeholder="Team member data will appear here automatically..."><?= htmlspecialchars($section['content']) ?></textarea>
                                
                                <div class="flex justify-between items-center mt-2">
                                    <p class="text-sm text-gray-500">
                                        <!-- <i class="fas fa-info-circle mr-1"></i>Format: Name|Position|Bio|ImageURL (separated by ||) -->
                                    </p>
                                    <div class="text-sm text-gray-600">
                                        <span id="team-textarea-length">0</span> characters
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($section['section_type'] === 'gallery'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-4">Gallery Images</label>
                            
                            <!-- Gallery Manager -->
                            <div id="galleryManager" class="space-y-4">
                                <div class="flex justify-between items-center p-4 bg-blue-50 border border-blue-200 rounded-md">
                                    <h4 class="font-medium text-blue-900">Manage Gallery Images</h4>
                                    <div class="space-x-2">
                                        <!-- <button type="button" onclick="addGalleryImage()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-200">
                                            <i class="fas fa-plus mr-2"></i>Add Image
                                        </button> -->
                                        <button type="button" onclick="openBulkImageUpload()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                                            <i class="fas fa-upload mr-2"></i>Upload Multiple
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="galleryItems" class="space-y-3">
                                    <!-- Gallery items will be loaded here -->
                                </div>
                            </div>
                            
                            <!-- Hidden textarea for form submission -->
                            <textarea id="content" name="content" class="hidden"><?= htmlspecialchars($section['content']) ?></textarea>
                        </div>
                        
                    <?php elseif ($section['section_type'] === 'image_slider' || $section['section_type'] === 'slider'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-4">Image Slider with Descriptions</label>
                            
                            <!-- Enhanced Slider Manager -->
                            <div id="sliderManager" class="space-y-4">
                                <div class="flex justify-between items-center p-4 bg-purple-50 border border-purple-200 rounded-md">
                                    <h4 class="font-medium text-purple-900">Manage Slider Content</h4>
                                    <div class="space-x-2">
                                        <button type="button" onclick="addSliderImage()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-200">
                                            <i class="fas fa-plus mr-2"></i>Add Slide
                                        </button>
                                        <button type="button" onclick="openBulkSliderUpload()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition duration-200">
                                            <i class="fas fa-upload mr-2"></i>Upload Multiple
                                        </button>
                                    </div>
                                </div>
                                
                                <div id="sliderItems" class="space-y-6">
                                    <!-- Slider items will be loaded here -->
                                </div>
                            </div>
                            
                            <!-- Hidden textarea for form submission -->
                            <textarea id="content" name="content" class="hidden"><?= htmlspecialchars($section['content']) ?></textarea>
                            <style>
                                #styling-options {
                                    display: none !important;
                                }
                            </style>
                        </div>
                        
<?php elseif ($section['section_type'] === 'video'): ?>
<?php 
// Parse existing video data from JSON
$video_data = json_decode($section['content'] ?? '[]', true);
if (!is_array($video_data)) $video_data = [];
?>
<div>
    <!-- Hidden input to store JSON data -->
    <input type="hidden" name="content" id="videoContentInput" value="<?= htmlspecialchars($section['content'] ?? '[]') ?>">
    
    <div class="mb-4 flex justify-between items-center">
        <label class="block text-sm font-medium text-gray-700">Videos</label>
        <button type="button" id="addVideoBtn" 
                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
            + Add Video
        </button>
    </div>
    
    <div id="videoSections">
        <!-- Existing videos will be loaded here -->
    </div>
    
    <p class="text-sm text-gray-500 mt-2">Use YouTube URLs - they will be automatically converted to embed format</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let videoCounter = 0;
    const videoSections = document.getElementById('videoSections');
    const contentInput = document.getElementById('videoContentInput');
    
    // Load existing videos
    const existingVideos = <?= json_encode($video_data) ?>;
    if (existingVideos.length > 0) {
        existingVideos.forEach(video => {
            addVideoSection(video.url || '', video.description || '');
        });
    } else {
        addVideoSection(); // Add one empty section by default
    }
    
    // Add video button event
    document.getElementById('addVideoBtn').addEventListener('click', function() {
        addVideoSection();
    });
    
    function addVideoSection(url = '', description = '') {
        videoCounter++;
        const sectionId = 'video_' + videoCounter;
        
        const sectionHTML = `
            <div class="video-item border border-gray-200 rounded-lg p-4 mb-3" data-video-id="${sectionId}">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-sm font-medium text-gray-600">Video ${videoCounter}</span>
                    <button type="button" class="delete-video bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs">
                        Delete
                    </button>
                </div>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Video URL *</label>
                        <input type="url" class="video-url w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="${url}" placeholder="https://www.youtube.com/watch?v=VIDEO_ID" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea class="video-description w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                  rows="2" placeholder="Enter video description (optional)">${description}</textarea>
                    </div>
                    
                    <div class="video-preview hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preview</label>
                        <div class="preview-container bg-gray-50 border rounded p-2">
                            <div class="embed-frame"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        videoSections.insertAdjacentHTML('beforeend', sectionHTML);
        
        const newSection = videoSections.lastElementChild;
        const urlInput = newSection.querySelector('.video-url');
        const deleteBtn = newSection.querySelector('.delete-video');
        
        // Delete functionality
        deleteBtn.addEventListener('click', function() {
            newSection.remove();
            updateVideoNumbers();
            updateHiddenInput();
        });
        
        // URL preview functionality
        urlInput.addEventListener('input', function() {
            updatePreview(newSection, this.value);
            updateHiddenInput();
        });
        
        // Description change event
        newSection.querySelector('.video-description').addEventListener('input', function() {
            updateHiddenInput();
        });
        
        // Initial preview if URL exists
        if (url) {
            updatePreview(newSection, url);
        }
        
        updateHiddenInput();
    }
    
    function updatePreview(section, url) {
        const previewDiv = section.querySelector('.video-preview');
        const embedFrame = section.querySelector('.embed-frame');
        
        if (isValidYouTubeUrl(url)) {
            const embedUrl = convertToEmbedUrl(url);
            embedFrame.innerHTML = `
                <iframe width="100%" height="150" src="${embedUrl}" 
                        frameborder="0" allowfullscreen></iframe>
            `;
            previewDiv.classList.remove('hidden');
        } else {
            previewDiv.classList.add('hidden');
            embedFrame.innerHTML = '';
        }
    }
    
    function isValidYouTubeUrl(url) {
        return /^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|embed\/)|youtu\.be\/)[\w-]+/.test(url);
    }
    
    function convertToEmbedUrl(url) {
        let videoId = '';
        if (url.includes('youtube.com/watch?v=')) {
            videoId = url.split('watch?v=')[1].split('&')[0];
        } else if (url.includes('youtu.be/')) {
            videoId = url.split('youtu.be/')[1].split('?')[0];
        } else if (url.includes('youtube.com/embed/')) {
            return url;
        }
        return `https://www.youtube.com/embed/${videoId}`;
    }
    
    function updateVideoNumbers() {
        const items = videoSections.querySelectorAll('.video-item');
        items.forEach((item, index) => {
            item.querySelector('span').textContent = `Video ${index + 1}`;
        });
    }
    
    function updateHiddenInput() {
        const videos = [];
        const items = videoSections.querySelectorAll('.video-item');
        
        items.forEach((item, index) => {
            const url = item.querySelector('.video-url').value.trim();
            const description = item.querySelector('.video-description').value.trim();
            
            if (url) {
                videos.push({
                    id: index + 1,
                    url: url,
                    embed_url: isValidYouTubeUrl(url) ? convertToEmbedUrl(url) : url,
                    description: description,
                    order: index + 1
                });
            }
        });
        
        contentInput.value = JSON.stringify(videos);
    }
});
</script>

<style>
.video-item {
    transition: all 0.3s ease;
}
.video-item:hover {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
                        
<?php elseif ($section['section_type'] === 'timeline'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Timeline Events *</label>
                            
                            <!-- Timeline Events Container -->
                            <div id="timeline-events" class="space-y-4 mb-4">
                                <!-- Timeline events will be populated here -->
                            </div>
                            
                            <!-- Add Event Button -->
                            <button type="button" onclick="addTimelineEvent()" class="mb-4 bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                                <i class="fas fa-plus mr-2"></i>Add Timeline Event
                            </button>
                            
                            <!-- Hidden textarea to store the final data -->
                            <textarea id="content" name="content" style="display: none;"><?= htmlspecialchars($section['content']) ?></textarea>
                            
                            <p class="text-sm text-gray-500">Add timeline events with year, title, and description. Events will be sorted by year automatically.</p>
                        </div>

                        <script>
                        let timelineEventCount = 0;
                        
                        // Parse existing timeline data
                        function parseTimelineData() {
                            const content = document.getElementById('content').value;
                            if (!content) return [];
                            
                            return content.split('||').map(event => {
                                const parts = event.split('|');
                                return {
                                    year: parts[0] || '',
                                    title: parts[1] || '',
                                    description: parts[2] || ''
                                };
                            }).filter(event => event.year || event.title || event.description);
                        }
                        
                        // Create timeline event HTML
                        function createTimelineEventHTML(event, index) {
                            return `
                                <div class="timeline-event border border-gray-300 rounded-lg p-4 bg-gray-50" data-index="${index}">
                                    <div class="flex justify-between items-center mb-3">
                                        <h4 class="text-md font-medium text-gray-800">Timeline Event #${index + 1}</h4>
                                        <button type="button" onclick="removeTimelineEvent(${index})" class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Year *</label>
                                            <input type="text" 
                                                   value="${event.year}" 
                                                   onchange="updateTimelineData()"
                                                   class="timeline-year w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="2024" 
                                                   required>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                                            <input type="text" 
                                                   value="${event.title}" 
                                                   onchange="updateTimelineData()"
                                                   class="timeline-title w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="Event Title" 
                                                   required>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                            <input type="text" 
                                                   value="${event.description}" 
                                                   onchange="updateTimelineData()"
                                                   class="timeline-description w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="Brief description">
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        
                        // Add new timeline event
                        function addTimelineEvent() {
                            const container = document.getElementById('timeline-events');
                            const newEvent = { year: '', title: '', description: '' };
                            const eventHTML = createTimelineEventHTML(newEvent, timelineEventCount);
                            
                            container.insertAdjacentHTML('beforeend', eventHTML);
                            timelineEventCount++;
                            updateTimelineData();
                        }
                        
                        // Remove timeline event
                        function removeTimelineEvent(index) {
                            const eventElement = document.querySelector(`[data-index="${index}"]`);
                            if (eventElement) {
                                eventElement.remove();
                                updateTimelineData();
                                reindexEvents();
                            }
                        }
                        
                        // Reindex events after removal
                        function reindexEvents() {
                            const events = document.querySelectorAll('.timeline-event');
                            events.forEach((event, index) => {
                                event.setAttribute('data-index', index);
                                event.querySelector('h4').textContent = `Timeline Event #${index + 1}`;
                                event.querySelector('button').setAttribute('onclick', `removeTimelineEvent(${index})`);
                            });
                            timelineEventCount = events.length;
                        }
                        
                        // Update the hidden textarea with current data
                        function updateTimelineData() {
                            const events = [];
                            document.querySelectorAll('.timeline-event').forEach(eventDiv => {
                                const year = eventDiv.querySelector('.timeline-year').value.trim();
                                const title = eventDiv.querySelector('.timeline-title').value.trim();
                                const description = eventDiv.querySelector('.timeline-description').value.trim();
                                
                                if (year || title || description) {
                                    events.push(`${year}|${title}|${description}`);
                                }
                            });
                            
                            document.getElementById('content').value = events.join('||');
                        }
                        
                        // Sort timeline events by year
                        function sortTimelineEvents() {
                            const container = document.getElementById('timeline-events');
                            const events = Array.from(container.children);
                            
                            events.sort((a, b) => {
                                const yearA = parseInt(a.querySelector('.timeline-year').value) || 0;
                                const yearB = parseInt(b.querySelector('.timeline-year').value) || 0;
                                return yearA - yearB;
                            });
                            
                            // Clear container and re-add sorted events
                            container.innerHTML = '';
                            events.forEach(event => container.appendChild(event));
                            reindexEvents();
                        }
                        
                        // Initialize timeline events on page load
                        document.addEventListener('DOMContentLoaded', function() {
                            const existingEvents = parseTimelineData();
                            const container = document.getElementById('timeline-events');
                            
                            if (existingEvents.length > 0) {
                                existingEvents.forEach((event, index) => {
                                    const eventHTML = createTimelineEventHTML(event, index);
                                    container.insertAdjacentHTML('beforeend', eventHTML);
                                    timelineEventCount++;
                                });
                            } else {
                                // Add one empty event by default
                                addTimelineEvent();
                            }
                        });
                        </script>

                        <style>
                        .timeline-event {
                            transition: all 0.3s ease;
                        }
                        
                        .timeline-event:hover {
                            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                        }
                        
                        .timeline-event button:hover {
                            transform: scale(1.1);
                        }
                        </style>
                        
<?php elseif ($section['section_type'] === 'faq'): ?>
                        <div class="space-y-6">
                            <!-- Enhanced FAQ Management -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-4">
                                    <i class="fas fa-question-circle mr-2"></i>FAQ Management
                                </label>
                                
                                <!-- Visual Preview Section -->
                                <div id="faq-preview" class="mb-6 p-6 border-2 border-gray-200 rounded-lg bg-gradient-to-br from-gray-50 to-blue-50">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-eye mr-2 text-blue-600"></i>Preview Your FAQs
                                        </h3>
                                        <div class="text-sm text-gray-600">
                                            <span id="faq-count">0</span> FAQs configured
                                        </div>
                                    </div>
                                    <div id="faq-display" class="space-y-4">
                                        <!-- FAQs will be displayed here -->
                                    </div>
                                </div>

                                <!-- FAQ Management Interface -->
                                <div class="bg-white border-2 border-gray-300 rounded-lg p-6 mb-4">
                                    <div class="flex items-center justify-between mb-4">
                                        <h4 class="font-semibold text-gray-900 flex items-center">
                                            <i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New FAQ
                                        </h4>
                                        <div class="text-sm text-gray-500">
                                            Fill out the form below to create a new FAQ
                                        </div>
                                    </div>
                                    
                                    <!-- FAQ Details Form -->
                                    <div class="space-y-4">
                                        <!-- Question -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-question mr-1"></i>Question *
                                            </label>
                                            <input type="text" id="new-faq-question" placeholder="e.g., What is your refund policy?" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>

                                        <!-- Answer -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-comment mr-1"></i>Answer *
                                            </label>
                                            <textarea id="new-faq-answer" rows="4" placeholder="Provide a detailed answer to the question..." 
                                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                                        </div>

                                        <!-- Category (Optional) -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                <i class="fas fa-tag mr-1"></i>Category (Optional)
                                            </label>
                                            <input type="text" id="new-faq-category" placeholder="e.g., Billing, Support, General" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                                            <button type="button" onclick="addNewFAQ()" 
                                                    class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200 text-sm font-medium">
                                                <i class="fas fa-plus mr-2"></i>➕ Add FAQ
                                            </button>
                                            <button type="button" onclick="cancelFAQEdit()" style="display: none;"
                                                    class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200 text-sm font-medium">
                                                <i class="fas fa-times mr-2"></i>Cancel Edit
                                            </button>
                                            <button type="button" onclick="clearAllFAQs()" 
                                                    class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200 text-sm font-medium">
                                                <i class="fas fa-trash mr-2"></i>Clear All FAQs
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <h4 class="font-medium text-blue-900 mb-2">
                                        <i class="fas fa-lightbulb mr-2"></i>FAQ Tips
                                    </h4>
                                    <ul class="text-sm text-blue-800 space-y-1">
                                        <li>• <strong>Clear Questions:</strong> Write questions the way customers would ask them</li>
                                        <li>• <strong>Complete Answers:</strong> Provide thorough answers to avoid follow-up questions</li>
                                        <li>• <strong>Categories:</strong> Group related FAQs together for better organization</li>
                                        <li>• <strong>Edit:</strong> Click the "Edit" button on any FAQ to modify it</li>
                                    </ul>
                                </div>

                                <!-- Hidden textarea for form submission -->
                                <textarea id="content" name="content" rows="4" required
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm font-mono hidden"
                                          placeholder="FAQ data will appear here automatically..."><?= htmlspecialchars($section['content']) ?></textarea>
                                
                                <div class="flex justify-between items-center mt-2">
                                    <p class="text-sm text-gray-500">
                                        <!-- <i class="fas fa-info-circle mr-1"></i>Format: Question|Answer|Category (separated by ||) -->
                                    </p>
                                    <div class="text-sm text-gray-600">
                                        <span id="faq-textarea-length">0</span> characters
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($section['section_type'] === 'newsletter'): ?>
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Newsletter Description</label>
                            <textarea id="content" name="content" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Subscribe to our newsletter for updates..."><?= htmlspecialchars($section['content']) ?></textarea>
                        </div>
                        

<?php elseif ($section['section_type'] === 'cta'): ?>
<div class="space-y-6">
    <!-- CTA Content -->
    <div>
        <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Call to Action Text *</label>
        <textarea id="content" name="content" rows="3" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Take action today and transform your business..."><?= htmlspecialchars($section['content']) ?></textarea>
    </div>
    
    <!-- Primary Button Section -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="font-medium text-blue-900 mb-3">
            <i class="fas fa-mouse-pointer mr-2"></i>Primary Button
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="button_text" class="block text-sm font-medium text-gray-700 mb-2">Button Text</label>
                <input type="text" id="button_text" name="button_text"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                       value="<?= htmlspecialchars($section['button_text'] ?? '') ?>"
                       placeholder="Get Started Now">
            </div>
            <div>
                <label for="button_url" class="block text-sm font-medium text-gray-700 mb-2">Button URL</label>
                <input type="url" id="button_url" name="button_url"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                       value="<?= htmlspecialchars($section['button_url'] ?? '') ?>"
                       placeholder="https://example.com/contact">
            </div>
        </div>
        
        <!-- Primary Button Color Settings -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label for="button_bg_color" class="block text-sm font-medium text-gray-700 mb-2">Background Color</label>
                <div class="flex space-x-2">
                    <input type="color" id="button_bg_color" name="button_bg_color"
                           class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                           value="<?= htmlspecialchars($section['button_bg_color'] ?? '#3b82f6') ?>">
                    <input type="text" id="button_bg_text"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($section['button_bg_color'] ?? '#3b82f6') ?>"
                           onchange="document.getElementById('button_bg_color').value = this.value"
                           placeholder="#3b82f6">
                </div>
                <div class="mt-2 flex space-x-2">
                    <button type="button" class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700" onclick="setButtonColor('button_bg_color', '#3b82f6')">Blue</button>
                    <button type="button" class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700" onclick="setButtonColor('button_bg_color', '#16a34a')">Green</button>
                    <button type="button" class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700" onclick="setButtonColor('button_bg_color', '#dc2626')">Red</button>
                    <button type="button" class="px-3 py-1 text-xs bg-purple-600 text-white rounded hover:bg-purple-700" onclick="setButtonColor('button_bg_color', '#9333ea')">Purple</button>
                </div>
            </div>
            
            <div>
                <label for="button_text_color" class="block text-sm font-medium text-gray-700 mb-2">Text Color</label>
                <div class="flex space-x-2">
                    <input type="color" id="button_text_color" name="button_text_color"
                           class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                           value="<?= htmlspecialchars($section['button_text_color'] ?? '#ffffff') ?>">
                    <input type="text" id="button_text_color_text"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($section['button_text_color'] ?? '#ffffff') ?>"
                           onchange="document.getElementById('button_text_color').value = this.value"
                           placeholder="#ffffff">
                </div>
                <div class="mt-2 flex space-x-2">
                    <button type="button" class="px-3 py-1 text-xs bg-white border text-black rounded hover:bg-gray-50" onclick="setButtonColor('button_text_color', '#ffffff')">White</button>
                    <button type="button" class="px-3 py-1 text-xs bg-black text-white rounded hover:bg-gray-800" onclick="setButtonColor('button_text_color', '#000000')">Black</button>
                    <button type="button" class="px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700" onclick="setButtonColor('button_text_color', '#4b5563')">Gray</button>
                </div>
            </div>
        </div>
        
        <!-- Primary Button Preview -->
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
            <button type="button" id="primary-button-preview" 
                    class="px-6 py-3 rounded-lg font-medium transition duration-200 shadow-md hover:shadow-lg transform hover:scale-105"
                    style="background-color: <?= htmlspecialchars($section['button_bg_color'] ?? '#3b82f6') ?>; color: <?= htmlspecialchars($section['button_text_color'] ?? '#ffffff') ?>;">
                <?= htmlspecialchars($section['button_text'] ?? 'Get Started Now') ?>
            </button>
        </div>
    </div>
    
    <!-- Secondary Button Section -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <div class="flex items-center justify-between mb-3">
            <h4 class="font-medium text-gray-900">
                <i class="fas fa-hand-pointer mr-2"></i>Secondary Button (Optional)
            </h4>
            <label class="flex items-center">
                <input type="checkbox" id="enable_second_button" name="enable_second_button" 
                       class="mr-2 rounded" 
                       <?= !empty($section['button_text_2']) ? 'checked' : '' ?>
                       onchange="toggleSecondButton()">
                <span class="text-sm text-gray-600">Enable Second Button</span>
            </label>
        </div>
        
        <div id="second-button-fields" class="<?= empty($section['button_text_2']) ? 'hidden' : '' ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="button_text_2" class="block text-sm font-medium text-gray-700 mb-2">Button Text</label>
                    <input type="text" id="button_text_2" name="button_text_2"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($section['button_text_2'] ?? '') ?>"
                           placeholder="Learn More">
                </div>
                <div>
                    <label for="button_url_2" class="block text-sm font-medium text-gray-700 mb-2">Button URL</label>
                    <input type="url" id="button_url_2" name="button_url_2"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($section['button_url_2'] ?? '') ?>"
                           placeholder="https://example.com/learn-more">
                </div>
            </div>
            
            <!-- Secondary Button Color Settings -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="button_bg_color_2" class="block text-sm font-medium text-gray-700 mb-2">Background Color</label>
                    <div class="flex space-x-2">
                        <input type="color" id="button_bg_color_2" name="button_bg_color_2"
                               class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                               value="<?= htmlspecialchars($section['button_bg_color_2'] ?? '#6b7280') ?>">
                        <input type="text" id="button_bg_text_2"
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($section['button_bg_color_2'] ?? '#6b7280') ?>"
                               onchange="document.getElementById('button_bg_color_2').value = this.value"
                               placeholder="#6b7280">
                    </div>
                    <div class="mt-2 flex space-x-2">
                        <button type="button" class="px-3 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700" onclick="setButtonColor('button_bg_color_2', '#6b7280')">Gray</button>
                        <button type="button" class="px-3 py-1 text-xs bg-orange-600 text-white rounded hover:bg-orange-700" onclick="setButtonColor('button_bg_color_2', '#ea580c')">Orange</button>
                        <button type="button" class="px-3 py-1 text-xs bg-teal-600 text-white rounded hover:bg-teal-700" onclick="setButtonColor('button_bg_color_2', '#0d9488')">Teal</button>
                        <button type="button" class="px-3 py-1 text-xs bg-white border text-black rounded hover:bg-gray-50" onclick="setButtonColor('button_bg_color_2', 'transparent')">Transparent</button>
                    </div>
                </div>
                
                <div>
                    <label for="button_text_color_2" class="block text-sm font-medium text-gray-700 mb-2">Text Color</label>
                    <div class="flex space-x-2">
                        <input type="color" id="button_text_color_2" name="button_text_color_2"
                               class="w-16 h-10 border border-gray-300 rounded cursor-pointer"
                               value="<?= htmlspecialchars($section['button_text_color_2'] ?? '#ffffff') ?>">
                        <input type="text" id="button_text_color_2_text"
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($section['button_text_color_2'] ?? '#ffffff') ?>"
                               onchange="document.getElementById('button_text_color_2').value = this.value"
                               placeholder="#ffffff">
                    </div>
                    <div class="mt-2 flex space-x-2">
                        <button type="button" class="px-3 py-1 text-xs bg-white border text-black rounded hover:bg-gray-50" onclick="setButtonColor('button_text_color_2', '#ffffff')">White</button>
                        <button type="button" class="px-3 py-1 text-xs bg-black text-white rounded hover:bg-gray-800" onclick="setButtonColor('button_text_color_2', '#000000')">Black</button>
                        <button type="button" class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700" onclick="setButtonColor('button_text_color_2', '#3b82f6')">Blue</button>
                    </div>
                </div>
            </div>
            
            <!-- Secondary Button Style Options -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Button Style</label>
                <div class="flex space-x-4">
                    <label class="flex items-center">
                        <input type="radio" name="button_style_2" value="solid" 
                               class="mr-2" 
                               <?= ($section['button_style_2'] ?? 'solid') === 'solid' ? 'checked' : '' ?>
                               onchange="updateSecondButtonPreview()">
                        <span class="text-sm">Solid</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="button_style_2" value="outline" 
                               class="mr-2"
                               <?= ($section['button_style_2'] ?? 'solid') === 'outline' ? 'checked' : '' ?>
                               onchange="updateSecondButtonPreview()">
                        <span class="text-sm">Outline</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="button_style_2" value="ghost" 
                               class="mr-2"
                               <?= ($section['button_style_2'] ?? 'solid') === 'ghost' ? 'checked' : '' ?>
                               onchange="updateSecondButtonPreview()">
                        <span class="text-sm">Ghost</span>
                    </label>
                </div>
            </div>
            
            <!-- Secondary Button Preview -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                <button type="button" id="secondary-button-preview" 
                        class="px-6 py-3 rounded-lg font-medium transition duration-200 shadow-md hover:shadow-lg transform hover:scale-105"
                        style="background-color: <?= htmlspecialchars($section['button_bg_color_2'] ?? '#6b7280') ?>; color: <?= htmlspecialchars($section['button_text_color_2'] ?? '#ffffff') ?>;">
                    <?= htmlspecialchars($section['button_text_2'] ?? 'Learn More') ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Layout Options -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <h4 class="font-medium text-yellow-900 mb-3">
            <i class="fas fa-layout mr-2"></i>Button Layout
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Button Alignment</label>
                <select id="button_alignment" name="button_alignment" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="center" <?= ($section['button_alignment'] ?? 'center') === 'center' ? 'selected' : '' ?>>Center</option>
                    <option value="left" <?= ($section['button_alignment'] ?? 'center') === 'left' ? 'selected' : '' ?>>Left</option>
                    <option value="right" <?= ($section['button_alignment'] ?? 'center') === 'right' ? 'selected' : '' ?>>Right</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Button Layout</label>
                <select id="button_layout" name="button_layout" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="horizontal" <?= ($section['button_layout'] ?? 'horizontal') === 'horizontal' ? 'selected' : '' ?>>Side by Side</option>
                    <option value="vertical" <?= ($section['button_layout'] ?? 'horizontal') === 'vertical' ? 'selected' : '' ?>>Stacked</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Combined Preview -->
    <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h4 class="font-medium text-gray-900 mb-4">
            <i class="fas fa-eye mr-2"></i>Complete CTA Preview
        </h4>
        <div class="text-center">
            <div class="mb-6">
                <p class="text-lg text-gray-700"><?= htmlspecialchars($section['content'] ?: 'Take action today and transform your business...') ?></p>
            </div>
            <div id="buttons-preview-container" class="flex justify-center items-center space-x-4">
                <button type="button" id="preview-primary-btn" 
                        class="px-6 py-3 rounded-lg font-medium transition duration-200 shadow-md hover:shadow-lg transform hover:scale-105"
                        style="background-color: <?= htmlspecialchars($section['button_bg_color'] ?? '#3b82f6') ?>; color: <?= htmlspecialchars($section['button_text_color'] ?? '#ffffff') ?>;">
                    <?= htmlspecialchars($section['button_text'] ?: 'Get Started Now') ?>
                </button>
                <?php if (!empty($section['button_text_2'])): ?>
                <button type="button" id="preview-secondary-btn" 
                        class="px-6 py-3 rounded-lg font-medium transition duration-200 shadow-md hover:shadow-lg transform hover:scale-105"
                        style="background-color: <?= htmlspecialchars($section['button_bg_color_2'] ?? '#6b7280') ?>; color: <?= htmlspecialchars($section['button_text_color_2'] ?? '#ffffff') ?>;">
                    <?= htmlspecialchars($section['button_text_2']) ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
                        

<?php elseif ($section['section_type'] === 'contact_form'): ?>
    <div class="space-y-4">
        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
            <h3 class="text-sm font-medium text-blue-900 mb-2">Contact Form Section</h3>
            <p class="text-sm text-blue-800">Configure the contact form and email settings.</p>
        </div>
        
        <!-- Form Title -->
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Form Title</label>
            <input type="text" id="title" name="title" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                   value="<?= htmlspecialchars($section['title']) ?>"
                   placeholder="Contact Us">
        </div>

        <!-- Form Subtitle -->
        <div>
            <label for="subtitle" class="block text-sm font-medium text-gray-700 mb-2">Form Subtitle (Optional)</label>
            <input type="text" id="subtitle" name="subtitle" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                   value="<?= htmlspecialchars($section['subtitle'] ?? '') ?>"
                   placeholder="Get in touch with us">
        </div>

        <!-- Google reCAPTCHA Section -->
        <div class="border-t pt-4">
            <h4 class="text-lg font-medium text-gray-900 mb-4">Google reCAPTCHA v2</h4>
            
            <?php
            // Parse email config from content field
            $emailConfig = [];
            if (!empty($section['content'])) {
                $configLines = explode("\n", $section['content']);
                foreach ($configLines as $line) {
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $emailConfig[trim($key)] = trim($value);
                    }
                }
            }
            
            // Check if reCAPTCHA is enabled
            $recaptchaEnabled = !empty($emailConfig['recaptcha_enabled']) && $emailConfig['recaptcha_enabled'] === 'true';
            $hasExistingPassword = !empty($emailConfig['smtp_password']);
            
            // Server-side password decryption function
            function decryptPasswordPHP($encryptedPassword) {
                $key = 'your-secure-password-key-2024';
                $salt = 'salt';
                $iterations = 100000;
                
                try {
                    // Check if password is already plain text
                    if (strlen($encryptedPassword) < 40 && !preg_match('/^[A-Za-z0-9+\/]+=*$/', $encryptedPassword)) {
                        return $encryptedPassword;
                    }
                    
                    // Decode base64
                    $data = base64_decode($encryptedPassword, true);
                    if ($data === false || strlen($data) < 28) {
                        return $encryptedPassword;
                    }
                    
                    // Extract components
                    $iv = substr($data, 0, 12);
                    $tag = substr($data, -16);
                    $encrypted = substr($data, 12, -16);
                    
                    // Derive key using PBKDF2
                    $derivedKey = hash_pbkdf2('sha256', $key, $salt, $iterations, 32, true);
                    
                    // Decrypt using AES-256-GCM
                    $decrypted = openssl_decrypt(
                        $encrypted,
                        'aes-256-gcm',
                        $derivedKey,
                        OPENSSL_RAW_DATA,
                        $iv,
                        $tag
                    );
                    
                    if ($decrypted === false) {
                        return $encryptedPassword; // Return original if decryption fails
                    }
                    
                    return $decrypted;
                    
                } catch (Exception $e) {
                    return $encryptedPassword; // Return original if any error
                }
            }
            
            // Decrypt the SMTP password for display
            $decryptedPassword = '';
            if ($hasExistingPassword) {
                $decryptedPassword = decryptPasswordPHP($emailConfig['smtp_password']);
            }
            ?>
            
            <!-- reCAPTCHA Enable/Disable Toggle -->
            <div class="flex items-center mb-4">
                <input type="checkbox" id="enable_recaptcha" name="enable_recaptcha" 
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                       <?= $recaptchaEnabled ? 'checked' : '' ?>>
                <label for="enable_recaptcha" class="ml-2 block text-sm font-medium text-gray-900">
                    Enable Google reCAPTCHA v2
                </label>
            </div>
            
            <!-- reCAPTCHA Configuration Fields (Hidden by default) -->
            <div id="recaptcha_config" class="space-y-4 <?= !$recaptchaEnabled ? 'hidden' : '' ?>">
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                    <p class="text-sm text-yellow-800">
                        <strong>Note:</strong> You need to register your site with Google reCAPTCHA to get the Site Key and Secret Key. 
                        <a href="https://www.google.com/recaptcha/admin" target="_blank" class="text-blue-600 hover:text-blue-800 underline">
                            Get your keys here
                        </a>
                    </p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="recaptcha_site_key" class="block text-sm font-medium text-gray-700 mb-2">
                            Site Key <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="recaptcha_site_key" name="recaptcha_site_key" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($emailConfig['recaptcha_site_key'] ?? '') ?>"
                               placeholder="6Lc2fhATAAAAAGaAYKSEwjcm5n0lxMqHoSdgE8g3">
                        <p class="text-xs text-gray-500 mt-1">This key is used in the HTML code your site serves to users.</p>
                    </div>
                    
                    <div>
                        <label for="recaptcha_secret_key" class="block text-sm font-medium text-gray-700 mb-2">
                            Secret Key <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" id="recaptcha_secret_key" name="recaptcha_secret_key" 
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                   value="<?= htmlspecialchars($emailConfig['recaptcha_secret_key'] ?? '') ?>"
                                   placeholder="6Lc2fhATAAAAALaX6lNLDL3y8GtN-yFLKMYj8gKH">
                            <button type="button" id="toggle_recaptcha_secret" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">This key is used for communication between your site and Google. Keep it secret.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Configuration Section -->
        <div class="border-t pt-4">
            <h4 class="text-lg font-medium text-gray-900 mb-4">Email Configuration</h4>
            
            <!-- Recipient Email -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="recipient_email" class="block text-sm font-medium text-gray-700 mb-2">Recipient Email</label>
                    <input type="email" id="recipient_email" name="recipient_email" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($emailConfig['recipient_email'] ?? '') ?>"
                           placeholder="admin@yoursite.com" >
                </div>
                
                <div>
                    <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-2">Recipient Name</label>
                    <input type="text" id="recipient_name" name="recipient_name" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($emailConfig['recipient_name'] ?? '') ?>"
                           placeholder="Website Admin">
                </div>
            </div>

            <!-- SMTP Configuration -->
            <div class="mt-4">
                <h5 class="text-md font-medium text-gray-800 mb-3">SMTP Settings</h5>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2">SMTP Host</label>
                        <input type="text" id="smtp_host" name="smtp_host" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($emailConfig['smtp_host'] ?? '') ?>"
                               placeholder="smtp.gmail.com" >
                    </div>
                    
                    <div>
                        <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2">SMTP Port</label>
                        <input type="number" id="smtp_port" name="smtp_port" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($emailConfig['smtp_port'] ?? '587') ?>"
                               placeholder="587" >
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2">SMTP Username</label>
                        <input type="email" id="smtp_username" name="smtp_username" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               value="<?= htmlspecialchars($emailConfig['smtp_username'] ?? '') ?>"
                               placeholder="your-email@gmail.com" >
                    </div>
                    
                    <div>
                        <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2">
                            SMTP Password
                            <?php if ($hasExistingPassword): ?>
                                <span class="text-sm text-green-600">(Decrypted and showing current password)</span>
                            <?php endif; ?>
                        </label>
                        <div class="relative">
                            <input type="password" id="smtp_password" name="smtp_password" 
                                   class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                   value="<?= htmlspecialchars($decryptedPassword) ?>"
                                   placeholder="<?= $hasExistingPassword ? 'Current decrypted password shown' : 'Your app password' ?>"
                                   <?= !$hasExistingPassword ?  : '' ?>>
                            <button type="button" id="toggle_password" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                        <?php if ($hasExistingPassword): ?>
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="text-green-600">✓</span> Password successfully decrypted and displayed. 
                                Edit to change password or leave as-is to keep current password.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 mb-2">Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        <option value="tls" <?= ($emailConfig['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($emailConfig['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
                            <style>
                                #styling-options {
                                    display: none !important;
                                }
                                #basicinfo {
                                    display: none !important;
                                }
                            </style>
    <!-- JavaScript to handle email config in content field with password encryption -->
    <script>
    // Password encryption utility using Web Crypto API (AES-GCM)
    class PasswordEncryption {
        static async generateKey() {
            // Generate a key from a password (use a fixed key for consistency)
            const password = 'your-secure-password-key-2024'; // Change this to your own key
            const enc = new TextEncoder();
            const keyMaterial = await crypto.subtle.importKey(
                'raw',
                enc.encode(password),
                'PBKDF2',
                false,
                ['deriveBits', 'deriveKey']
            );
            
            return await crypto.subtle.deriveKey(
                {
                    name: 'PBKDF2',
                    salt: enc.encode('salt'), // Use a fixed salt for consistency
                    iterations: 100000,
                    hash: 'SHA-256'
                },
                keyMaterial,
                { name: 'AES-GCM', length: 256 },
                false,
                ['encrypt', 'decrypt']
            );
        }
        
        static async encryptPassword(password) {
            const key = await this.generateKey();
            const enc = new TextEncoder();
            const iv = crypto.getRandomValues(new Uint8Array(12)); // 12 bytes for AES-GCM
            
            const encrypted = await crypto.subtle.encrypt(
                { name: 'AES-GCM', iv: iv },
                key,
                enc.encode(password)
            );
            
            // Combine IV and encrypted data
            const combined = new Uint8Array(iv.length + encrypted.byteLength);
            combined.set(iv);
            combined.set(new Uint8Array(encrypted), iv.length);
            
            // Return base64 encoded
            return btoa(String.fromCharCode(...combined));
        }
        
        static async decryptPassword(encryptedPassword) {
            try {
                const key = await this.generateKey();
                const combined = new Uint8Array(atob(encryptedPassword).split('').map(c => c.charCodeAt(0)));
                
                const iv = combined.slice(0, 12);
                const encrypted = combined.slice(12);
                
                const decrypted = await crypto.subtle.decrypt(
                    { name: 'AES-GCM', iv: iv },
                    key,
                    encrypted
                );
                
                return new TextDecoder().decode(decrypted);
            } catch (error) {
                console.error('Decryption failed:', error);
                return null;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Get all email config fields
        const emailFields = [
            'recipient_email', 'recipient_name', 'smtp_host', 'smtp_port',
            'smtp_username', 'smtp_password', 'smtp_encryption'
        ];
        
        // Add reCAPTCHA fields to the config
        const recaptchaFields = ['enable_recaptcha', 'recaptcha_site_key', 'recaptcha_secret_key'];
        const allFields = [...emailFields, ...recaptchaFields];
        
        let hasExistingPassword = <?= $hasExistingPassword ? 'true' : 'false' ?>;
        let passwordChanged = false;
        let originalDecryptedPassword = '<?= addslashes($decryptedPassword) ?>';
        let originalEncryptedPassword = '<?= addslashes($emailConfig['smtp_password'] ?? '') ?>';
        
        // reCAPTCHA toggle functionality
        const enableRecaptchaCheckbox = document.getElementById('enable_recaptcha');
        const recaptchaConfig = document.getElementById('recaptcha_config');
        const recaptchaSiteKey = document.getElementById('recaptcha_site_key');
        const recaptchaSecretKey = document.getElementById('recaptcha_secret_key');
        
        // Toggle reCAPTCHA config visibility
        enableRecaptchaCheckbox.addEventListener('change', function() {
            if (this.checked) {
                recaptchaConfig.classList.remove('hidden');
                // Add smooth slide down animation
                recaptchaConfig.style.opacity = '0';
                recaptchaConfig.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    recaptchaConfig.style.transition = 'all 0.3s ease-in-out';
                    recaptchaConfig.style.opacity = '1';
                    recaptchaConfig.style.transform = 'translateY(0)';
                }, 10);
                
                // Make fields required when enabled
                recaptchaSiteKey.setAttribute('required', 'required');
                recaptchaSecretKey.setAttribute('required', 'required');
            } else {
                // Add smooth slide up animation
                recaptchaConfig.style.transition = 'all 0.3s ease-in-out';
                recaptchaConfig.style.opacity = '0';
                recaptchaConfig.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    recaptchaConfig.classList.add('hidden');
                    recaptchaConfig.style.transition = '';
                }, 300);
                
                // Remove required attribute when disabled
                recaptchaSiteKey.removeAttribute('required');
                recaptchaSecretKey.removeAttribute('required');
                
                // Clear the values
                recaptchaSiteKey.value = '';
                recaptchaSecretKey.value = '';
            }
            updateEmailConfig();
        });
        
        // reCAPTCHA Secret Key visibility toggle
        const toggleRecaptchaSecret = document.getElementById('toggle_recaptcha_secret');
        if (toggleRecaptchaSecret) {
            toggleRecaptchaSecret.addEventListener('click', function() {
                const type = recaptchaSecretKey.getAttribute('type') === 'password' ? 'text' : 'password';
                recaptchaSecretKey.setAttribute('type', type);
                
                // Update icon
                const icon = toggleRecaptchaSecret.querySelector('svg');
                if (type === 'text') {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>';
                } else {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
                }
            });
        }
        
        // Password visibility toggle (existing functionality)
        const passwordInput = document.getElementById('smtp_password');
        const toggleButton = document.getElementById('toggle_password');
        
        if (toggleButton) {
            toggleButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Update icon
                const icon = toggleButton.querySelector('svg');
                if (type === 'text') {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>';
                } else {
                    icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
                }
            });
        }
        
        // Track password changes
        passwordInput.addEventListener('input', function() {
            if (hasExistingPassword && this.value !== originalDecryptedPassword) {
                passwordChanged = true;
            }
        });
        
        // Function to update content field with email config
        async function updateEmailConfig() {
            const config = {};
            
            for (const field of allFields) {
                const element = document.getElementById(field);
                if (element) {
                    if (field === 'enable_recaptcha') {
                        // Handle checkbox
                        config['recaptcha_enabled'] = element.checked ? 'true' : 'false';
                    } else if (element.value && element.value.trim()) {
                        let value = element.value.trim();
                        
                        // Handle password field specially
                        if (field === 'smtp_password') {
                            if (hasExistingPassword && !passwordChanged && value === originalDecryptedPassword) {
                                // Password hasn't changed, use original encrypted password
                                config[field] = originalEncryptedPassword;
                            } else if (value) {
                                // New password entered or password changed, encrypt it
                                try {
                                    const encryptedPassword = await PasswordEncryption.encryptPassword(value);
                                    config[field] = encryptedPassword;
                                    passwordChanged = true;
                                    //console.log('Password encrypted successfully');
                                } catch (error) {
                                    //console.error('Error encrypting password:', error);
                                    alert('Error securing password. Please try again.');
                                    return;
                                }
                            }
                        } else {
                            config[field] = value;
                        }
                    }
                }
            }
            
            // Convert to key=value format for content field
            const configString = Object.entries(config)
                .map(([key, value]) => `${key}=${value}`)
                .join('\n');
            
            // Update the content field (create if it doesn't exist)
            let contentField = document.getElementById('content');
            if (!contentField) {
                contentField = document.createElement('textarea');
                contentField.id = 'content';
                contentField.name = 'content';
                contentField.style.display = 'none';
                document.querySelector('form').appendChild(contentField);
            }
            contentField.value = configString;
        }
        
        // Add event listeners to all config fields
        allFields.forEach(field => {
            const element = document.getElementById(field);
            if (element) {
                if (field === 'smtp_password') {
                    // Special handling for password field with async encryption
                    element.addEventListener('input', async function() {
                        await updateEmailConfig();
                    });
                } else if (field === 'enable_recaptcha') {
                    // Checkbox already has event listener above
                } else {
                    element.addEventListener('input', async function() {
                        await updateEmailConfig();
                    });
                    element.addEventListener('change', updateEmailConfig);
                }
            }
        });
        
        // Form submission handling
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', async function(e) {
                // Validate reCAPTCHA fields if enabled
                if (enableRecaptchaCheckbox.checked) {
                    if (!recaptchaSiteKey.value.trim() || !recaptchaSecretKey.value.trim()) {
                        e.preventDefault();
                        alert('Please fill in both Site Key and Secret Key for Google reCAPTCHA.');
                        return;
                    }
                }
                
                // Show loading state
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    const originalText = submitButton.textContent;
                    submitButton.textContent = 'Saving...';
                    submitButton.disabled = true;
                    
                    // Re-enable after a delay if form submission doesn't redirect
                    setTimeout(() => {
                        submitButton.textContent = originalText;
                        submitButton.disabled = false;
                    }, 5000);
                }
                
                // Make sure config is updated before submission
                await updateEmailConfig();
            });
        }
        
        // Initial update
        updateEmailConfig();
        
        // Add status indicator for password decryption
        if (hasExistingPassword) {
            const passwordField = document.getElementById('smtp_password');
            const statusDiv = document.createElement('div');
            statusDiv.className = 'mt-1 text-xs flex items-center text-green-600';
            statusDiv.innerHTML = `
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Password decrypted successfully and ready for editing
            `;
            passwordField.parentElement.parentElement.appendChild(statusDiv);
        }
    });
    </script>


<?php elseif ($section['section_type'] === 'projects'): ?>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-4">Project Portfolio</label>
        
        <!-- Projects Manager -->
        <div id="projectsManager" class="space-y-4">
            <div class="flex justify-between items-center p-4 bg-blue-50 border border-blue-200 rounded-md">
                <h4 class="font-medium text-blue-900">Manage Projects</h4>
                <div class="space-x-2">
                    <button type="button" onclick="addNewProject()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Add Project
                    </button>
                    <button type="button" onclick="importProjects()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-file-import mr-2"></i>Import Projects
                    </button>
                </div>
            </div>
            
            <div id="projectsList" class="space-y-4">
                <!-- Projects will be loaded here -->
            </div>
        </div>
        
        <!-- Hidden textarea for form submission -->
        <textarea id="content" name="content" class="hidden"><?= htmlspecialchars($section['content']) ?></textarea>
    </div>

    <!-- Project Modal -->
    <div id="projectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Add New Project</h3>
                        <button type="button" onclick="closeProjectModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Basic Project Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Name *</label>
                            <input type="text" id="projectName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Enter project name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Model/Type</label>
                            <select id="projectModel" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Model</option>
                                <option value="Foreign Projects">Foreign Projects</option>
                                <option value="Local Projects">Local Projects</option>
                                <option value="Residential">Residential</option>
                                <option value="Commercial">Commercial</option>
                                <option value="Industrial">Industrial</option>
                                <option value="Infrastructure">Infrastructure</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                            <input type="text" id="projectClient" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Client name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                            <input type="text" id="projectLocation" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Project location">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="projectStatus" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                <option value="COMPLETED">COMPLETED</option>
                                <option value="IN PROGRESS">IN PROGRESS</option>
                                <option value="ON HOLD">ON HOLD</option>
                                <option value="PLANNED">PLANNED</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Value</label>
                            <input type="text" id="projectValue" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., $500,000 or -">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Architects/Partners</label>
                            <input type="text" id="projectArchitects" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Architecture firm or partners">
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project Description</label>
                        <textarea id="projectDescription" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Detailed project description"></textarea>
                    </div>
                    
                    <!-- Our Role -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Our Role</label>
                        <textarea id="projectRole" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., Design of Total MEP System"></textarea>
                    </div>
                    
                    <!-- Project Images -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-4">Project Images</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
                            <div class="text-center">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                <p class="text-gray-600 mb-4">Drag and drop images here, or click to select</p>
                                <input type="file" id="projectImages" multiple accept="image/*" class="hidden" onchange="handleImageUpload(this)">
                                <button type="button" onclick="document.getElementById('projectImages').click()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition duration-200">
                                    Select Images
                                </button>
                            </div>
                        </div>
                        
                        <!-- Image Preview -->
                        <div id="imagePreview" class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                            <!-- Image previews will appear here -->
                        </div>
                    </div>
                    
                    <!-- Additional Fields -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" id="projectStartDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Completion Date</label>
                            <input type="date" id="projectEndDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Technologies/Services</label>
                            <input type="text" id="projectTechnologies" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="e.g., MEP Design, HVAC, Electrical Systems">
                        </div>
                    </div>
                </div>
                
                <div class="p-6 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeProjectModal()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition duration-200">
                        Cancel
                    </button>
                    <button type="button" onclick="saveProject()" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-save mr-2"></i>Save Project
                    </button>
                </div>
            </div>
        </div>
    </div>
<script>
    // Project Management System
// ===== FIXED PROJECT MANAGEMENT SYSTEM =====

// ===== FIXED PROJECT MANAGEMENT SYSTEM =====

// ===== FIXED PROJECT MANAGEMENT SYSTEM =====

let projects = [];
let currentEditingProject = null;
let projectImages = [];

// Initialize projects from existing content
function initializeProjects() {
    const contentTextarea = document.getElementById('content');
    if (contentTextarea && contentTextarea.value.trim()) {
        try {
            //console.log('Loading projects from textarea:', contentTextarea.value);
            const content = contentTextarea.value.trim();
            
            if (content.startsWith('[') || content.startsWith('{')) {
                // New JSON format
                projects = JSON.parse(content);
                //console.log('Loaded projects (JSON format):', projects);
            } else if (content.includes('||') || content.includes('|')) {
                // Old pipe-separated format - convert to new format
                //console.log('Detected old pipe format, converting...');
                parseOldFormatProjects(content);
                //console.log('Converted old format to new:', projects);
            } else {
                // Unknown format
                //console.warn('Unknown content format, initializing empty');
                projects = [];
            }
        } catch (e) {
            console.error('Error parsing projects:', e);
            //console.log('Attempting to parse as old format...');
            parseOldFormatProjects(contentTextarea.value);
        }
    } else {
        projects = [];
        console.log('No existing projects found');
    }
    renderProjects();
}

// Parse old pipe-separated format and convert to new JSON format
function parseOldFormatProjects(content) {
    if (!content) {
        projects = [];
        return;
    }
    
    //console.log('Parsing old format content:', content);
    
    const projectParts = content.split('||');
    //console.log('Split into parts:', projectParts);
    
    projects = projectParts.map((part, index) => {
        const details = part.split('|');
        //console.log(`Project ${index} details:`, details);
        
        return {
            id: Date.now() + index,
            name: details[0] || '',
            description: details[1] || '',
            images: details[2] ? [details[2]] : [],
            githubUrl: details[3] || '',
            demoUrl: details[4] || '',
            technologies: details[5] || '',
            model: details[6] || '',
            client: details[7] || '',
            location: details[8] || '',
            status: details[9] || 'COMPLETED',
            value: details[10] || '',
            architects: details[11] || '',
            role: details[12] || '',
            startDate: details[13] || '',
            endDate: details[14] || ''
        };
    }).filter(project => project.name.trim());
    
    //console.log('Parsed projects:', projects);
    
    // Auto-convert to JSON format
    if (projects.length > 0) {
        //console.log('Auto-converting old format to JSON...');
        updateContent();
    }
}

// Render projects list
function renderProjects() {
    const projectsList = document.getElementById('projectsList');
    if (!projectsList) {
        console.error('Projects list container not found');
        return;
    }
    
    //console.log('Rendering', projects.length, 'projects');
    
    if (projects.length === 0) {
        projectsList.innerHTML = `
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-briefcase text-4xl mb-4"></i>
                <p>No projects added yet. Click "Add Project" to get started.</p>
            </div>
        `;
        return;
    }
    
    projectsList.innerHTML = projects.map((project, index) => `
        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">${escapeHtml(project.name)}</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm text-gray-600 mb-3">
                        ${project.model ? `<div><strong>Model:</strong> ${escapeHtml(project.model)}</div>` : ''}
                        ${project.client ? `<div><strong>Client:</strong> ${escapeHtml(project.client)}</div>` : ''}
                        ${project.location ? `<div><strong>Location:</strong> ${escapeHtml(project.location)}</div>` : ''}
                        <div><strong>Status:</strong> <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(project.status)}">${project.status}</span></div>
                    </div>
                    ${project.description ? `<p class="text-gray-700 text-sm mb-2">${escapeHtml(project.description).substring(0, 150)}${project.description.length > 150 ? '...' : ''}</p>` : ''}
                    ${project.role ? `<p class="text-blue-600 text-sm"><strong>Our Role:</strong> ${escapeHtml(project.role)}</p>` : ''}
                </div>
                <div class="ml-4 flex flex-col space-y-2">
                    ${project.images && project.images.length > 0 ? `
                        <div class="flex -space-x-2">
                            ${project.images.slice(0, 3).map(img => `
                                <img src="${getFullImageUrl(img)}" alt="Project image" class="w-12 h-12 rounded-lg border-2 border-white object-cover" 
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDgiIGhlaWdodD0iNDgiIHZpZXdCb3g9IjAgMCA0OCA0OCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQ4IiBoZWlnaHQ9IjQ4IiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0yNCAyN0wyNyAyNEwzMCAyN0wyNyAzMEwyNCAyN1oiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
                            `).join('')}
                            ${project.images.length > 3 ? `<div class="w-12 h-12 rounded-lg border-2 border-white bg-gray-100 flex items-center justify-center text-xs text-gray-600">+${project.images.length - 3}</div>` : ''}
                        </div>
                    ` : ''}
                    <div class="flex space-x-2">
                        <button type="button" onclick="editProject(${index})" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button type="button" onclick="deleteProject(${index})" class="text-red-600 hover:text-red-800 text-sm">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Get status color classes
function getStatusColor(status) {
    switch (status) {
        case 'COMPLETED': return 'bg-green-100 text-green-800';
        case 'IN PROGRESS': return 'bg-blue-100 text-blue-800';
        case 'ON HOLD': return 'bg-yellow-100 text-yellow-800';
        case 'PLANNED': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

// Add new project
function addNewProject() {
    //console.log('Adding new project...');
    currentEditingProject = null;
    projectImages = [];
    clearProjectForm();
    document.getElementById('modalTitle').textContent = 'Add New Project';
    document.getElementById('projectModal').classList.remove('hidden');
}

// Edit existing project
function editProject(index) {
    //console.log('Editing project at index:', index);
    if (!projects[index]) {
        console.error('Project not found at index:', index);
        return;
    }
    
    currentEditingProject = index;
    const project = projects[index];
    projectImages = [...(project.images || [])];
    
    //console.log('Editing project:', project);
    
    // Populate ALL form fields
    const formFields = {
        'projectName': project.name || '',
        'projectModel': project.model || '',
        'projectClient': project.client || '',
        'projectLocation': project.location || '',
        'projectStatus': project.status || 'COMPLETED',
        'projectValue': project.value || '',
        'projectArchitects': project.architects || '',
        'projectDescription': project.description || '',
        'projectRole': project.role || '',
        'projectStartDate': project.startDate || '',
        'projectEndDate': project.endDate || '',
        'projectTechnologies': project.technologies || ''
    };
    
    // Set all form field values
    Object.keys(formFields).forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.value = formFields[fieldId];
            //console.log(`Set ${fieldId} to:`, formFields[fieldId]);
        } else {
            console.warn(`Form field ${fieldId} not found`);
        }
    });
    
    updateImagePreview();
    document.getElementById('modalTitle').textContent = 'Edit Project';
    document.getElementById('projectModal').classList.remove('hidden');
}

// Delete project
function deleteProject(index) {
    if (confirm('Are you sure you want to delete this project?')) {
        //console.log('Deleting project at index:', index);
        projects.splice(index, 1);
        updateContent();
        renderProjects();
    }
}

// Clear project form
function clearProjectForm() {
    //console.log('Clearing project form...');
    
    const formFields = [
        'projectName', 'projectModel', 'projectClient', 'projectLocation',
        'projectStatus', 'projectValue', 'projectArchitects', 'projectDescription',
        'projectRole', 'projectStartDate', 'projectEndDate', 'projectTechnologies'
    ];
    
    formFields.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.value = '';
        }
    });
    
    // Reset status to default
    const statusField = document.getElementById('projectStatus');
    if (statusField) {
        statusField.value = 'COMPLETED';
    }
    
    projectImages = [];
    updateImagePreview();
}

// Close project modal
function closeProjectModal() {
    document.getElementById('projectModal').classList.add('hidden');
    projectImages = [];
    currentEditingProject = null;
}

// FIXED: Save project function - Only store uploads/filename.jpg format
function saveProject() {
    //console.log('=== SAVE PROJECT FUNCTION CALLED ===');
    
    // Get ALL form values with validation
    const formData = {
        name: document.getElementById('projectName')?.value?.trim() || '',
        model: document.getElementById('projectModel')?.value?.trim() || '',
        client: document.getElementById('projectClient')?.value?.trim() || '',
        location: document.getElementById('projectLocation')?.value?.trim() || '',
        status: document.getElementById('projectStatus')?.value?.trim() || 'COMPLETED',
        value: document.getElementById('projectValue')?.value?.trim() || '',
        architects: document.getElementById('projectArchitects')?.value?.trim() || '',
        description: document.getElementById('projectDescription')?.value?.trim() || '',
        role: document.getElementById('projectRole')?.value?.trim() || '',
        startDate: document.getElementById('projectStartDate')?.value?.trim() || '',
        endDate: document.getElementById('projectEndDate')?.value?.trim() || '',
        technologies: document.getElementById('projectTechnologies')?.value?.trim() || ''
    };
    
    //console.log('Form data collected:', formData);
    
    // Validation
    if (!formData.name) {
        alert('Project name is required');
        document.getElementById('projectName')?.focus();
        return;
    }
    
    // FIXED: Filter out data URLs and only keep uploads/filename.jpg format
    const validImages = projectImages.filter(img => {
        // Skip data URLs completely
        if (img.startsWith('data:')) {
            console.warn('Skipping data URL - image should be uploaded first');
            return false;
        }
        return true;
    }).map(img => {
        // Clean to uploads/filename.jpg format
        if (img.includes('uploads/')) {
            const uploadsIndex = img.indexOf('uploads/');
            return img.substring(uploadsIndex);
        }
        
        // If it's just a filename, add uploads/ prefix
        if (!img.includes('/') && !img.startsWith('http')) {
            return 'uploads/' + img;
        }
        
        // Keep external URLs as-is for now
        if (img.startsWith('http')) {
            return img;
        }
        
        return img;
    });
    
    // console.log('Original images:', projectImages);
    // console.log('Valid images (uploads/ format only):', validImages);
    
    // Show warning if data URLs were found
    if (projectImages.some(img => img.startsWith('data:'))) {
        alert('Warning: Some images are not uploaded yet. Please upload images to server first.');
        return;
    }
    
    // Create complete project object
    const projectData = {
        id: currentEditingProject !== null ? projects[currentEditingProject].id : Date.now(),
        name: formData.name,
        model: formData.model,
        client: formData.client,
        location: formData.location,
        status: formData.status,
        value: formData.value,
        architects: formData.architects,
        description: formData.description,
        role: formData.role,
        startDate: formData.startDate,
        endDate: formData.endDate,
        technologies: formData.technologies,
        images: validImages, // FIXED: Only uploads/filename.jpg format
        githubUrl: '', 
        demoUrl: ''    
    };
    
    //console.log('Complete project data with clean images:', projectData);
    
    // Save or update project
    if (currentEditingProject !== null) {
        //console.log('UPDATING existing project at index:', currentEditingProject);
        projects[currentEditingProject] = projectData;
        showNotification('✅ Project updated successfully!', 'success');
    } else {
        //console.log('ADDING new project');
        projects.push(projectData);
        showNotification('✅ Project added successfully!', 'success');
    }
    
    //console.log('Projects array after save:', projects.length, 'projects');
    
    // Update content textarea and UI
    updateContent();
    renderProjects();
    closeProjectModal();
}

// FIXED: Update content function
function updateContent() {
    const contentTextarea = document.getElementById('content');
    if (!contentTextarea) {
        console.error('Content textarea not found!');
        return;
    }
    
    try {
        // Save as JSON format for better data preservation
        const jsonData = JSON.stringify(projects, null, 2);
        //console.log('Saving projects to textarea:', jsonData);
        
        contentTextarea.value = jsonData;
        
        // Trigger change event for any form listeners
        const event = new Event('input', { bubbles: true });
        contentTextarea.dispatchEvent(event);
        
        // console.log('Content textarea updated successfully');
        // console.log('Textarea value length:', contentTextarea.value.length);
        
    } catch (error) {
        console.error('Error updating content textarea:', error);
        alert('Error saving project data: ' + error.message);
    }
}

// Handle image upload - FIXED: Upload to server immediately
function handleImageUpload(input) {
    const files = Array.from(input.files);
    //console.log('Handling image upload:', files.length, 'files');
    
    if (files.length === 0) return;
    
    // Show uploading message
    const uploadStatus = document.createElement('div');
    uploadStatus.id = 'upload-status';
    uploadStatus.className = 'fixed top-4 right-4 bg-blue-500 text-white px-4 py-2 rounded-lg z-50';
    uploadStatus.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading images...';
    document.body.appendChild(uploadStatus);
    
    // Upload each file to server
    files.forEach(file => {
        if (file.type.startsWith('image/')) {
            uploadImageToServer(file);
        }
    });
    
    // Clear the input
    input.value = '';
}

// FIXED: Upload image to server and get uploads/filename.jpg path
function uploadImageToServer(file) {
    const formData = new FormData();
    formData.append('image', file);
    
    fetch('upload-image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        const uploadStatus = document.getElementById('upload-status');
        if (uploadStatus) uploadStatus.remove();
        
        if (data.success) {
            // FIXED: Store only uploads/filename.jpg format
            let cleanPath = data.url;
            
            // Extract uploads/filename.jpg from response
            if (cleanPath.includes('uploads/')) {
                const uploadsIndex = cleanPath.indexOf('uploads/');
                cleanPath = cleanPath.substring(uploadsIndex);
            }
            
            //console.log('Image uploaded successfully:', cleanPath);
            projectImages.push(cleanPath);
            updateImagePreview();
            
            // Show success message
            showNotification('✅ Image uploaded successfully!', 'success');
        } else {
            //console.error('Upload failed:', data.error);
            showNotification('❌ Upload failed: ' + data.error, 'error');
        }
    })
    .catch(error => {
        const uploadStatus = document.getElementById('upload-status');
        if (uploadStatus) uploadStatus.remove();
        
        //console.error('Upload error:', error);
        showNotification('❌ Upload failed: ' + error.message, 'error');
    });
}

// Show notification helper
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 3000);
}

// Integration with existing image picker system
function selectImageForProject(url) {
    //console.log('Selecting image for project:', url);
    
    // Clean the URL to proper format
    const cleanedUrl = cleanImagePath(url);
    projectImages.push(cleanedUrl);
    
    //console.log('Added image:', cleanedUrl);
    updateImagePreview();
}

// Override the global selectImage function when project modal is open
function setupImagePickerIntegration() {
    // Store original function
    if (!window.originalSelectImage) {
        window.originalSelectImage = window.selectImage || function() {};
    }
    
    // Override when project modal is open
    window.selectImage = function(url) {
        const projectModal = document.getElementById('projectModal');
        if (projectModal && !projectModal.classList.contains('hidden')) {
            selectImageForProject(url);
            closeImagePicker();
            return;
        }
        
        // Call original function for other cases
        if (window.originalSelectImage) {
            window.originalSelectImage(url);
        }
    };
}

// Update image preview
function updateImagePreview() {
    const preview = document.getElementById('imagePreview');
    if (!preview) return;
    
    //console.log('Updating image preview with', projectImages.length, 'images');
    
    preview.innerHTML = projectImages.map((img, index) => `
        <div class="relative group">
            <img src="${getFullImageUrl(img)}" alt="Project image ${index + 1}" class="w-full h-24 object-cover rounded-lg border"
                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iOTYiIGhlaWdodD0iOTYiIHZpZXdCb3g9IjAgMCA5NiA5NiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9Ijk2IiBoZWlnaHQ9Ijk2IiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik00OCA1NEw1NCA0OEw2MCA1NEw1NCA2MEw0OCA1NFoiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
            <div class="absolute bottom-1 left-1 right-1 bg-black bg-opacity-75 text-white text-xs p-1 rounded">
                ${getImageDisplayName(img)}
            </div>
            <button type="button" onclick="removeProjectImage(${index})" class="absolute top-1 right-1 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    `).join('');
}

// Helper function to get full image URL
function getFullImageUrl(imagePath) {
    if (!imagePath) return '';
    
    // If it's already a full URL (starts with http/https), return as-is
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
        return imagePath;
    }
    
    // If it's a data URL (base64), return as-is
    if (imagePath.startsWith('data:')) {
        return imagePath;
    }
    
    // If it starts with uploads/, construct full URL
    if (imagePath.startsWith('uploads/')) {
        const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '');
        return baseUrl + '/../' + imagePath;
    }
    
    // If it's a relative path without uploads/, assume it's in uploads/
    const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '');
    return baseUrl + '/../uploads/' + imagePath;
}

// Override the global selectImage function when project modal is open
function setupImagePickerIntegration() {
    // Store original function
    if (!window.originalSelectImage) {
        window.originalSelectImage = window.selectImage || function() {};
    }
    
    // Override when project modal is open
    window.selectImage = function(url) {
        const projectModal = document.getElementById('projectModal');
        if (projectModal && !projectModal.classList.contains('hidden')) {
            selectImageForProject(url);
            closeImagePicker();
            return;
        }
        
        // Call original function for other cases
        if (window.originalSelectImage) {
            window.originalSelectImage(url);
        }
    };
}

// Helper function to get display name for image
function getImageDisplayName(imagePath) {
    if (!imagePath) return 'No image';
    
    if (imagePath.startsWith('data:')) {
        return 'Uploaded image';
    }
    
    if (imagePath.startsWith('http')) {
        try {
            const url = new URL(imagePath);
            return url.pathname.split('/').pop() || 'External image';
        } catch {
            return 'External image';
        }
    }
    
    // For uploads/filename.jpg format - show just the filename
    if (imagePath.includes('/')) {
        return imagePath.split('/').pop();
    }
    
    return imagePath;
}

// Remove project image
function removeProjectImage(index) {
    //console.log('Removing image at index:', index);
    projectImages.splice(index, 1);
    updateImagePreview();
}

// Import projects from JSON
function importProjects() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.json';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const importedProjects = JSON.parse(e.target.result);
                    if (Array.isArray(importedProjects)) {
                        projects = [...projects, ...importedProjects];
                        updateContent();
                        renderProjects();
                        alert('Projects imported successfully!');
                    } else {
                        alert('Invalid JSON format');
                    }
                } catch (error) {
                    console.error('Import error:', error);
                    alert('Error parsing JSON file: ' + error.message);
                }
            };
            reader.readAsText(file);
        }
    };
    input.click();
}

// Utility function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    //console.log('=== PROJECT SECTION INITIALIZING ===');
    if (document.getElementById('projectsList')) {
        initializeProjects();
        setupImagePickerIntegration();
        //console.log('=== PROJECT SECTION INITIALIZED ===');
    }
});

// Debug function
// window.debugProjects = function() {
//     console.log('=== PROJECT DEBUG INFO ===');
//     console.log('Projects array:', projects);
//     console.log('Current editing project:', currentEditingProject);
//     console.log('Project images:', projectImages);
//     console.log('Textarea content:', document.getElementById('content')?.value);
//     console.log('=== END DEBUG INFO ===');
// };

// Export for external use
window.projectManager = {
    getProjects: () => projects,
    getCount: () => projects.length,
    debug: window.debugProjects,
    save: saveProject,
    add: addNewProject,
    edit: editProject,
    delete: deleteProject
};

//console.log('🚀 Fixed Project Management Script Loaded Successfully!');
</script>

<?php elseif ($section['section_type'] === 'banner'): ?>
<div class="space-y-6">
    <!-- Banner Content -->
    <div>
        <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Banner Content *</label>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="banner_title" class="block text-sm font-medium text-gray-700 mb-2">Banner Title</label>
                <input type="text" id="banner_title" name="banner_title"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                       value="<?= htmlspecialchars(explode('|', $section['content'])[0] ?? '') ?>"
                       placeholder="Web Hosting Sri Lanka, WordPress, Linux VPS & .LK Domains"
                       onkeyup="updateBannerContent()" 
                       onchange="updateBannerContent()" 
                       oninput="updateBannerContent()">
            </div>
            <div>
                <label for="banner_subtitle" class="block text-sm font-medium text-gray-700 mb-2">Banner Subtitle</label>
                <input type="text" id="banner_subtitle" name="banner_subtitle"
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                       value="<?= htmlspecialchars(explode('|', $section['content'])[1] ?? '') ?>"
                       placeholder="Professional web hosting services with 24/7 support"
                       onkeyup="updateBannerContent()" 
                       onchange="updateBannerContent()" 
                       oninput="updateBannerContent()">
            </div>
        </div>
        <!-- Hidden textarea for form submission -->
        <textarea id="content" name="content" class="hidden"><?= htmlspecialchars($section['content']) ?></textarea>
    </div>
</div>

<script>
// GLOBAL ION CUBE PROTECTION - Place this at the very top of your edit-section.php file
(function() {
    'use strict';
    
    // Store original addEventListener
    var originalAddEventListener = Element.prototype.addEventListener;
    var originalDocumentAddEventListener = document.addEventListener;
    var originalWindowAddEventListener = window.addEventListener;
    
    // Safe addEventListener wrapper
    function safeAddEventListener(type, listener, options) {
        try {
            if (this && typeof this === 'object' && this.nodeType) {
                // Element addEventListener
                return originalAddEventListener.call(this, type, listener, options);
            } else if (this === document) {
                // Document addEventListener
                return originalDocumentAddEventListener.call(this, type, listener, options);
            } else if (this === window) {
                // Window addEventListener
                return originalWindowAddEventListener.call(this, type, listener, options);
            }
        } catch (error) {
            console && console.warn && console.warn('addEventListener failed:', error);
            return false;
        }
    }
    
    // Override addEventListener globally
    Element.prototype.addEventListener = safeAddEventListener;
    if (document.addEventListener) {
        document.addEventListener = safeAddEventListener;
    }
    if (window.addEventListener) {
        window.addEventListener = safeAddEventListener;
    }
    
    // Safe getElementById wrapper
    var originalGetElementById = document.getElementById;
    document.getElementById = function(id) {
        try {
            return originalGetElementById.call(this, id);
        } catch (error) {
            console && console.warn && console.warn('getElementById failed for:', id);
            return null;
        }
    };
    
    // Safe querySelector wrapper
    var originalQuerySelector = document.querySelector;
    document.querySelector = function(selector) {
        try {
            return originalQuerySelector.call(this, selector);
        } catch (error) {
            console && console.warn && console.warn('querySelector failed for:', selector);
            return null;
        }
    };
    
    // Safe querySelectorAll wrapper
    var originalQuerySelectorAll = document.querySelectorAll;
    document.querySelectorAll = function(selector) {
        try {
            return originalQuerySelectorAll.call(this, selector);
        } catch (error) {
            console && console.warn && console.warn('querySelectorAll failed for:', selector);
            return [];
        }
    };
})();

// Ion Cube safe variables
var bannerInitialized = false;
var bannerRetryCount = 0;

// Update banner content function - Ion Cube safe
function updateBannerContent() {
    try {
        var titleElement = document.getElementById('banner_title');
        var subtitleElement = document.getElementById('banner_subtitle');
        var contentElement = document.getElementById('content');
        
        if (!titleElement || !subtitleElement || !contentElement) {
            return;
        }
        
        var title = titleElement.value || '';
        var subtitle = subtitleElement.value || '';
        var content = title + (subtitle ? '|' + subtitle : '');
        
        contentElement.value = content;
        
        // Trigger any other content updates safely
        triggerContentUpdate();
    } catch (error) {
        // Silent error handling for Ion Cube compatibility
        console && console.error && console.error('Error updating banner content:', error);
    }
}

// Safe content update trigger for other sections
function triggerContentUpdate() {
    try {
        // Safely trigger button preview update if it exists
        var contentElement = document.getElementById('content');
        var buttonsPreview = document.querySelector('#buttons-preview-container');
        
        if (contentElement && buttonsPreview) {
            var preview = buttonsPreview.previousElementSibling;
            if (preview) {
                var previewText = preview.querySelector('p');
                if (previewText) {
                    previewText.textContent = contentElement.value || 'Take action today and transform your business...';
                }
            }
        }
    } catch (error) {
        // Silent error - don't break the main functionality
    }
}

// Initialize banner editor - Ion Cube safe
function initializeBannerEditor() {
    if (bannerInitialized) {
        return;
    }
    
    bannerRetryCount++;
    
    try {
        var titleElement = document.getElementById('banner_title');
        var subtitleElement = document.getElementById('banner_subtitle');
        var contentElement = document.getElementById('content');
        
        if (!titleElement || !subtitleElement || !contentElement) {
            if (bannerRetryCount < 50) {
                setTimeout(initializeBannerEditor, 100);
            }
            return;
        }
        
        // Set initial values if content exists
        var content = contentElement.value;
        if (content) {
            var parts = content.split('|');
            if (parts[0]) titleElement.value = parts[0];
            if (parts[1]) subtitleElement.value = parts[1];
        }
        
        bannerInitialized = true;
        console && console.log && console.log('Banner editor initialized successfully');
    } catch (error) {
        console && console.error && console.error('Error initializing banner editor:', error);
        if (bannerRetryCount < 50) {
            setTimeout(initializeBannerEditor, 200);
        }
    }
}

// Ion Cube safe initialization - NO addEventListener
(function() {
    // Method 1: Immediate execution
    initializeBannerEditor();
    
    // Method 2: Check document ready state
    function checkReady() {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            initializeBannerEditor();
        } else {
            setTimeout(checkReady, 50);
        }
    }
    checkReady();
    
    // Method 3: Multiple timed attempts
    setTimeout(initializeBannerEditor, 100);
    setTimeout(initializeBannerEditor, 300);
    setTimeout(initializeBannerEditor, 500);
    setTimeout(initializeBannerEditor, 1000);
    setTimeout(initializeBannerEditor, 2000);
    setTimeout(initializeBannerEditor, 3000);
    
    // Method 4: Old school event handling (IE compatible)
    if (document.attachEvent) {
        document.attachEvent('onreadystatechange', function() {
            if (document.readyState === 'complete') {
                initializeBannerEditor();
            }
        });
    }
})();

// Make functions globally available
window.updateBannerContent = updateBannerContent;
window.initializeBannerEditor = initializeBannerEditor;
window.triggerContentUpdate = triggerContentUpdate;
</script>

<style>
.transition {
    transition: all 0.2s ease;
}
</style>

<?php elseif ($section['section_type'] === 'textwithimage'): ?>
    <div class="space-y-6">
        <!-- Enhanced Text with Image Management -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-4">
                <i class="fas fa-image mr-2"></i>Text with Image Content Management
            </label>
            
            <!-- Visual Preview Section -->
            <div id="textwithimage-preview" class="mb-6 p-6 border-2 border-gray-200 rounded-lg bg-gradient-to-br from-gray-50 to-blue-50">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-eye mr-2 text-blue-600"></i>Preview Your Content Sections
                    </h3>
                    <div class="text-sm text-gray-600">
                        <span id="textwithimage-count">0</span> sections
                    </div>
                </div>
                <div id="textwithimage-display" class="space-y-6">
                    <!-- Text with image sections will be displayed here -->
                </div>
            </div>

            <!-- Text with Image Management Interface -->
            <div class="bg-white border-2 border-gray-300 rounded-lg p-6 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New Content Section
                    </h4>
                </div>
                
                <!-- Content Form -->
                <div class="space-y-4">
                    <!-- Content Type and Title -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-tag mr-1"></i>Content Type *
                            </label>
                            <input type="text" id="new-content-type" placeholder="e.g., Feature, Service, Product, Testimonial..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-heading mr-1"></i>Content Title *
                            </label>
                            <input type="text" id="new-content-title" placeholder="Enter section title..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                    </div>

                    <!-- Text Content -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-align-left mr-1"></i>Text Content *
                        </label>
                        <textarea id="new-content-text" rows="4" placeholder="Enter your content text here..." 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
                    </div>

                    <!-- Image and Position -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-image mr-1"></i>Image
                            </label>
                            <div class="flex gap-2 mb-2">
                                <input type="text" id="new-content-image" placeholder="https://example.com/image.jpg" 
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <button type="button" onclick="openImageUploadForTextWithImage()" 
                                        class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 transition duration-200 text-sm">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-arrows-alt mr-1"></i>Image Position
                            </label>
                            <select id="new-content-position" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="left">📍 Left Side</option>
                                <option value="right">📍 Right Side</option>
                            </select>
                        </div>
                    </div>

                    <!-- Alt Text and Button -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-tag mr-1"></i>Alt Text
                            </label>
                            <input type="text" id="new-content-alt" placeholder="Descriptive text for image" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-mouse-pointer mr-1"></i>Button Text (Optional)
                            </label>
                            <input type="text" id="new-content-button-text" placeholder="e.g., Learn More, Get Started" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                        </div>
                    </div>

                    <!-- Button URL -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-link mr-1"></i>Button URL (Optional)
                        </label>
                        <input type="url" id="new-content-button-url" placeholder="https://example.com" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4 border-t border-gray-200">
                        <button type="button" onclick="addNewTextWithImage()" 
                                class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition duration-200 text-sm font-medium">
                            <i class="fas fa-plus mr-2"></i>➕ Add Section
                        </button>
                        <button type="button" onclick="cancelTextWithImageEdit()" style="display: none;"
                                class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition duration-200 text-sm font-medium">
                            <i class="fas fa-times mr-2"></i>Cancel Edit
                        </button>
                        <button type="button" onclick="clearAllTextWithImage()" 
                                class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition duration-200 text-sm font-medium">
                            <i class="fas fa-trash mr-2"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Hidden textarea for form submission -->
            <textarea id="content" name="content" rows="4" required
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm font-mono hidden"
                      placeholder="JSON data will appear here automatically..."><?= htmlspecialchars($section['content']) ?></textarea>
            
            <div class="flex justify-between items-center mt-2">
                <p class="text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>Format: JSON Array of Objects
                </p>
                <div class="text-sm text-gray-600">
                    <span id="textwithimage-textarea-length">0</span> characters
                </div>
            </div>
        </div>
    </div>

    <script>
let textWithImageData = [];
let editingTextWithImageIndex = -1;
let currentTextWithImageTarget = null;
let isUploadInProgress = false;

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('textwithimage-preview')) {
        loadExistingTextWithImage();
        updateTextWithImagePreview();
    }
});

// Load existing data from JSON
function loadExistingTextWithImage() {
    const contentTextarea = document.getElementById('content');
    if (!contentTextarea) return;
    
    const content = contentTextarea.value.trim();
    textWithImageData = [];
    
    if (content) {
        try {
            const parsedData = JSON.parse(content);
            if (Array.isArray(parsedData)) {
                textWithImageData = parsedData;
            }
        } catch (error) {
            textWithImageData = [];
        }
    }
    
    updateTextWithImageCount();
}

// Update count
function updateTextWithImageCount() {
    const countElement = document.getElementById('textwithimage-count');
    if (countElement) {
        countElement.textContent = textWithImageData.length;
    }
    
    const textareaLengthElement = document.getElementById('textwithimage-textarea-length');
    if (textareaLengthElement) {
        const content = document.getElementById('content').value;
        textareaLengthElement.textContent = content.length;
    }
}

// Update preview display
function updateTextWithImagePreview() {
    const container = document.getElementById('textwithimage-display');
    if (!container) return;
    
    if (textWithImageData.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-image text-4xl"></i>
                </div>
                <p class="text-gray-500 text-lg mb-2">No content sections yet</p>
                <p class="text-gray-400 text-sm">Add your first text with image section using the form below</p>
            </div>
        `;
        return;
    }

    container.innerHTML = textWithImageData.map((section, index) => {
        const isEditing = editingTextWithImageIndex === index;
        const editingClass = isEditing ? 'ring-2 ring-orange-400' : '';
        const isImageLeft = section.position === 'left';
        
        // Convert relative path to full URL for display
        let displayImageUrl = section.image;
        if (displayImageUrl && !displayImageUrl.startsWith('http') && !displayImageUrl.startsWith('data:')) {
            displayImageUrl = '<?= BASE_URL . '/'  ?>' + displayImageUrl;
        }
        
        const buttonHtml = section.buttonText ? `
            <div class="mt-4">
                <a href="${section.buttonUrl || '#'}" 
                   class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors duration-200 font-medium">
                    ${section.buttonText}
                </a>
            </div>
        ` : '';
        
        return `
            <div class="bg-white rounded-lg shadow-md ${editingClass} p-6" data-section-index="${index}">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-center">
                    <!-- Text Content -->
                    <div class="${isImageLeft ? 'lg:order-2' : 'lg:order-1'}">
                        ${section.contentType ? `<span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full mb-3">${section.contentType}</span>` : ''}
                        ${section.title ? `<h3 class="text-xl font-bold text-gray-900 mb-3">${section.title}</h3>` : ''}
                        <p class="text-gray-700 leading-relaxed">${section.text || ''}</p>
                        ${buttonHtml}
                    </div>
                    
                    <!-- Image -->
                    <div class="${isImageLeft ? 'lg:order-1' : 'lg:order-2'}">
                        ${section.image ? `
                            <img src="${displayImageUrl}" alt="${section.alt || 'Content image'}" 
                                 class="w-full h-64 object-cover rounded-lg">
                        ` : `
                            <div class="w-full h-64 bg-gray-300 rounded-lg flex items-center justify-center">
                                <i class="fas fa-image text-gray-500 text-4xl"></i>
                                <span class="ml-2 text-gray-500">No image</span>
                            </div>
                        `}
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-2 mt-4 pt-4 border-t">
                    <button onclick="editTextWithImage(${index})" 
                            class="flex-1 bg-yellow-500 text-white py-2 px-3 rounded hover:bg-yellow-600 text-xs">
                        <i class="fas fa-edit mr-1"></i> Edit
                    </button>
                    <button onclick="duplicateTextWithImage(${index})" 
                            class="flex-1 bg-green-500 text-white py-2 px-3 rounded hover:bg-green-600 text-xs">
                        <i class="fas fa-copy mr-1"></i> Copy
                    </button>
                    <button onclick="removeTextWithImage(${index})" 
                            class="bg-red-500 text-white py-2 px-3 rounded hover:bg-red-600 text-xs">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');
    
    updateTextWithImageCount();
}

// Edit function
function editTextWithImage(index) {
    if (!textWithImageData[index]) return;
    
    if (isUploadInProgress) return;
    
    const section = textWithImageData[index];
    editingTextWithImageIndex = index;
    
    // Populate form
    document.getElementById('new-content-type').value = section.contentType || '';
    document.getElementById('new-content-title').value = section.title || '';
    document.getElementById('new-content-text').value = section.text || '';
    document.getElementById('new-content-image').value = section.image || '';
    document.getElementById('new-content-position').value = section.position || 'left';
    document.getElementById('new-content-alt').value = section.alt || '';
    document.getElementById('new-content-button-text').value = section.buttonText || '';
    document.getElementById('new-content-button-url').value = section.buttonUrl || '';
    
    // Update UI
    const addButton = document.querySelector('button[onclick="addNewTextWithImage()"]');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-save mr-2"></i>Update Section';
        addButton.className = addButton.className.replace('bg-blue-500', 'bg-green-500');
    }
    
    const cancelButton = document.querySelector('button[onclick="cancelTextWithImageEdit()"]');
    if (cancelButton) {
        cancelButton.style.display = 'inline-block';
    }
    
    updateTextWithImagePreview();
}

// Image path processing
function processImagePath(image) {
    if (!image) return '';
    
    if (image.startsWith('data:')) {
        return image;
    }
    
    const baseUrl = '<?= BASE_URL ?>';
    if (image.startsWith('http')) {
        const url = new URL(image);
        let pathname = url.pathname;
        
        if (baseUrl) {
            const baseUrlObj = new URL(baseUrl);
            if (pathname.startsWith(baseUrlObj.pathname)) {
                pathname = pathname.substring(baseUrlObj.pathname.length);
            }
        }
        
        return pathname.replace(/^\/+/, '');
    }
    
    if (baseUrl && image.startsWith(baseUrl)) {
        image = image.replace(baseUrl + '/', '');
    }
    
    image = image.replace(/^https?:\/\/[^\/]+\/?/, '');
    image = image.replace(/^\/+/, '');
    image = image.replace(/^websitecmsnewshow[^\/]*\//, '');
    
    return image;
}

// Add or update section
function addNewTextWithImage() {
    const contentType = document.getElementById('new-content-type').value.trim();
    const title = document.getElementById('new-content-title').value.trim();
    const text = document.getElementById('new-content-text').value.trim();
    let image = document.getElementById('new-content-image').value.trim();
    const position = document.getElementById('new-content-position').value;
    const alt = document.getElementById('new-content-alt').value.trim();
    const buttonText = document.getElementById('new-content-button-text').value.trim();
    const buttonUrl = document.getElementById('new-content-button-url').value.trim();
    
    if (!contentType && !title && !text && !image) {
        alert('Please enter at least content type, title, text, or image');
        return;
    }

    image = processImagePath(image);

    const sectionData = {
        contentType: contentType,
        title: title,
        text: text,
        image: image,
        position: position,
        alt: alt,
        buttonText: buttonText,
        buttonUrl: buttonUrl
    };

    if (editingTextWithImageIndex >= 0) {
        textWithImageData[editingTextWithImageIndex] = sectionData;
    } else {
        textWithImageData.push(sectionData);
    }
    
    clearTextWithImageForm();
    saveToTextarea();
    updateTextWithImagePreview();
}

// Clear form
function clearTextWithImageForm() {
    document.getElementById('new-content-type').value = '';
    document.getElementById('new-content-title').value = '';
    document.getElementById('new-content-text').value = '';
    document.getElementById('new-content-image').value = '';
    document.getElementById('new-content-position').value = 'left';
    document.getElementById('new-content-alt').value = '';
    document.getElementById('new-content-button-text').value = '';
    document.getElementById('new-content-button-url').value = '';
    
    editingTextWithImageIndex = -1;
    
    const addButton = document.querySelector('button[onclick="addNewTextWithImage()"]');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-plus mr-2"></i>➕ Add Section';
        addButton.className = addButton.className.replace('bg-green-500', 'bg-blue-500');
    }
    
    const cancelButton = document.querySelector('button[onclick="cancelTextWithImageEdit()"]');
    if (cancelButton) {
        cancelButton.style.display = 'none';
    }
}

// Cancel edit
function cancelTextWithImageEdit() {
    clearTextWithImageForm();
    updateTextWithImagePreview();
}

// Duplicate section
function duplicateTextWithImage(index) {
    const section = { ...textWithImageData[index] };
    textWithImageData.splice(index + 1, 0, section);
    saveToTextarea();
    updateTextWithImagePreview();
}

// Remove section
function removeTextWithImage(index) {
    if (confirm('Remove this section?')) {
        textWithImageData.splice(index, 1);
        if (editingTextWithImageIndex === index) {
            clearTextWithImageForm();
        } else if (editingTextWithImageIndex > index) {
            editingTextWithImageIndex--;
        }
        saveToTextarea();
        updateTextWithImagePreview();
    }
}

// Clear all sections
function clearAllTextWithImage() {
    if (confirm('Remove all sections?')) {
        textWithImageData = [];
        clearTextWithImageForm();
        saveToTextarea();
        updateTextWithImagePreview();
    }
}

// Save to textarea as JSON
function saveToTextarea() {
    const contentTextarea = document.getElementById('content');
    if (!contentTextarea) return;
    
    const jsonString = JSON.stringify(textWithImageData);
    contentTextarea.value = jsonString;
    updateTextWithImageCount();
}

// Image upload function
function openImageUploadForTextWithImage() {
    currentTextWithImageTarget = 'form';
    isUploadInProgress = true;
    
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (!file) {
            isUploadInProgress = false;
            return;
        }
        
        const imageInput = document.getElementById('new-content-image');
        const originalValue = imageInput.value;
        imageInput.value = 'Uploading...';
        imageInput.disabled = true;
        
        const formData = new FormData();
        formData.append('image', file);
        
        fetch('upload-image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(responseText => {
            try {
                const data = JSON.parse(responseText);
                
                if (data.success && data.url) {
                    let processedUrl = processImagePath(data.url);
                    
                    imageInput.value = processedUrl;
                    imageInput.disabled = false;
                    
                    if (editingTextWithImageIndex >= 0) {
                        if (textWithImageData[editingTextWithImageIndex]) {
                            textWithImageData[editingTextWithImageIndex].image = processedUrl;
                            saveToTextarea();
                        }
                    }
                    
                    updateTextWithImagePreview();
                    
                    // Ensure form field stays set
                    let checkCount = 0;
                    const checkInterval = setInterval(() => {
                        checkCount++;
                        const currentValue = document.getElementById('new-content-image').value;
                        
                        if (currentValue !== processedUrl) {
                            document.getElementById('new-content-image').value = processedUrl;
                        }
                        
                        if (checkCount >= 5) {
                            clearInterval(checkInterval);
                            isUploadInProgress = false;
                        }
                    }, 100);
                    
                    alert('Image uploaded successfully!');
                } else {
                    throw new Error(data.error || 'Upload failed');
                }
            } catch (parseError) {
                throw new Error('Invalid response');
            }
        })
        .catch(error => {
            imageInput.value = originalValue;
            imageInput.disabled = false;
            isUploadInProgress = false;
            alert('Upload failed: ' + error.message);
        });
    };
    input.click();
}

// selectImage handler
//const originalSelectImage = window.selectImage;
window.selectImage = function(url) {
    if (currentTextWithImageTarget === 'form') {
        isUploadInProgress = true;
        
        const processedUrl = processImagePath(url);
        
        const imageInput = document.getElementById('new-content-image');
        if (imageInput) {
            imageInput.value = processedUrl;
            
            if (editingTextWithImageIndex >= 0) {
                if (textWithImageData[editingTextWithImageIndex]) {
                    textWithImageData[editingTextWithImageIndex].image = processedUrl;
                    saveToTextarea();
                }
            }
            
            updateTextWithImagePreview();
            
            setTimeout(() => {
                isUploadInProgress = false;
            }, 200);
        }
        
        currentTextWithImageTarget = null;
        
        if (typeof closeImagePicker === 'function') {
            closeImagePicker();
        }
        
        return;
    }
    
    if (originalSelectImage) {
        originalSelectImage(url);
    }
};

// Monitor textarea changes
document.addEventListener('DOMContentLoaded', function() {
    const contentTextarea = document.getElementById('content');
    if (contentTextarea && document.getElementById('textwithimage-preview')) {
        contentTextarea.addEventListener('input', function() {
            loadExistingTextWithImage();
            updateTextWithImagePreview();
        });
    }
});
    </script>
                        
<?php elseif ($section['section_type'] === 'custom'): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Custom HTML Content</label>
                            
                            <!-- Editor Controls -->
                            <div class="bg-gray-100 border border-gray-300 rounded-t-md p-3 flex flex-wrap items-center gap-2">
                                <button type="button" onclick="togglePreview()" id="preview-btn" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700 transition">
                                    <i class="fas fa-eye mr-1"></i>Preview
                                </button>
                                <button type="button" onclick="formatCode()" class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700 transition">
                                    <i class="fas fa-code mr-1"></i>Format
                                </button>
                                <button type="button" onclick="insertTemplate('basic')" class="bg-purple-600 text-white px-3 py-1 rounded text-sm hover:bg-purple-700 transition">
                                    <i class="fas fa-file-code mr-1"></i>Basic Template
                                </button>
                                <button type="button" onclick="insertTemplate('card')" class="bg-indigo-600 text-white px-3 py-1 rounded text-sm hover:bg-indigo-700 transition">
                                    <i class="fas fa-id-card mr-1"></i>Card Template
                                </button>
                                <button type="button" onclick="insertTemplate('gallery')" class="bg-pink-600 text-white px-3 py-1 rounded text-sm hover:bg-pink-700 transition">
                                    <i class="fas fa-images mr-1"></i>Gallery Template
                                </button>
                                <div class="ml-auto text-sm text-gray-600">
                                    <span id="char-count">0</span> characters
                                </div>
                            </div>
                            
                            <!-- Editor Container -->
                            <div class="relative">
                                <!-- Code Editor -->
                                <div id="editor-container" class="relative">
                                    <textarea id="content" name="content" rows="15"
                                              class="w-full px-3 py-2 border-l border-r border-gray-300 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm resize-y"
                                              placeholder="Enter your custom HTML content here..."
                                              oninput="updateCharCount(); updatePreview();"><?= htmlspecialchars($section['content']) ?></textarea>
                                    
                                    <!-- Line numbers (optional) -->
                                    <div id="line-numbers" class="absolute left-0 top-0 bg-gray-50 text-gray-400 text-xs font-mono p-2 border-r border-gray-200 select-none" style="display: none;">
                                        <!-- Line numbers will be generated here -->
                                    </div>
                                </div>
                                
                                <!-- Preview Panel -->
                                <div id="preview-panel" class="hidden border-l border-r border-gray-300 bg-white p-4 min-h-[300px]">
                                    <div class="bg-yellow-50 border border-yellow-200 rounded p-2 mb-4 text-sm text-yellow-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Preview Mode - This shows how your HTML will appear
                                    </div>
                                    <div id="preview-content">
                                        <!-- Preview content will be rendered here -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Footer -->
                            <div class="bg-gray-100 border border-gray-300 rounded-b-md p-3">
                                <div class="flex flex-wrap items-center justify-between text-sm text-gray-600">
                                    <div class="flex items-center gap-4">
                                        <span><i class="fas fa-info-circle mr-1"></i>You can use HTML, CSS, and basic JavaScript</span>
                                        <label class="flex items-center">
                                            <input type="checkbox" id="show-line-numbers" onchange="toggleLineNumbers()" class="mr-1">
                                            Show line numbers
                                        </label>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-green-600" id="save-status"></span>
                                        <button type="button" onclick="validateHTML()" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-check-circle mr-1"></i>Validate
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                        let isPreviewMode = false;
                        
                        // Templates
                        const templates = {
                            basic: `<div class="custom-section">
    <h2>Custom Section Title</h2>
    <p>This is a basic custom section. You can add any HTML content here.</p>
    <style>
        .custom-section {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 20px 0;
        }
        .custom-section h2 {
            color: #333;
            margin-bottom: 15px;
        }
    </style>
</div>`,
            
            card: `<div class="custom-card">
    <div class="card-header">
        <h3>Card Title</h3>
    </div>
    <div class="card-body">
        <p>Card content goes here. This is a flexible card layout.</p>
        <button class="card-btn">Learn More</button>
    </div>
    <style>
        .custom-card {
            max-width: 400px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 20px auto;
        }
        .card-header {
            background: #007bff;
            color: white;
            padding: 15px;
        }
        .card-header h3 {
            margin: 0;
        }
        .card-body {
            padding: 20px;
        }
        .card-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .card-btn:hover {
            background: #0056b3;
        }
    </style>
</div>`,
            
            gallery: `<div class="custom-gallery">
    <h2>Image Gallery</h2>
    <div class="gallery-grid">
        <div class="gallery-item">
            <img src="https://via.placeholder.com/300x200" alt="Image 1">
            <div class="gallery-caption">Image Caption 1</div>
        </div>
        <div class="gallery-item">
            <img src="https://via.placeholder.com/300x200" alt="Image 2">
            <div class="gallery-caption">Image Caption 2</div>
        </div>
        <div class="gallery-item">
            <img src="https://via.placeholder.com/300x200" alt="Image 3">
            <div class="gallery-caption">Image Caption 3</div>
        </div>
    </div>
    <style>
        .custom-gallery {
            margin: 20px 0;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .gallery-item {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .gallery-item:hover {
            transform: translateY(-2px);
        }
        .gallery-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .gallery-caption {
            padding: 15px;
            background: white;
            text-align: center;
            font-weight: 500;
        }
    </style>
</div>`
        };
        
        // Toggle preview mode
        function togglePreview() {
            const editor = document.getElementById('editor-container');
            const preview = document.getElementById('preview-panel');
            const btn = document.getElementById('preview-btn');
            
            isPreviewMode = !isPreviewMode;
            
            if (isPreviewMode) {
                editor.classList.add('hidden');
                preview.classList.remove('hidden');
                btn.innerHTML = '<i class="fas fa-code mr-1"></i>Edit';
                btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                btn.classList.add('bg-gray-600', 'hover:bg-gray-700');
                updatePreview();
            } else {
                editor.classList.remove('hidden');
                preview.classList.add('hidden');
                btn.innerHTML = '<i class="fas fa-eye mr-1"></i>Preview';
                btn.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                btn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }
        }
        
        // Update preview content
        function updatePreview() {
            if (isPreviewMode) {
                const content = document.getElementById('content').value;
                const previewContent = document.getElementById('preview-content');
                previewContent.innerHTML = content;
            }
        }
        
        // Insert template
        function insertTemplate(type) {
            const textarea = document.getElementById('content');
            const template = templates[type];
            
            if (textarea.value.trim() === '') {
                textarea.value = template;
            } else {
                if (confirm('This will replace your current content. Continue?')) {
                    textarea.value = template;
                }
            }
            
            updateCharCount();
            updatePreview();
            textarea.focus();
        }
        
        // Format code (basic indentation)
        function formatCode() {
            const textarea = document.getElementById('content');
            let content = textarea.value;
            
            // Basic HTML formatting
            content = content.replace(/></g, '>\n<');
            content = content.replace(/\n\s*\n/g, '\n');
            
            // Simple indentation
            let indentLevel = 0;
            const lines = content.split('\n');
            const formatted = lines.map(line => {
                line = line.trim();
                if (line.match(/<\/\w+>/)) indentLevel--;
                const indented = '  '.repeat(Math.max(0, indentLevel)) + line;
                if (line.match(/<\w+[^>]*[^\/]>$/)) indentLevel++;
                return indented;
            });
            
            textarea.value = formatted.join('\n');
            updateCharCount();
            updatePreview();
        }
        
        // Update character count
        function updateCharCount() {
            const textarea = document.getElementById('content');
            const count = document.getElementById('char-count');
            count.textContent = textarea.value.length;
        }
        
        // Toggle line numbers
        function toggleLineNumbers() {
            const lineNumbers = document.getElementById('line-numbers');
            const checkbox = document.getElementById('show-line-numbers');
            const textarea = document.getElementById('content');
            
            if (checkbox.checked) {
                lineNumbers.style.display = 'block';
                textarea.style.paddingLeft = '50px';
                updateLineNumbers();
            } else {
                lineNumbers.style.display = 'none';
                textarea.style.paddingLeft = '12px';
            }
        }
        
        // Update line numbers
        function updateLineNumbers() {
            const textarea = document.getElementById('content');
            const lineNumbers = document.getElementById('line-numbers');
            const lines = textarea.value.split('\n').length;
            
            let numberHTML = '';
            for (let i = 1; i <= lines; i++) {
                numberHTML += i + '\n';
            }
            lineNumbers.textContent = numberHTML;
        }
        
        // Basic HTML validation
        function validateHTML() {
            const content = document.getElementById('content').value;
            const saveStatus = document.getElementById('save-status');
            
            // Basic validation
            const openTags = content.match(/<\w+[^>]*[^\/]>/g) || [];
            const closeTags = content.match(/<\/\w+>/g) || [];
            
            if (openTags.length === closeTags.length) {
                saveStatus.innerHTML = '<i class="fas fa-check mr-1"></i>HTML looks valid';
                saveStatus.className = 'text-green-600';
            } else {
                saveStatus.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>Check your HTML tags';
                saveStatus.className = 'text-orange-600';
            }
            
            setTimeout(() => {
                saveStatus.innerHTML = '';
            }, 3000);
        }
        
        // Auto-save indication
        function showSaveStatus() {
            const saveStatus = document.getElementById('save-status');
            saveStatus.innerHTML = '<i class="fas fa-check mr-1"></i>Auto-saved';
            saveStatus.className = 'text-green-600';
            
            setTimeout(() => {
                saveStatus.innerHTML = '';
            }, 2000);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCount();
            
            // Auto-save simulation (you can implement actual auto-save)
            let saveTimeout;
            document.getElementById('content').addEventListener('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(showSaveStatus, 1000);
            });
        });
        </script>

        <style>
        .custom-section, .custom-card, .custom-gallery {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        #content:focus {
            outline: none;
        }
        
        .transition {
            transition: all 0.2s ease;
        }
        
        /* Syntax highlighting for textarea (basic) */
        #content {
            line-height: 1.5;
            tab-size: 2;
        }
        </style>
                    <?php endif; ?>
                </div>

                <!-- Image/Media Section -->
<?php if (in_array($section['section_type'], ['hero', 'content','cta','banner'])): ?>
<div class="bg-white border border-gray-200 rounded-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Background/Feature Image</h3>
    <div>
        <label for="image_url" class="block text-sm font-medium text-gray-700 mb-2">Image URL</label>
        <div class="flex space-x-2">
            <input type="text" id="image_url" name="image_url"
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                   value="<?= htmlspecialchars($section['image_url'] ?? '') ?>"
                   placeholder="https://example.com/image.jpg"
                   onchange="handleImageUrlChange(this.value)">
            <button type="button" onclick="openImageUpload()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition duration-200" title="Upload new image">
                <i class="fas fa-upload"></i>
            </button>
            <?php if ($section['image_url']): ?>
            <button type="button" onclick="deleteImage()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition duration-200" title="Delete image">
                <i class="fas fa-trash"></i>
            </button>
            <?php endif; ?>
        </div>
        <p class="text-sm text-gray-500 mt-1">Optional: Add a background or feature image for this section.</p>
        
        <!-- Enhanced Image Preview with BASE_URL -->
        <?php if ($section['image_url']): ?>
            <div class="mt-4" id="image-preview-container">
                <div class="image-preview-card">
                    <!-- Image Preview with BASE_URL -->
                    <div class="flex-shrink-0">
                        <img id="current-image-preview" 
                             src="<?= BASE_URL . '/' . htmlspecialchars($section['image_url']) ?>" 
                             alt="Current image" 
                             class="w-24 h-24 object-cover rounded border"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div class="image-load-error">
                            Failed to load
                        </div>
                    </div>
                    
                    <!-- Image Details -->
                    <div class="flex-1">
                        <h5 class="font-medium text-gray-900 mb-1">Background Image</h5>
                        <p class="text-sm text-gray-600 mb-2">
                            <strong>Path:</strong> <?= htmlspecialchars($section['image_url']) ?>
                        </p>
                        <p class="text-sm text-gray-500 mb-3">
                            <strong>Full URL:</strong> <?= BASE_URL . '/' . htmlspecialchars($section['image_url']) ?>
                        </p>
                        
                        <!-- Action Buttons -->
                        <div class="flex space-x-2">
                            <button type="button" onclick="deleteImage()" class="action-btn action-btn-red">
                                <i class="fas fa-trash mr-1"></i>Delete Image
                            </button>
                            <button type="button" onclick="previewImageFullSize()" class="action-btn action-btn-blue">
                                <i class="fas fa-eye mr-1"></i>Preview Full Size
                            </button>
                            <button type="button" onclick="replaceImage()" class="action-btn action-btn-green">
                                <i class="fas fa-sync mr-1"></i>Replace Image
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="mt-4 hidden" id="image-preview-container">
                <!-- Preview container will be shown when image is added -->
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- FIXED Modal with Custom CSS (No Tailwind Dependencies) -->
<div id="fullSizeImageModal" class="image-modal-overlay" onclick="closeFullSizePreview(event)">
    <div class="image-modal-content" onclick="event.stopPropagation()">
        <img id="fullSizeImage" src="" alt="Full size preview" class="image-modal-img">
        <button onclick="closeFullSizePreview()" class="image-modal-close">
            <i class="fas fa-times"></i>
        </button>
        <div class="image-modal-info">
            <p id="fullSizeImagePath"></p>
        </div>
    </div>
</div>

<!-- Custom CSS that doesn't rely on Tailwind -->
<style>
/* ===== IMAGE PREVIEW CARD STYLES ===== */
.image-preview-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background-color: rgba(249, 250, 251, 1); /* Custom gray-50 replacement */
    border-radius: 0.5rem;
    border: 1px solid rgba(229, 231, 235, 1); /* Custom border */
}

.image-load-error {
    width: 6rem;
    height: 6rem;
    background-color: rgba(229, 231, 235, 1); /* Custom gray-200 */
    border-radius: 0.25rem;
    border: 1px solid rgba(209, 213, 219, 1);
    display: none;
    align-items: center;
    justify-content: center;
    color: rgba(107, 114, 128, 1); /* Custom gray-500 */
    font-size: 0.75rem;
    text-align: center;
}

/* ===== ACTION BUTTON STYLES ===== */
.action-btn {
    font-size: 0.875rem;
    padding: 0.25rem 0.75rem;
    border-radius: 0.25rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
}

.action-btn-red {
    background-color: rgba(254, 226, 226, 1); /* Custom red-100 */
    color: rgba(185, 28, 28, 1); /* Custom red-700 */
}

.action-btn-red:hover {
    background-color: rgba(254, 202, 202, 1); /* Custom red-200 */
}

.action-btn-blue {
    background-color: rgba(219, 234, 254, 1); /* Custom blue-100 */
    color: rgba(29, 78, 216, 1); /* Custom blue-700 */
}

.action-btn-blue:hover {
    background-color: rgba(191, 219, 254, 1); /* Custom blue-200 */
}

.action-btn-green {
    background-color: rgba(220, 252, 231, 1); /* Custom green-100 */
    color: rgba(21, 128, 61, 1); /* Custom green-700 */
}

.action-btn-green:hover {
    background-color: rgba(187, 247, 208, 1); /* Custom green-200 */
}

/* ===== IMAGE MODAL STYLES (Independent of Tailwind) ===== */
.image-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.85); /* Custom dark overlay */
    backdrop-filter: blur(2px);
    z-index: 99999;
    display: none; /* Hidden by default */
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
    transition: opacity 0.3s ease-in-out;
    cursor: pointer;
}

.image-modal-overlay.show {
    display: flex !important;
    opacity: 1;
}

.image-modal-overlay.hide {
    opacity: 0;
}

.image-modal-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: default;
}

.image-modal-img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
    background-color: white;
    cursor: default;
}

.image-modal-close {
    position: absolute;
    top: -50px;
    right: -50px;
    width: 44px;
    height: 44px;
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.2s ease;
    z-index: 100001;
}

.image-modal-close:hover {
    background-color: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.image-modal-info {
    position: absolute;
    bottom: -50px;
    left: 0;
    right: 0;
    background-color: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 12px 16px;
    border-radius: 6px;
    text-align: center;
    backdrop-filter: blur(4px);
    font-size: 14px;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .image-modal-overlay {
        padding: 10px;
    }
    
    .image-modal-close {
        top: -35px;
        right: -35px;
        width: 36px;
        height: 36px;
        font-size: 16px;
    }
    
    .image-modal-info {
        bottom: -45px;
        font-size: 12px;
        padding: 8px 12px;
    }
}

/* Smooth animations */
@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.image-modal-content {
    animation: modalFadeIn 0.25s ease-out;
}

/* Prevent body scroll when modal is open */
body.modal-open {
    overflow: hidden !important;
}
</style>

<script>
// ===== ENHANCED IMAGE MANAGEMENT WITH CUSTOM MODAL =====
(function() {
    'use strict';
    
    const BASE_URL = '<?= BASE_URL ?>';
    
    // ===== IMAGE URL HANDLING =====
    
    function handleImageUrlChange(url) {
        const trimmedUrl = url.trim();
        
        if (trimmedUrl) {
            updateImagePreview(trimmedUrl);
            switchBackgroundToImage();
            showImageNotification('✅ Image URL updated', 'success');
        } else {
            hideImagePreview();
            switchBackgroundToSolid();
            showImageNotification('🔄 Switched to solid background (no image)', 'info');
        }
    }
    
    function updateImagePreview(imagePath) {
        const container = document.getElementById('image-preview-container');
        const fullImageUrl = getFullImageUrl(imagePath);
        
        container.innerHTML = `
            <div class="image-preview-card">
                <div class="flex-shrink-0">
                    <img id="current-image-preview" 
                         src="${fullImageUrl}" 
                         alt="Current image" 
                         class="w-24 h-24 object-cover rounded border"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="image-load-error">
                        Failed to load
                    </div>
                </div>
                
                <div class="flex-1">
                    <h5 class="font-medium text-gray-900 mb-1">Background Image</h5>
                    <p class="text-sm text-gray-600 mb-2">
                        <strong>Path:</strong> ${imagePath}
                    </p>
                    <p class="text-sm text-gray-500 mb-3">
                        <strong>Full URL:</strong> ${fullImageUrl}
                    </p>
                    
                    <div class="flex space-x-2">
                        <button type="button" onclick="deleteImage()" class="action-btn action-btn-red">
                            <i class="fas fa-trash mr-1"></i>Delete Image
                        </button>
                        <button type="button" onclick="previewImageFullSize()" class="action-btn action-btn-blue">
                            <i class="fas fa-eye mr-1"></i>Preview Full Size
                        </button>
                        <button type="button" onclick="replaceImage()" class="action-btn action-btn-green">
                            <i class="fas fa-sync mr-1"></i>Replace Image
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        container.classList.remove('hidden');
    }
    
    function hideImagePreview() {
        const container = document.getElementById('image-preview-container');
        container.classList.add('hidden');
    }
    
    function getFullImageUrl(imagePath) {
        if (!imagePath) return '';
        
        if (imagePath.includes('://')) {
            return imagePath;
        }
        
        return BASE_URL + '/' + imagePath.replace(/^\/+/, '');
    }
    
    // ===== DELETE FUNCTIONALITY =====
    
    function deleteImage() {
        if (confirm('🗑️ Are you sure you want to delete this background image?\n\nThis will switch the background to solid color.')) {
            document.getElementById('image_url').value = '';
            hideImagePreview();
            switchBackgroundToSolid();
            showImageNotification('🗑️ Image deleted and switched to solid background!', 'success');
        }
    }
    
    function replaceImage() {
        if (typeof openImagePicker === 'function') {
            openImagePicker();
        } else {
            alert('Image picker not available. Please enter a new URL manually.');
        }
    }
    
    // ===== ENHANCED MODAL FUNCTIONALITY =====
    
    function previewImageFullSize() {
        const currentImg = document.getElementById('current-image-preview');
        const modal = document.getElementById('fullSizeImageModal');
        const fullSizeImg = document.getElementById('fullSizeImage');
        const pathDisplay = document.getElementById('fullSizeImagePath');
        
        if (currentImg && modal && fullSizeImg) {
            // Set image source
            fullSizeImg.src = currentImg.src;
            pathDisplay.textContent = document.getElementById('image_url').value;
            
            // Show modal with custom class
            modal.classList.add('show');
            document.body.classList.add('modal-open');
            
            console.log('Modal opened with custom overlay');
        }
    }
    
    function closeFullSizePreview(event) {
        // Prevent closing when clicking on the image or info
        if (event && event.target !== event.currentTarget) {
            return;
        }
        
        const modal = document.getElementById('fullSizeImageModal');
        
        // Add hide animation
        modal.classList.add('hide');
        
        setTimeout(() => {
            modal.classList.remove('show', 'hide');
            document.body.classList.remove('modal-open');
        }, 300);
        
        console.log('Modal closed');
    }
    
    // ===== BACKGROUND TYPE SWITCHING =====
    
    function switchBackgroundToImage() {
        const backgroundTypeField = document.getElementById('background_type');
        if (backgroundTypeField) {
            backgroundTypeField.value = 'image';
            
            if (typeof switchBackgroundType === 'function') {
                switchBackgroundType('image');
            }
            
            updateBackgroundTabs('image');
            
            if (typeof updateStylingPreview === 'function') {
                setTimeout(updateStylingPreview, 100);
            }
        }
    }
    
    function switchBackgroundToSolid() {
        const backgroundTypeField = document.getElementById('background_type');
        if (backgroundTypeField) {
            backgroundTypeField.value = 'solid';
            
            if (typeof switchBackgroundType === 'function') {
                switchBackgroundType('solid');
            }
            
            updateBackgroundTabs('solid');
            
            if (typeof updateStylingPreview === 'function') {
                setTimeout(updateStylingPreview, 100);
            }
        }
    }
    
    function updateBackgroundTabs(activeType) {
        const tabs = ['solid', 'gradient', 'image'];
        
        tabs.forEach(type => {
            const tab = document.getElementById(`tab-${type}`);
            const options = document.getElementById(`${type}-options`);
            
            if (tab) {
                if (type === activeType) {
                    tab.classList.add('active');
                } else {
                    tab.classList.remove('active');
                }
            }
            
            if (options) {
                if (type === activeType) {
                    options.classList.remove('hidden');
                } else {
                    options.classList.add('hidden');
                }
            }
        });
    }
    
    // ===== NOTIFICATION SYSTEM =====
    
    function showImageNotification(message, type = 'success') {
        const existing = document.querySelector('.image-notification');
        if (existing) existing.remove();
        
        const colors = {
            success: 'background-color: #10b981; color: white;',
            error: 'background-color: #ef4444; color: white;',
            info: 'background-color: #3b82f6; color: white;',
            warning: 'background-color: #f59e0b; color: black;'
        };
        
        const notification = document.createElement('div');
        notification.className = 'image-notification';
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 100000;
            ${colors[type] || colors.success}
            font-weight: 500;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center;">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="margin-left: 12px; background: none; border: none; color: inherit; font-size: 18px; cursor: pointer; opacity: 0.8;">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 4000);
    }
    
    // ===== KEYBOARD SUPPORT =====
    
    document.addEventListener('keydown', function(event) {
        const modal = document.getElementById('fullSizeImageModal');
        
        if (modal && modal.classList.contains('show') && event.key === 'Escape') {
            closeFullSizePreview();
        }
    });
    
    // ===== MAKE FUNCTIONS GLOBALLY AVAILABLE =====
    
    window.handleImageUrlChange = handleImageUrlChange;
    window.deleteImage = deleteImage;
    window.replaceImage = replaceImage;
    window.previewImageFullSize = previewImageFullSize;
    window.closeFullSizePreview = closeFullSizePreview;
    
    // ===== INITIALIZATION =====
    
    document.addEventListener('DOMContentLoaded', function() {
        //console.log('🖼️ Image section with custom modal initialized');
        
        const imageUrl = document.getElementById('image_url').value;
        if (imageUrl) {
            switchBackgroundToImage();
        }
    });
    
})();
</script>
<?php endif; ?>
                
                <!-- Form Actions -->
                <div class="flex justify-between pt-6 border-t border-gray-200">
                    <a href="edit-page.php?id=<?= $section['page_id'] ?>" 
                       class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 transition duration-200">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <div class="space-x-3">
                        <a href="delete-section.php?id=<?= $section['id'] ?>" 
                           class="bg-red-600 text-white px-6 py-2 rounded hover:bg-red-700 transition duration-200"
                           onclick="return confirm('Are you sure you want to delete this section?')">
                            <i class="fas fa-trash mr-2"></i>Delete
                        </a>
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition duration-200">
                            <i class="fas fa-save mr-2"></i>Update Section
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Image Picker Modal -->
        <div id="imagePickerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[80vh] overflow-hidden">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Choose Image</h3>
                    <button onclick="closeImagePicker()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-96">
                    <div id="imageGrid" class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <!-- Images will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Modal -->
        <div id="uploadModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Upload Image</h3>
                </div>
                <div class="p-6">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Image</label>
                            <input type="file" id="imageInput" name="image" accept="image/*" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-sm text-gray-500 mt-1">Supported formats: JPG, PNG, GIF, WebP, SVG (max 5MB)</p>
                        </div>
                        
                        <div id="imagePreview" class="hidden mb-4">
                            <img id="previewImg" class="w-full h-48 object-cover rounded-lg border">
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeImageUpload()" 
                                    class="px-4 py-2 text-gray-700 bg-gray-300 rounded hover:bg-gray-400 transition duration-200">
                                Cancel
                            </button>
                            <button type="submit" id="uploadBtn"
                                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-200">
                                <i class="fas fa-upload mr-2"></i>Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<style>
    /* ===== CUSTOM BACKGROUND OPACITY CSS (No Tailwind Dependencies) ===== */

/* Form Action Buttons */
.bg-gray-300 {
    background-color: rgba(209, 213, 219, 1) !important; /* Custom bg-gray-300 */
}

.hover\:bg-gray-400:hover {
    background-color: rgba(156, 163, 175, 1) !important; /* Custom hover:bg-gray-400 */
}

.bg-red-600 {
    background-color: rgba(220, 38, 38, 1) !important; /* Custom bg-red-600 */
}

.hover\:bg-red-700:hover {
    background-color: rgba(185, 28, 28, 1) !important; /* Custom hover:bg-red-700 */
}

.bg-blue-600 {
    background-color: rgba(37, 99, 235, 1) !important; /* Custom bg-blue-600 */
}

.hover\:bg-blue-700:hover {
    background-color: rgba(29, 78, 216, 1) !important; /* Custom hover:bg-blue-700 */
}

/* Modal Overlays with Custom Opacity */
#imagePickerModal.fixed.inset-0.bg-black {
    background-color: rgba(0, 0, 0, 0.5) !important; /* Custom bg-black bg-opacity-50 */
}

#uploadModal.fixed.inset-0.bg-black {
    background-color: rgba(0, 0, 0, 0.5) !important; /* Custom bg-black bg-opacity-50 */
}

/* Alternative approach - target by class combination */
.fixed.inset-0.bg-black.bg-opacity-50 {
    background-color: rgba(0, 0, 0, 0.5) !important;
}

/* Modal Content Backgrounds */
.bg-white {
    background-color: rgba(255, 255, 255, 1) !important;
}

/* Text Colors (ensuring compatibility) */
.text-gray-700 {
    color: rgba(55, 65, 81, 1) !important;
}

.text-white {
    color: rgba(255, 255, 255, 1) !important;
}

.text-gray-900 {
    color: rgba(17, 24, 39, 1) !important;
}

.text-gray-400 {
    color: rgba(156, 163, 175, 1) !important;
}

.hover\:text-gray-600:hover {
    color: rgba(75, 85, 99, 1) !important;
}

/* Border Colors */
.border-gray-200 {
    border-color: rgba(229, 231, 235, 1) !important;
}

.border-gray-300 {
    border-color: rgba(209, 213, 219, 1) !important;
}

/* Focus States */
.focus\:ring-blue-500:focus {
    --tw-ring-color: rgba(59, 130, 246, 1) !important;
}

.focus\:border-blue-500:focus {
    border-color: rgba(59, 130, 246, 1) !important;
}

/* Additional utility classes for consistency */
.text-gray-500 {
    color: rgba(107, 114, 128, 1) !important;
}

/* Transition classes remain as-is since they don't use opacity */
.transition {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

.duration-200 {
    transition-duration: 200ms;
}

/* Custom classes for better maintainability */
.custom-modal-overlay {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(2px);
}

.custom-btn-cancel {
    background-color: rgba(209, 213, 219, 1);
    color: rgba(55, 65, 81, 1);
}

.custom-btn-cancel:hover {
    background-color: rgba(156, 163, 175, 1);
}

.custom-btn-danger {
    background-color: rgba(220, 38, 38, 1);
    color: rgba(255, 255, 255, 1);
}

.custom-btn-danger:hover {
    background-color: rgba(185, 28, 28, 1);
}

.custom-btn-primary {
    background-color: rgba(37, 99, 235, 1);
    color: rgba(255, 255, 255, 1);
}

.custom-btn-primary:hover {
    background-color: rgba(29, 78, 216, 1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .custom-modal-overlay {
        padding: 1rem;
    }
    
    .custom-btn-cancel,
    .custom-btn-danger,
    .custom-btn-primary {
        width: 100%;
        justify-content: center;
        margin-bottom: 0.5rem;
    }
}
</style>
    <script>

    // Helper function to remove base URL from image path
    function removeBaseUrl(imageUrl) {
        if (!imageUrl) return '';
        
        // If the URL starts with the base URL, remove it
        if (imageUrl.startsWith(BASE_URL)) {
            let relativePath = imageUrl.substring(BASE_URL.length);
            // Remove leading slash if present
            if (relativePath.startsWith('/')) {
                relativePath = relativePath.substring(1);
            }
            return relativePath;
        }
        
        // If it's already a relative path, remove leading slash
        if (imageUrl.startsWith('/')) {
            return imageUrl.substring(1);
        }
        
        // If it's already clean or different domain, keep as is
        return imageUrl;
    }

    // Helper function to add base URL to image path for display
    function addBaseUrl(imagePath) {
        if (!imagePath) return '';
        
        // If it's already a full URL (starts with http/https), return as is
        if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
            return imagePath;
        }
        
        // Remove trailing slash from BASE_URL if present
        const baseUrl = BASE_URL.endsWith('/') ? BASE_URL.slice(0, -1) : BASE_URL;
        
        // If it's a relative path, add base URL with slash
        return baseUrl + "/" + imagePath;
    }

        // Global variables
        let currentSlideIndex = -1;
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('galleryManager')) {
                initGalleryManager();
            }
            if (document.getElementById('testimonialsManager')) {
                initTestimonialsManager();
            }
            if (document.getElementById('sliderManager')) {
                initSliderManager();
            }
            
            // Sync color pickers
            document.getElementById('background_color').addEventListener('input', function() {
                document.getElementById('bg_text').value = this.value;
            });
            document.getElementById('text_color').addEventListener('input', function() {
                document.getElementById('text_text').value = this.value;
            });
        });
        
        // Enhanced Slider Management with descriptions
        let sliderImages = [];
        
        function initSliderManager() {
            
            const content = document.getElementById('content').value;
            if (content) {
                // Parse content that includes descriptions separated by semicolons
                // Format: imageUrl;title;description;buttonText;buttonUrl||imageUrl2;title2;description2;buttonText2;buttonUrl2
                sliderImages = content.split('||').map(slide => {
                    const parts = slide.split(';');
                    return {
                        url: parts[0] || '',
                        title: parts[1] || '',
                        description: parts[2] || '',
                        buttonText: parts[3] || '',
                        buttonUrl: parts[4] || ''
                    };
                }).filter(slide => slide.url.trim());
            } else {
                sliderImages = [];
            }
            renderSliderItems();
        }
        
        function renderSliderItems() {
            const container = document.getElementById('sliderItems');
            container.innerHTML = '';
            
            if (sliderImages.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-500 py-8">No slides added yet. Click "Add Slide" to get started.</p>';
                return;
            }
            
            sliderImages.forEach((slide, index) => {
                const item = document.createElement('div');
                item.className = 'p-6 bg-white border border-gray-200 rounded-lg';

                // Add base URL for image display
                const displayImageUrl = addBaseUrl(slide.url);
                // Show full URL in input field for editing
                const editImageUrl = slide.url;

                item.innerHTML = `
                    <div class="flex items-start space-x-4 mb-4">
                        <div class="flex-shrink-0">
                            <img src="${displayImageUrl}" alt="Slider image" class="w-24 h-24 object-cover rounded border" 
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iOTYiIGhlaWdodD0iOTYiIHZpZXdCb3g9IjAgMCA5NiA5NiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9Ijk2IiBoZWlnaHQ9Ijk2IiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik00OCA1NEw1NCA0OEw2MCA1NEw1NCA2MEw0OCA1NFoiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
                        </div>
                        <div class="flex-1 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Image URL *</label>
                                <div class="flex space-x-2">
                                    <input type="text" value="${editImageUrl}" 
                                           onchange="updateSlideField(${index}, 'url', this.value)"
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="https://example.com/image.jpg" required>
                
                                    <button type="button" onclick="openImageUploadForSlide(${index})" 
                                            class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 transition duration-200">
                                        <i class="fas fa-upload"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Slide Title</label>
                                    <input type="text" value="${slide.title}" 
                                           onchange="updateSlideField(${index}, 'title', this.value)"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Slide title (optional)">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Button Text</label>
                                    <input type="text" value="${slide.buttonText}" 
                                           onchange="updateSlideField(${index}, 'buttonText', this.value)"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="Learn More">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Slide Description</label>
                                    <textarea onchange="updateSlideField(${index}, 'description', this.value)" 
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                              rows="3" placeholder="Slide description (optional)">${slide.description}</textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Button URL</label>
                                    <input type="url" value="${slide.buttonUrl}" 
                                           onchange="updateSlideField(${index}, 'buttonUrl', this.value)"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="https://example.com">
                                </div>
                            </div>
                        </div>
                        <div class="flex-shrink-0 flex flex-col space-y-2">
                            <button type="button" onclick="moveSliderImage(${index}, -1)" 
                                    class="p-2 text-gray-500 hover:text-gray-700 border rounded" 
                                    ${index === 0 ? 'disabled' : ''} title="Move up">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button type="button" onclick="moveSliderImage(${index}, 1)" 
                                    class="p-2 text-gray-500 hover:text-gray-700 border rounded" 
                                    ${index === sliderImages.length - 1 ? 'disabled' : ''} title="Move down">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button type="button" onclick="duplicateSlide(${index})" 
                                    class="p-2 text-blue-500 hover:text-blue-700 border rounded" 
                                    title="Duplicate slide">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button type="button" onclick="removeSliderImage(${index})" 
                                    class="p-2 text-red-500 hover:text-red-700 border rounded" 
                                    title="Remove slide">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Slide Preview -->
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <div class="text-xs font-medium text-gray-500 mb-2">Preview:</div>
                        <div class="text-sm">
                            <div class="font-medium text-gray-900">${slide.title || 'No title'}</div>
                            <div class="text-gray-600 mt-1">${slide.description || 'No description'}</div>
                            ${slide.buttonText ? `<div class="mt-2"><span class="inline-block bg-blue-600 text-white px-3 py-1 rounded text-xs">${slide.buttonText}</span></div>` : ''}
                        </div>
                    </div>
                `;
                container.appendChild(item);
            });
            
            updateSliderContent();
        }
        
        function addSliderImage() {
            // Add a new slide with empty fields
            sliderImages.push({
                url: '',
                title: '',
                description: '',
                buttonText: '',
                buttonUrl: ''
            });
            renderSliderItems();
        }
        
        function updateSlideField(index, field, value) {
            sliderImages[index][field] = value.trim();
            updateSliderContent();
            
            // Update image preview if URL changed
            if (field === 'url') {
                const img = document.querySelector(`#sliderItems .p-6:nth-child(${index + 1}) img`);
                if (img) img.src = value;
            }
            
            // Update preview if title, description, or button text changed
            if (['title', 'description', 'buttonText'].includes(field)) {
                renderSliderItems();
            }
        }
        
        function removeSliderImage(index) {
            if (confirm('Remove this slide from the slider?')) {
                sliderImages.splice(index, 1);
                renderSliderItems();
            }
        }
        
        function duplicateSlide(index) {
            const slide = { ...sliderImages[index] };
            sliderImages.splice(index + 1, 0, slide);
            renderSliderItems();
        }
        
        function moveSliderImage(index, direction) {
            const newIndex = index + direction;
            if (newIndex >= 0 && newIndex < sliderImages.length) {
                [sliderImages[index], sliderImages[newIndex]] = [sliderImages[newIndex], sliderImages[index]];
                renderSliderItems();
            }
        }
        
        function updateSliderContent() {
            // Format: imageUrl;title;description;buttonText;buttonUrl||imageUrl2;title2;description2;buttonText2;buttonUrl2
            const formatted = sliderImages.map(slide => 
                `${removeBaseUrl(slide.url)};${slide.title};${slide.description};${slide.buttonText};${slide.buttonUrl}`
            ).join('||');
            document.getElementById('content').value = formatted;
        }
        
        // Image picker specifically for slides
        function openImagePickerForSlide(index) {
            currentSlideIndex = index;
            openImagePicker();
        }
        
        function openImageUploadForSlide(index) {
            currentSlideIndex = index;
            openImageUpload();
        }
        
        // Bulk upload for slider with descriptions
        function openBulkSliderUpload() {
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.accept = 'image/*';
            input.onchange = function(e) {
                const files = Array.from(e.target.files);
                uploadMultipleSliderImages(files);
            };
            input.click();
        }
        
        function uploadMultipleSliderImages(files) {
            if (files.length === 0) return;
            
            const uploadPromises = files.map(file => {
                const formData = new FormData();
                formData.append('image', file);
                
                return fetch('upload-image.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json());
            });
            
            Promise.all(uploadPromises)
                .then(results => {
                    const successfulUploads = results.filter(r => r.success);
                    if (successfulUploads.length > 0) {
                        successfulUploads.forEach(result => {
                            sliderImages.push({
                                url: result.url,
                                title: '',
                                description: '',
                                buttonText: '',
                                buttonUrl: ''
                            });
                        });
                        renderSliderItems();
                        alert(`Successfully uploaded ${successfulUploads.length} images to slider! You can now add titles and descriptions.`);
                    }
                    const failed = results.filter(r => !r.success);
                    if (failed.length > 0) {
                        alert(`${failed.length} uploads failed.`);
                    }
                })
                .catch(error => {
                    alert('Upload failed: ' + error);
                });
        }
        
        // Gallery Management
        let galleryImages = [];
        const BASE_URL = '<?= BASE_URL ?>';
        
        function initGalleryManager() {
            const content = document.getElementById('content').value;
            galleryImages = content ? content.split('|').filter(img => img.trim()) : [];
            renderGalleryItems();
        }
        
        function renderGalleryItems() {
            const container = document.getElementById('galleryItems');
            container.innerHTML = '';
            
            if (galleryImages.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-500 py-8">No images added yet. Click "Add Image" to get started.</p>';
                return;
            }
            
            galleryImages.forEach((imageUrl, index) => {
                const displayUrl = imageUrl.startsWith('http') ? imageUrl : BASE_URL + "/" + imageUrl;
                //console.log('Display URL:', displayUrl);
                const item = document.createElement('div');
                item.className = 'flex items-center space-x-4 p-4 bg-white border border-gray-200 rounded-lg';
                item.innerHTML = `
                    <div class="flex-shrink-0">
                        <img src="${displayUrl}" alt="Gallery image" class="w-20 h-20 object-cover rounded border" 
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik00MCA0NUw0NSA0MEw1MCA0NUw0NSA1MEw0MCA0NVoiIGZpbGw9IiM5Q0EzQUYiLz4KPC9zdmc+'">
                    </div>
                    <div class="flex-1">
                        <input type="text" value="${imageUrl}" 
                               onchange="updateGalleryImage(${index}, this.value)"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               placeholder="https://example.com/image.jpg">
                    </div>
                    <div class="flex-shrink-0 space-x-2">
                        <button type="button" onclick="moveGalleryImage(${index}, -1)" 
                                class="p-2 text-gray-500 hover:text-gray-700" ${index === 0 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button type="button" onclick="moveGalleryImage(${index}, 1)" 
                                class="p-2 text-gray-500 hover:text-gray-700" ${index === galleryImages.length - 1 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button type="button" onclick="removeGalleryImage(${index})" 
                                class="p-2 text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(item);
            });
            
            updateGalleryContent();
        }
        
        function addGalleryImage() {
            const url = prompt('Enter image URL:');
            if (url && url.trim()) {
                galleryImages.push(url.trim());
                renderGalleryItems();
            }
        }
        
        function updateGalleryImage(index, newUrl) {
            galleryImages[index] = newUrl.trim();
            updateGalleryContent();
            // Refresh the image preview
            const img = document.querySelector(`#galleryItems div:nth-child(${index + 1}) img`);
            if (img) img.src = newUrl;
        }
        
        function removeGalleryImage(index) {
            if (confirm('Remove this image from the gallery?')) {
                galleryImages.splice(index, 1);
                renderGalleryItems();
            }
        }
        
        function moveGalleryImage(index, direction) {
            const newIndex = index + direction;
            if (newIndex >= 0 && newIndex < galleryImages.length) {
                [galleryImages[index], galleryImages[newIndex]] = [galleryImages[newIndex], galleryImages[index]];
                renderGalleryItems();
            }
        }
        
function updateGalleryContent() {
    // Clean the URLs by removing the base URL and leading slashes
    const cleanedImages = galleryImages.map(url => {
        let cleanUrl = url.replace(BASE_URL, '');
        // Remove leading slash if it exists
        if (cleanUrl.startsWith('/')) {
            cleanUrl = cleanUrl.substring(1);
        }
        return cleanUrl;
    });
    
    document.getElementById('content').value = cleanedImages.join('|');
}
        
        // Testimonials Management
// ===== COMPLETELY ISOLATED TESTIMONIALS MANAGER =====
// Wrap everything in IIFE to prevent conflicts
(function() {
    'use strict';
    
    // Private variables - no conflicts possible
    let testimonialsData = [];
    let editingTestimonialIndex = -1;
    let currentTestimonialImageTarget = null;
    let isTestimonialsPageActive = false;
    let initializationComplete = false;
    
    // Check if we're on testimonials page
    function isTestimonialsPage() {
        return document.getElementById('testimonials-preview') && 
               document.getElementById('testimonials-display') && 
               document.getElementById('content');
    }
    
    // Initialize only once and only for testimonials
    function initializeTestimonials() {
        if (initializationComplete || !isTestimonialsPage()) {
            return;
        }
        
        //console.log('🎯 Initializing ISOLATED Testimonials Manager with JSON Storage');
        isTestimonialsPageActive = true;
        initializationComplete = true;
        
        // Load and display immediately
        loadTestimonialsData();
        renderTestimonialsDisplay();
        setupFormListeners();
        
        //console.log('✅ Testimonials Manager Ready');
    }

    // Load testimonials data with multiple format support
    function loadTestimonialsData() {
        const textarea = document.getElementById('content');
        if (!textarea) return;
        
        const content = textarea.value.trim();
        testimonialsData = [];
        
        if (content) {
            try {
                // Try to parse as JSON first
                const parsed = JSON.parse(content);
                
                // Handle different JSON structures
                if (parsed.testimonials && Array.isArray(parsed.testimonials)) {
                    // New JSON format with testimonials array
                    testimonialsData = parsed.testimonials;
                } else if (parsed.features && Array.isArray(parsed.features)) {
                    // Legacy features format - convert to testimonials
                    testimonialsData = parsed.features.map(item => ({
                        name: item.title || '',
                        quote: item.description || '',
                        rating: item.icon || '5',
                        image: item.image || '',
                        dateAdded: item.dateAdded || new Date().toISOString(),
                        verified: item.verified !== undefined ? item.verified : true
                    }));
                } else if (Array.isArray(parsed)) {
                    // Direct array of testimonials
                    testimonialsData = parsed;
                } else {
                    // Single testimonial object
                    testimonialsData = [parsed];
                }
                
                // Ensure all testimonials have required fields
                testimonialsData = testimonialsData.map(testimonial => ({
                    name: testimonial.name || '',
                    quote: testimonial.quote || '',
                    rating: testimonial.rating || '5',
                    image: testimonial.image || '',
                    dateAdded: testimonial.dateAdded || new Date().toISOString(),
                    verified: testimonial.verified !== undefined ? testimonial.verified : true,
                    ...testimonial // Keep any additional fields
                }));
                
            } catch (e) {
                // If JSON parsing fails, try the pipe-separated format for backward compatibility
                //console.log('📄 Attempting to parse legacy pipe-separated format');
                try {
                    const items = content.split('||');
                    testimonialsData = items.map(item => {
                        const parts = item.split('|').map(p => p.trim());
                        if (parts.length >= 2 && parts[0] && parts[1]) {
                            return {
                                name: parts[0],
                                quote: parts[1],
                                rating: parts[2] || '5',
                                image: parts[3] || '',
                                dateAdded: new Date().toISOString(),
                                verified: true
                            };
                        }
                        return null;
                    }).filter(Boolean);
                    
                    // Immediately convert to JSON format
                    if (testimonialsData.length > 0) {
                        //console.log('🔄 Converting legacy format to JSON');
                        saveToTextarea();
                    }
                } catch (e2) {
                    //console.warn('Error parsing testimonials:', e2);
                    testimonialsData = [];
                }
            }
        }
        
        //console.log(`📊 Loaded ${testimonialsData.length} testimonials`);
        updateCounters();
    }
    
    // Update counters
    function updateCounters() {
        const countEl = document.getElementById('testimonials-count');
        const lengthEl = document.getElementById('testimonials-textarea-length');
        const textarea = document.getElementById('content');
        
        if (countEl) countEl.textContent = testimonialsData.length;
        if (lengthEl && textarea) lengthEl.textContent = textarea.value.length;
    }
    
    // Render testimonials display
    function renderTestimonialsDisplay() {
        const container = document.getElementById('testimonials-display');
        if (!container) return;
        
        if (testimonialsData.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 col-span-full">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-quote-left text-4xl"></i>
                    </div>
                    <p class="text-gray-500 text-lg mb-2">No testimonials yet</p>
                    <p class="text-gray-400 text-sm">Add your first customer testimonial using the form below</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = testimonialsData.map((testimonial, index) => {
            const isEditing = editingTestimonialIndex === index;
            const editingClass = isEditing ? 'ring-2 ring-orange-400 border-orange-300' : 'border-gray-200';
            const stars = '⭐'.repeat(parseInt(testimonial.rating) || 5);
            const displayImageUrl = getFullImageUrl(testimonial.image);
            const dateAdded = testimonial.dateAdded ? new Date(testimonial.dateAdded).toLocaleDateString() : 'Unknown';
            const verifiedBadge = testimonial.verified ? 
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-2"><i class="fas fa-check-circle mr-1"></i>Verified</span>' : 
                '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 ml-2">Unverified</span>';
            
            return `
                <div class="bg-white rounded-lg shadow-md ${editingClass} overflow-hidden transition-all duration-200 hover:shadow-lg relative" data-testimonial-index="${index}">
                    ${isEditing ? `
                        <div class="absolute -top-2 -right-2 bg-orange-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold animate-pulse z-10">
                            ✏️
                        </div>
                    ` : ''}
                    
                    <div class="p-6">
                        <!-- Quote -->
                        <div class="mb-4">
                            <i class="fas fa-quote-left text-2xl text-yellow-500 mb-2"></i>
                            <p class="text-gray-700 italic leading-relaxed">"${escapeHtml(testimonial.quote)}"</p>
                        </div>
                        
                        <!-- Rating -->
                        <div class="mb-4">
                            <span class="text-yellow-500">${stars}</span>
                            <span class="text-sm text-gray-500 ml-2">(${testimonial.rating}/5)</span>
                        </div>
                        
                        <!-- Customer Info -->
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full overflow-hidden mr-3 bg-gray-100 flex-shrink-0">
                                ${testimonial.image ? `
                                    <img src="${displayImageUrl}" alt="${escapeHtml(testimonial.name)}" 
                                         class="w-full h-full object-cover" 
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="w-full h-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center hidden">
                                        <i class="fas fa-user text-gray-500 text-lg"></i>
                                    </div>
                                ` : `
                                    <div class="w-full h-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-500 text-lg"></i>
                                    </div>
                                `}
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 text-sm">${escapeHtml(testimonial.name)}</h4>
                                <div class="flex items-center text-xs text-gray-500">
                                    <span>Added: ${dateAdded}</span>
                                    ${verifiedBadge}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <button onclick="IsolatedTestimonials.edit(${index})" 
                                    class="flex-1 bg-yellow-500 text-white py-2 px-3 rounded-lg hover:bg-yellow-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                            <button onclick="IsolatedTestimonials.duplicate(${index})" 
                                    class="flex-1 bg-green-500 text-white py-2 px-3 rounded-lg hover:bg-green-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-copy mr-1"></i> Copy
                            </button>
                            <button onclick="IsolatedTestimonials.move(${index}, -1)" 
                                    class="bg-gray-500 text-white py-2 px-3 rounded-lg hover:bg-gray-600 transition-colors duration-200 text-xs font-medium" 
                                    ${index === 0 ? 'disabled style="opacity:0.5"' : ''}>
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button onclick="IsolatedTestimonials.move(${index}, 1)" 
                                    class="bg-gray-500 text-white py-2 px-3 rounded-lg hover:bg-gray-600 transition-colors duration-200 text-xs font-medium" 
                                    ${index === testimonialsData.length - 1 ? 'disabled style="opacity:0.5"' : ''}>
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button onclick="IsolatedTestimonials.remove(${index})" 
                                    class="bg-red-500 text-white py-2 px-3 rounded-lg hover:bg-red-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        updateCounters();
        //console.log(`🎨 Rendered ${testimonialsData.length} testimonials`);
    }
    
    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function getFullImageUrl(imagePath) {
        if (!imagePath) return '';
        
        // If it's already a full URL, return as-is
        if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith('data:')) {
            return imagePath;
        }
        
        // Get base URL from window
        window.BASE_URL = '<?php echo BASE_URL; ?>';
        let baseUrl = window.BASE_URL || '';
        
        // If no BASE_URL is set, use current origin
        if (!baseUrl) {
            baseUrl = window.location.origin;
        }
        
        // Remove trailing slash from base URL
        baseUrl = baseUrl.replace(/\/+$/, '');
        
        // Clean up image path - remove leading slashes
        const cleanImagePath = imagePath.replace(/^\/+/, '');
        
        // Construct full URL with base URL
        const fullUrl = `${baseUrl}/${cleanImagePath}`;
        
        return fullUrl;
    }
    
    function removeBaseUrl(url) {
        if (!url) return '';
        
        //console.log(`🔧 Starting URL cleanup for: ${url}`);
        
        // If it's an external URL (http/https), process it
        if (url.startsWith('http://') || url.startsWith('https://')) {
            const baseUrl = window.BASE_URL || window.location.origin;
            //console.log(`   Base URL: ${baseUrl}`);
            
            if (baseUrl && url.startsWith(baseUrl)) {
                // Remove base URL part
                let cleanUrl = url.replace(baseUrl, '');
                //console.log(`   After base URL removal: ${cleanUrl}`);
                
                // Remove leading slashes
                cleanUrl = cleanUrl.replace(/^\/+/, '');
                //console.log(`   After slash removal: ${cleanUrl}`);
                
                // Keep uploads/ prefix - this is what we want to save
                //console.log(`✅ Final cleaned URL (keeping uploads/): ${cleanUrl}`);
                return cleanUrl;
            }
            // If it's an external URL from different domain, keep as-is
            //console.log(`   External URL kept as-is: ${url}`);
            return url;
        }
        
        // If it's a data URL, keep as-is
        if (url.startsWith('data:')) {
            //console.log(`   Data URL kept as-is`);
            return url;
        }
        
        // For relative URLs, ensure they have uploads/ prefix
        let cleanUrl = url.replace(/^\/+/, '');
        //console.log(`   After slash removal: ${cleanUrl}`);
        
        // If it doesn't start with uploads/, add it
        if (!cleanUrl.startsWith('uploads/') && cleanUrl.length > 0) {
            cleanUrl = `uploads/${cleanUrl}`;
            //console.log(`   Added uploads/ prefix: ${cleanUrl}`);
        }
        
        //console.log(`✅ Final cleaned relative URL: ${cleanUrl}`);
        return cleanUrl;
    }
    
    // Save to textarea in JSON format
    function saveToTextarea() {
        const textarea = document.getElementById('content');
        if (!textarea) return;
        
        // Create structured JSON with metadata
        const jsonData = {
            testimonials: testimonialsData,
            metadata: {
                totalTestimonials: testimonialsData.length,
                lastUpdated: new Date().toISOString(),
                version: "3.0",
                format: "json",
                createdBy: "IsolatedTestimonialsManager"
            }
        };
        
        // Pretty print JSON for better readability
        const formatted = JSON.stringify(jsonData, null, 2);
        
        // Temporarily disable any existing listeners
        const oldValue = textarea.value;
        textarea.value = formatted;
        
        // Only trigger change if value actually changed
        if (oldValue !== formatted) {
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        updateCounters();
        //console.log('💾 Saved to textarea in JSON format');
    }
    
    // Setup form listeners
    function setupFormListeners() {
        const imageField = document.getElementById('new-testimonial-image');
        if (imageField) {
            imageField.addEventListener('input', function() {
                const previewContainer = document.getElementById('testimonial-image-preview');
                const previewImg = document.getElementById('preview-testimonial-image');
                
                if (this.value.trim() && previewContainer && previewImg) {
                    previewImg.src = this.value;
                    previewContainer.classList.remove('hidden');
                    
                    previewImg.onerror = function() {
                        previewContainer.classList.add('hidden');
                        showNotification('❌ Failed to load image', 'error');
                    };
                } else if (previewContainer) {
                    previewContainer.classList.add('hidden');
                }
            });
        }
    }
    
    // Notification system
    function showNotification(message, type = 'success') {
        const existing = document.querySelector('.isolated-testimonial-notification');
        if (existing) existing.remove();
        
        const colors = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            info: 'bg-blue-500 text-white',
            warning: 'bg-yellow-500 text-black'
        };
        
        const notification = document.createElement('div');
        notification.className = `isolated-testimonial-notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${colors[type] || colors.success}`;
        notification.innerHTML = `
            <div class="flex items-center">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-lg hover:opacity-75">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 4000);
    }
    
    // Public API functions
    window.IsolatedTestimonials = {
        add: function() {
            const name = document.getElementById('new-testimonial-name')?.value.trim() || '';
            const quote = document.getElementById('new-testimonial-quote')?.value.trim() || '';
            const rating = document.getElementById('new-testimonial-rating')?.value.trim() || '5';
            const imageInput = document.getElementById('new-testimonial-image')?.value.trim() || '';
            const verified = document.getElementById('new-testimonial-verified')?.checked !== false; // Default to true
            
            //console.log(`💾 Adding testimonial with image input: ${imageInput}`);
            
            if (!name) {
                showNotification('❌ Please enter the customer name.', 'error');
                document.getElementById('new-testimonial-name')?.focus();
                return;
            }
            
            if (!quote) {
                showNotification('❌ Please enter the testimonial quote.', 'error');
                document.getElementById('new-testimonial-quote')?.focus();
                return;
            }
            
            const image = removeBaseUrl(imageInput);
            //console.log(`💾 Image after cleanup: ${image}`);
            
            const testimonialData = { 
                name, 
                quote, 
                rating, 
                image,
                dateAdded: new Date().toISOString(),
                verified: verified
            };
            //console.log(`💾 Final testimonial data:`, testimonialData);
            
            if (editingTestimonialIndex >= 0) {
                const oldName = testimonialsData[editingTestimonialIndex].name;
                // Preserve original dateAdded when editing
                testimonialData.dateAdded = testimonialsData[editingTestimonialIndex].dateAdded || testimonialData.dateAdded;
                testimonialsData[editingTestimonialIndex] = testimonialData;
                showNotification(`✅ Testimonial from "${oldName}" updated!`, 'success');
            } else {
                testimonialsData.push(testimonialData);
                showNotification(`🎉 New testimonial from "${name}" added!`, 'success');
            }
            
            this.clearForm();
            saveToTextarea();
            renderTestimonialsDisplay();
        },
        
        // Export functions
        exportJSON: function() {
            const jsonData = {
                testimonials: testimonialsData,
                metadata: {
                    totalTestimonials: testimonialsData.length,
                    exportedAt: new Date().toISOString(),
                    version: "3.0",
                    format: "json"
                }
            };
            
            const blob = new Blob([JSON.stringify(jsonData, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `testimonials-${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
            
            showNotification('📁 Testimonials exported to JSON file!', 'success');
        },
        
        importJSON: function() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const importedData = JSON.parse(e.target.result);
                            
                            // Handle different JSON structures
                            let importedTestimonials = [];
                            if (importedData.testimonials && Array.isArray(importedData.testimonials)) {
                                importedTestimonials = importedData.testimonials;
                            } else if (Array.isArray(importedData)) {
                                importedTestimonials = importedData;
                            }
                            
                            if (importedTestimonials.length > 0) {
                                if (confirm(`Import ${importedTestimonials.length} testimonials? This will replace existing data.`)) {
                                    testimonialsData = importedTestimonials;
                                    saveToTextarea();
                                    renderTestimonialsDisplay();
                                    showNotification(`✅ Imported ${importedTestimonials.length} testimonials!`, 'success');
                                }
                            } else {
                                showNotification('❌ No valid testimonials found in file', 'error');
                            }
                        } catch (error) {
                            showNotification('❌ Invalid JSON file', 'error');
                            //console.error('Import error:', error);
                        }
                    };
                    reader.readAsText(file);
                }
            };
            input.click();
        },
        
        // Image picker and upload functions
        openImagePicker: function() {
            currentTestimonialImageTarget = 'form';
            if (typeof openImagePicker === 'function') {
                openImagePicker();
            } else {
                showNotification('❌ Image picker not available', 'error');
            }
        },
        
        openImageUpload: function() {
            currentTestimonialImageTarget = 'form';
            if (typeof openImageUpload === 'function') {
                openImageUpload();
            } else {
                // Fallback: create upload input
                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*';
                input.onchange = function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const formData = new FormData();
                        formData.append('image', file);
                        
                        fetch('upload-image.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const imageField = document.getElementById('new-testimonial-image');
                                if (imageField) {
                                    imageField.value = data.url;
                                    // Trigger input event to update preview
                                    imageField.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                                showNotification('✅ Image uploaded successfully!', 'success');
                            } else {
                                showNotification('❌ Upload failed: ' + data.error, 'error');
                            }
                        })
                        .catch(error => {
                            showNotification('❌ Upload failed: ' + error.message, 'error');
                        });
                    }
                };
                input.click();
            }
        },
        
        selectImage: function(url) {
            if (currentTestimonialImageTarget === 'form') {
                const imageField = document.getElementById('new-testimonial-image');
                if (imageField) {
                    imageField.value = url;
                    // Trigger input event to update preview
                    imageField.dispatchEvent(new Event('input', { bubbles: true }));
                }
                currentTestimonialImageTarget = null;
                if (typeof closeImagePicker === 'function') {
                    closeImagePicker();
                }
                return true;
            }
            return false;
        },
        
        edit: function(index) {
            if (!testimonialsData[index]) {
                showNotification('❌ Testimonial not found!', 'error');
                return;
            }
            
            const testimonial = testimonialsData[index];
            editingTestimonialIndex = index;
            
            const nameField = document.getElementById('new-testimonial-name');
            const quoteField = document.getElementById('new-testimonial-quote');
            const ratingField = document.getElementById('new-testimonial-rating');
            const imageField = document.getElementById('new-testimonial-image');
            const verifiedField = document.getElementById('new-testimonial-verified');
            
            if (nameField) nameField.value = testimonial.name || '';
            if (quoteField) quoteField.value = testimonial.quote || '';
            if (ratingField) ratingField.value = testimonial.rating || '5';
            if (verifiedField) verifiedField.checked = testimonial.verified !== false;
            
            // For editing, show the full URL in the input field
            if (imageField) {
                const fullImageUrl = getFullImageUrl(testimonial.image);
                imageField.value = fullImageUrl;
                //console.log(`📝 Edit mode - showing full URL: ${fullImageUrl}`);
            }
            
            // Update image preview
            if (testimonial.image) {
                const previewContainer = document.getElementById('testimonial-image-preview');
                const previewImg = document.getElementById('preview-testimonial-image');
                if (previewContainer && previewImg) {
                    previewImg.src = getFullImageUrl(testimonial.image);
                    previewContainer.classList.remove('hidden');
                }
            }
            
            // Update form UI
            const addButton = document.querySelector('button[onclick*="IsolatedTestimonials.add"]');
            if (addButton) {
                addButton.innerHTML = '<i class="fas fa-save mr-2"></i>💾 Update Testimonial';
                addButton.className = addButton.className.replace('bg-blue-500', 'bg-green-500').replace('hover:bg-blue-600', 'hover:bg-green-600');
            }
            
            const cancelButton = document.querySelector('button[onclick*="IsolatedTestimonials.cancel"]');
            if (cancelButton) cancelButton.style.display = 'inline-block';
            
            // Update header
            const formHeaders = document.querySelectorAll('h4');
            formHeaders.forEach(header => {
                if (header.textContent.includes('Add New Testimonial')) {
                    header.innerHTML = '<i class="fas fa-edit mr-2 text-orange-600"></i>✏️ Edit Testimonial';
                }
            });
            
            // Scroll to form
            const formElement = document.querySelector('.bg-white.border-2.border-gray-300');
            if (formElement) {
                formElement.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
                
                formElement.style.boxShadow = '0 0 20px rgba(251, 191, 36, 0.3)';
                setTimeout(() => {
                    formElement.style.boxShadow = '';
                }, 2000);
            }
            
            renderTestimonialsDisplay();
            showNotification(`✏️ Now editing testimonial from: "${testimonial.name}"`, 'info');
        },
        
        cancel: function() {
            this.clearForm();
            showNotification('✖️ Edit cancelled', 'info');
        },
        
        clearForm: function() {
            const fields = ['new-testimonial-name', 'new-testimonial-quote', 'new-testimonial-rating', 'new-testimonial-image'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) field.value = '';
            });
            
            const ratingField = document.getElementById('new-testimonial-rating');
            if (ratingField) ratingField.value = '5';
            
            const verifiedField = document.getElementById('new-testimonial-verified');
            if (verifiedField) verifiedField.checked = true;
            
            const previewContainer = document.getElementById('testimonial-image-preview');
            if (previewContainer) previewContainer.classList.add('hidden');
            
            const addButton = document.querySelector('button[onclick*="IsolatedTestimonials.add"]');
            if (addButton) {
                addButton.innerHTML = '<i class="fas fa-plus mr-2"></i>➕ Add Testimonial';
                addButton.className = addButton.className.replace('bg-green-500', 'bg-blue-500').replace('hover:bg-green-600', 'hover:bg-blue-600');
            }
            
            const cancelButton = document.querySelector('button[onclick*="IsolatedTestimonials.cancel"]');
            if (cancelButton) cancelButton.style.display = 'none';
            
            const formHeaders = document.querySelectorAll('h4');
            formHeaders.forEach(header => {
                if (header.textContent.includes('Edit Testimonial')) {
                    header.innerHTML = '<i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New Testimonial';
                }
            });
            
            editingTestimonialIndex = -1;
            renderTestimonialsDisplay();
        },
        
duplicate: function(index) {
            if (!testimonialsData[index]) return;
            
            const testimonial = { ...testimonialsData[index] };
            testimonial.name = testimonial.name + ' (Copy)';
            testimonial.dateAdded = new Date().toISOString();
            
            testimonialsData.splice(index + 1, 0, testimonial);
            
            saveToTextarea();
            renderTestimonialsDisplay();
            showNotification(`📋 Testimonial duplicated: "${testimonial.name}"`, 'success');
        },
        
        move: function(index, direction) {
            if (!testimonialsData[index]) return;
            
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= testimonialsData.length) return;
            
            const testimonial = testimonialsData[index];
            const movingName = testimonial.name;
            
            // Swap elements
            [testimonialsData[index], testimonialsData[newIndex]] = [testimonialsData[newIndex], testimonialsData[index]];
            
            // Update editing index if needed
            if (editingTestimonialIndex === index) {
                editingTestimonialIndex = newIndex;
            } else if (editingTestimonialIndex === newIndex) {
                editingTestimonialIndex = index;
            }
            
            saveToTextarea();
            renderTestimonialsDisplay();
            showNotification(`🔄 "${movingName}" moved ${direction > 0 ? 'down' : 'up'}`, 'success');
        },
        
        remove: function(index) {
            if (!testimonialsData[index]) return;
            
            const testimonial = testimonialsData[index];
            const testimonialName = testimonial.name;
            
            if (confirm(`⚠️ Are you sure you want to delete the testimonial from "${testimonialName}"?\n\nThis action cannot be undone.`)) {
                testimonialsData.splice(index, 1);
                
                // Reset editing state if we were editing this testimonial
                if (editingTestimonialIndex === index) {
                    this.clearForm();
                } else if (editingTestimonialIndex > index) {
                    editingTestimonialIndex--;
                }
                
                saveToTextarea();
                renderTestimonialsDisplay();
                showNotification(`🗑️ Testimonial from "${testimonialName}" deleted`, 'success');
            }
        },
        
        clear: function() {
            if (testimonialsData.length === 0) {
                showNotification('ℹ️ No testimonials to clear', 'info');
                return;
            }
            
            if (confirm(`⚠️ Are you sure you want to delete ALL ${testimonialsData.length} testimonials?\n\nThis action cannot be undone.`)) {
                testimonialsData = [];
                this.clearForm();
                saveToTextarea();
                renderTestimonialsDisplay();
                showNotification('🧹 All testimonials cleared', 'success');
            }
        },
        
        // Bulk operations
        sortByName: function() {
            if (testimonialsData.length < 2) return;
            
            testimonialsData.sort((a, b) => a.name.localeCompare(b.name));
            this.clearForm();
            saveToTextarea();
            renderTestimonialsDisplay();
            showNotification('📊 Testimonials sorted by name', 'success');
        },
        
        sortByRating: function() {
            if (testimonialsData.length < 2) return;
            
            testimonialsData.sort((a, b) => parseInt(b.rating) - parseInt(a.rating));
            this.clearForm();
            saveToTextarea();
            renderTestimonialsDisplay();
            showNotification('⭐ Testimonials sorted by rating', 'success');
        },
        
        sortByDate: function() {
            if (testimonialsData.length < 2) return;
            
            testimonialsData.sort((a, b) => new Date(b.dateAdded) - new Date(a.dateAdded));
            this.clearForm();
            saveToTextarea();
            renderTestimonialsDisplay();
            showNotification('📅 Testimonials sorted by date', 'success');
        },
        
        // Statistics
        getStats: function() {
            if (testimonialsData.length === 0) {
                return {
                    total: 0,
                    averageRating: 0,
                    verified: 0,
                    unverified: 0
                };
            }
            
            const total = testimonialsData.length;
            const totalRating = testimonialsData.reduce((sum, t) => sum + parseInt(t.rating || 5), 0);
            const averageRating = (totalRating / total).toFixed(1);
            const verified = testimonialsData.filter(t => t.verified).length;
            const unverified = total - verified;
            
            return {
                total,
                averageRating,
                verified,
                unverified
            };
        },
        
        showStats: function() {
            const stats = this.getStats();
            const message = `📊 Testimonials Statistics:\n\n` +
                          `Total: ${stats.total}\n` +
                          `Average Rating: ${stats.averageRating}/5 ⭐\n` +
                          `Verified: ${stats.verified}\n` +
                          `Unverified: ${stats.unverified}`;
            
            alert(message);
        },
        
        // Initialize when DOM is ready
        init: initializeTestimonials
    };
    
    // Auto-initialize when DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTestimonials);
    } else {
        // DOM already loaded
        initializeTestimonials();
    }
    
    // Also try to initialize on window load as fallback
    window.addEventListener('load', initializeTestimonials);
    
})();

// Initialize immediately if elements exist
if (document.getElementById('testimonials-preview') && 
    document.getElementById('testimonials-display') && 
    document.getElementById('content')) {
    setTimeout(() => {
        if (window.IsolatedTestimonials && window.IsolatedTestimonials.init) {
            window.IsolatedTestimonials.init();
        }
    }, 100);
}

// ----------------------------------------------------------------------------------------------------------------------------
        
        // Bulk image upload for gallery
        function openBulkImageUpload() {
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.accept = 'image/*';
            input.onchange = function(e) {
                const files = Array.from(e.target.files);
                uploadMultipleImages(files);
            };
            input.click();
        }
        
        function uploadMultipleImages(files) {
            if (files.length === 0) return;
            
            const uploadPromises = files.map(file => {
                const formData = new FormData();
                formData.append('image', file);
                
                return fetch('upload-image.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json());
            });
            
            Promise.all(uploadPromises)
                .then(results => {
                    const successfulUploads = results.filter(r => r.success);
                    if (successfulUploads.length > 0) {
                        successfulUploads.forEach(result => {
                            galleryImages.push(result.url);
                        });
                        renderGalleryItems();
                        alert(`Successfully uploaded ${successfulUploads.length} images!`);
                    }
                    const failed = results.filter(r => !r.success);
                    if (failed.length > 0) {
                        alert(`${failed.length} uploads failed.`);
                    }
                })
                .catch(error => {
                    alert('Upload failed: ' + error);
                });
        }
        
        // Color picker functions
        function setColor(inputId, color) {
            document.getElementById(inputId).value = color;
            const textInputId = inputId === 'background_color' ? 'bg_text' : 'text_text';
            document.getElementById(textInputId).value = color;
        }
        
        // Image picker and upload functions
        function openImagePicker() {
            document.getElementById('imagePickerModal').classList.remove('hidden');
            loadImages();
        }
        
        function closeImagePicker() {
            document.getElementById('imagePickerModal').classList.add('hidden');
        }
        
        function openImageUpload() {
            document.getElementById('uploadModal').classList.remove('hidden');
        }
        
        function closeImageUpload() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('uploadForm').reset();
            document.getElementById('imagePreview').classList.add('hidden');
        }
        
        function loadImages() {
            const imageGrid = document.getElementById('imageGrid');
            imageGrid.innerHTML = '<p class="col-span-full text-center text-gray-500">Loading images...</p>';
            
            // Load images from your gallery
            fetch('get-images.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.images.length > 0) {
                        imageGrid.innerHTML = '';
                        data.images.forEach(image => {
                            const imageDiv = document.createElement('div');
                            imageDiv.className = 'relative group cursor-pointer';
                            imageDiv.innerHTML = `
                                <img src="${image.file_path}" alt="${image.original_name}" 
                                     class="w-full h-24 object-cover rounded border hover:opacity-80 transition duration-200"
                                     onclick="selectImage('${image.file_path}')">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition duration-200 rounded"></div>
                            `;
                            imageGrid.appendChild(imageDiv);
                        });
                    } else {
                        imageGrid.innerHTML = '<p class="col-span-full text-center text-gray-500">No images found. Upload some images first.</p>';
                    }
                })
                .catch(error => {
                    imageGrid.innerHTML = '<p class="col-span-full text-center text-red-500">Error loading images.</p>';
                });
        }
        
        function selectImage(url) {
            if (currentSlideIndex >= 0) {
                // For slider slides
                sliderImages[currentSlideIndex].url = url;
                renderSliderItems();
                currentSlideIndex = -1;
                closeImagePicker();
                return;
            }
            
            const currentField = document.querySelector('input[name="image_url"]');
            if (currentField) {
                currentField.value = url;
            } else if (document.getElementById('galleryManager')) {
                // For gallery sections, add to gallery
                galleryImages.push(url);
                renderGalleryItems();
            } else {
                // For other sections, try to find content field
                const contentField = document.querySelector('textarea[name="content"]');
                if (contentField) {
                    const currentContent = contentField.value;
                    contentField.value = currentContent ? currentContent + '|' + url : url;
                }
            }
            closeImagePicker();
        }
        
        // Image preview for upload
        document.getElementById('imageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Upload form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const uploadBtn = document.getElementById('uploadBtn');
            
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
            
            fetch('upload-image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (currentSlideIndex >= 0) {
                        // For slider slides
                        sliderImages[currentSlideIndex].url = data.url;
                        renderSliderItems();
                        currentSlideIndex = -1;
                    } else {
                        selectImage(data.url);
                    }
                    closeImageUpload();
                } else {
                    alert('Upload failed: ' + data.error);
                }
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
            })
            .catch(error => {
                alert('Upload failed: ' + error);
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload';
            });
        });

// ===== COMPLETE PRICING PLANS MANAGEMENT SCRIPT =====

let plansData = [];
let editingPlanIndex = -1;
// let isFormSubmitting = false; // ✅ FIXED: Added missing variable

function migrateExistingPlans() {
    const contentTextarea = document.getElementById('content');
    if (!contentTextarea) {
        //console.log('Textarea not found');
        return;
    }
    
    const content = contentTextarea.value.trim();
    if (!content) {
        //console.log('No content to migrate');
        return;
    }
    
    //console.log('Original content:', content);
    
    // Split by double pipes to get individual plans
    const plans = content.split('||').filter(plan => plan.trim());
    
    const migratedPlans = plans.map(plan => {
        const parts = plan.split('|');
        
        // Check if this plan already has the popular field (5th position)
        // If the 5th part exists and is '0' or '1', it's already migrated
        if (parts.length >= 5 && (parts[4] === '0' || parts[4] === '1')) {
            //console.log('Plan already migrated:', parts[0]);
            return plan; // Already has popular field
        }
        
        // Insert popular field as '0' (not popular) after description (4th position)
        if (parts.length >= 4) {
            // Insert '0' at position 4 (after description, before features)
            parts.splice(4, 0, '0');
            //console.log('Migrated plan:', parts[0], 'with popular field:', parts[4]);
            return parts.join('|');
        }
        
        //console.log('Skipping invalid plan:', plan);
        return plan; // Return as-is if invalid
    });
    
    const migratedContent = migratedPlans.join('||');
    //console.log('Migrated content:', migratedContent);
    
    // Update textarea
    contentTextarea.value = migratedContent;
    
    // Trigger change event to reload plans
    const event = new Event('input', { bubbles: true });
    contentTextarea.dispatchEvent(event);
    
    //console.log('Migration complete! You can now set popular plans using the checkbox.');
}

// Run the migration
migrateExistingPlans();

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    //console.log('=== PRICING SECTION LOADED ===');
    if (document.getElementById('pricing-preview')) {
        loadExistingPlans();
        updatePreview();
        setupFormListeners();
        setupTextareaMonitoring();
    }
});

// Setup form listeners for real-time preview
function setupFormListeners() {
    const formInputs = ['new-plan-name', 'new-plan-price', 'new-plan-period', 'new-plan-description'];
    formInputs.forEach(inputId => {
        const element = document.getElementById(inputId);
        if (element) {
            element.addEventListener('input', updateLivePreview);
        }
    });
    
    const markPopular = document.getElementById('mark-popular');
    if (markPopular) {
        markPopular.addEventListener('change', updateLivePreview);
    }
}

// Setup textarea monitoring
function setupTextareaMonitoring() {
    const contentTextarea = document.getElementById('content');
    if (contentTextarea) {
        contentTextarea.addEventListener('input', function() {
            if (!isFormSubmitting) {
                loadExistingPlans();
                updatePreview();
                updateCharacterCount();
            }
        });
        updateCharacterCount();
    }
}

// ✅ FIXED: Load existing plans from textarea with proper popular handling
function loadExistingPlans() {
    const contentTextarea = document.getElementById('content');
    if (!contentTextarea) return;
    
    const content = contentTextarea.value.trim();
    //console.log('Loading plans from content:', content);
    
    plansData = [];
    
    if (content) {
        // Split by double pipes to separate different plans
        const plans = content.split('||').filter(plan => plan.trim());
        
        plansData = plans.map(plan => {
            const parts = plan.split('|');
            
            //console.log('Processing plan parts:', parts);
            
            if (parts.length >= 2 && parts[0].trim() && parts[1].trim()) {
                // Handle the format: Name|Price|Period|Description|Popular|Feature1|Feature2|...
                const planData = {
                    name: parts[0].trim(),
                    price: parts[1].trim(),
                    period: parts[2] ? parts[2].trim() : '/month',
                    description: parts[3] ? parts[3].trim() : '',
                    popular: parts[4] === '1', // ✅ FIXED: Parse popular status properly
                    features: []
                };
                
                // Extract features (everything after popular status)
                if (parts.length > 5) {
                    planData.features = parts.slice(5)
                        .map(feature => feature.trim())
                        .filter(feature => feature !== ''); // Remove empty features
                }
                
                // Handle special cases for period
                if (planData.period === '') {
                    planData.period = '/month'; // Default if empty
                } else if (!planData.period.startsWith('/') && planData.period !== 'lifetime' && planData.period !== 'one-time') {
                    planData.period = '/' + planData.period; // Add slash if missing
                }
                
                //console.log('Parsed plan:', planData);
                return planData;
            }
            
            //console.log('Skipping invalid plan:', parts);
            return null;
        }).filter(plan => plan !== null);
    }
    
    //console.log('Final loaded plans:', plansData);
    updatePlanCount();
}

// Update plan count display
function updatePlanCount() {
    const planCountElement = document.getElementById('plan-count');
    if (planCountElement) {
        planCountElement.textContent = plansData.length;
    }
    updateCharacterCount();
}

// Update character count
function updateCharacterCount() {
    const textareaLengthElement = document.getElementById('textarea-length');
    const content = document.getElementById('content').value;
    
    if (textareaLengthElement) {
        textareaLengthElement.textContent = content.length;
    }
}

// Update live preview as user types
function updateLivePreview() {
    const name = document.getElementById('new-plan-name').value.trim();
    const price = document.getElementById('new-plan-price').value.trim();
    
    if (name || price) {
        showLiveFormPreview();
    } else {
        removeLivePreview();
    }
}

// Show live preview
function showLiveFormPreview() {
    const name = document.getElementById('new-plan-name').value.trim() || 'New Plan';
    const price = document.getElementById('new-plan-price').value.trim() || '0';
    const period = document.getElementById('new-plan-period').value.trim() || '/month';
    const description = document.getElementById('new-plan-description').value.trim();
    const popular = document.getElementById('mark-popular').checked;
    
    // Get current features from form
    const currentFeatures = [];
    document.querySelectorAll('.feature-input').forEach(input => {
        if (input.value.trim()) {
            currentFeatures.push(input.value.trim());
        }
    });
    
    // Format price
    let displayPrice = price;
    if (displayPrice && !displayPrice.includes('$') && !displayPrice.includes('€') && !displayPrice.includes('£') && displayPrice !== 'Free' && displayPrice !== 'Custom') {
        displayPrice = '$' + displayPrice;
    }
    
    // Create or update live preview
    let livePreviewContainer = document.getElementById('live-preview-container');
    if (!livePreviewContainer) {
        livePreviewContainer = document.createElement('div');
        livePreviewContainer.id = 'live-preview-container';
        livePreviewContainer.className = 'mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg';
        livePreviewContainer.innerHTML = '<h4 class="font-medium text-blue-900 mb-3">📋 Live Preview</h4><div id="live-preview-card"></div>';
        document.querySelector('.bg-white.border-2.border-gray-300').appendChild(livePreviewContainer);
    }
    
    const livePreviewCard = document.getElementById('live-preview-card');
    livePreviewCard.innerHTML = generatePlanHTML({
        name, price: displayPrice, period, description, features: currentFeatures, popular
    }, -1, true);
}

// Remove live preview
function removeLivePreview() {
    const livePreview = document.getElementById('live-preview-container');
    if (livePreview) {
        livePreview.remove();
    }
}

// Add feature input row
function addFeatureInput() {
    const featuresContainer = document.getElementById('features-list');
    const featureDiv = document.createElement('div');
    featureDiv.className = 'flex gap-2 feature-row';
    featureDiv.innerHTML = `
        <input type="text" placeholder="Feature description" 
               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm feature-input">
        <button type="button" onclick="removeFeatureInput(this)" 
                class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition duration-200">
            <i class="fas fa-times"></i>
        </button>
    `;
    featuresContainer.appendChild(featureDiv);
    
    // Add event listener for live preview
    featureDiv.querySelector('.feature-input').addEventListener('input', updateLivePreview);
}

// Remove feature input row
function removeFeatureInput(button) {
    button.closest('.feature-row').remove();
    updateLivePreview();
}

// ✅ FIXED: Add new plan or update existing with proper debugging
function addNewPlan() {
    //console.log('=== ADD/UPDATE PLAN CALLED ===');
    //console.log('Editing index:', editingPlanIndex);
    
    const name = document.getElementById('new-plan-name').value.trim();
    const price = document.getElementById('new-plan-price').value.trim();
    const period = document.getElementById('new-plan-period').value.trim();
    const description = document.getElementById('new-plan-description').value.trim();
    const popular = document.getElementById('mark-popular').checked;
    
    //console.log('Form values:', { name, price, period, description, popular });
    
    // Validation
    if (!name || !price) {
        showPricingNotification('❌ Please enter at least a plan name and price.', 'error');
        return;
    }

    // Get features
    const features = [];
    document.querySelectorAll('.feature-input').forEach(input => {
        if (input.value.trim()) {
            features.push(input.value.trim());
        }
    });

    const planData = {
        name: name,
        price: price,
        period: period,
        description: description,
        features: features,
        popular: popular // ✅ FIXED: Properly include popular status
    };

    //console.log('Plan data to save:', planData);
    //console.log('Current plansData before operation:', plansData);

    if (editingPlanIndex >= 0 && editingPlanIndex < plansData.length) {
        // Update existing plan
        //console.log('UPDATING existing plan at index:', editingPlanIndex);
        const oldName = plansData[editingPlanIndex].name;
        plansData[editingPlanIndex] = planData;
        showPricingNotification(`✅ Plan "${oldName}" updated successfully!`, 'success');
    } else {
        // Add new plan
        //console.log('ADDING new plan');
        plansData.push(planData);
        showPricingNotification(`🎉 New plan "${name}" added successfully!`, 'success');
    }
    
    //console.log('Plans array after operation:', plansData);
    //console.log('Array length:', plansData.length);
    
    clearForm();
    updateTextarea();
    updatePreview();
}

// Edit existing plan
function editPlan(index) {
    //console.log('=== EDIT PLAN CALLED ===');
    //console.log('Index:', index, 'Total plans:', plansData.length);
    
    // Validate index
    if (typeof index !== 'number' || index < 0 || index >= plansData.length) {
        //console.error('Invalid plan index:', index);
        showPricingNotification('❌ Invalid plan selected!', 'error');
        return;
    }
    
    const plan = plansData[index];
    if (!plan) {
        //console.error('Plan not found at index:', index);
        showPricingNotification('❌ Plan not found!', 'error');
        return;
    }
    
    //console.log('Editing plan:', plan);
    
    // Set editing mode
    editingPlanIndex = index;
    
    // Populate form with plan data
    document.getElementById('new-plan-name').value = plan.name || '';
    document.getElementById('new-plan-price').value = plan.price || '';
    document.getElementById('new-plan-period').value = plan.period || '/month';
    document.getElementById('new-plan-description').value = plan.description || '';
    document.getElementById('mark-popular').checked = plan.popular || false; // ✅ FIXED: Properly set popular checkbox
    
    // Clear existing features and populate with plan's features
    const featuresContainer = document.getElementById('features-list');
    featuresContainer.innerHTML = '';
    
    if (!plan.features || plan.features.length === 0) {
        // Add one empty feature input if no features
        addFeatureInput();
    } else {
        // Add feature inputs for each existing feature
        plan.features.forEach(feature => {
            const featureDiv = document.createElement('div');
            featureDiv.className = 'flex gap-2 feature-row';
            featureDiv.innerHTML = `
                <input type="text" value="${feature}" 
                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm feature-input">
                <button type="button" onclick="removeFeatureInput(this)" 
                        class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition duration-200">
                    <i class="fas fa-times"></i>
                </button>
            `;
            featuresContainer.appendChild(featureDiv);
            
            // Add event listener for live preview
            featureDiv.querySelector('.feature-input').addEventListener('input', updateLivePreview);
        });
    }
    
    // Update UI for editing mode
    updateFormUIForEditing();
    
    // Update preview with highlighting
    updatePreview();
    
    // Show live preview with current data
    updateLivePreview();
    
    // Scroll to form
    document.querySelector('.bg-white.border-2.border-gray-300').scrollIntoView({ behavior: 'smooth' });
    
    showPricingNotification(`✏️ Now editing: "${plan.name}"`, 'info');
    
    //console.log('Edit setup complete');
}

// Update form UI for editing mode
function updateFormUIForEditing() {
    // Update header
    const formHeader = document.getElementById('form-header');
    if (formHeader) {
        formHeader.innerHTML = '<i class="fas fa-edit mr-2 text-orange-600"></i>Edit Pricing Plan';
    }
    
    const formSubtitle = document.getElementById('form-subtitle');
    if (formSubtitle) {
        formSubtitle.textContent = 'Modify the plan details below';
    }
    
    // Update main button
    const mainBtn = document.getElementById('main-action-btn');
    if (mainBtn) {
        mainBtn.innerHTML = '<i class="fas fa-save mr-2"></i>💾 Update Plan';
        mainBtn.className = mainBtn.className.replace('bg-blue-500', 'bg-green-500').replace('hover:bg-blue-600', 'hover:bg-green-600');
    }
    
    // Show cancel button
    const cancelBtn = document.getElementById('cancel-edit-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'inline-block';
    }
}

// Clear form and reset to add mode
function clearForm() {
    //console.log('=== CLEARING FORM ===');
    
    // Clear all input fields
    document.getElementById('new-plan-name').value = '';
    document.getElementById('new-plan-price').value = '';
    document.getElementById('new-plan-period').value = '/month';
    document.getElementById('new-plan-description').value = '';
    document.getElementById('mark-popular').checked = false;
    
    // Reset features to one empty input
    const featuresContainer = document.getElementById('features-list');
    featuresContainer.innerHTML = `
        <div class="flex gap-2 feature-row">
            <input type="text" placeholder="Feature description (e.g., 24/7 Support)" 
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm feature-input">
            <button type="button" onclick="removeFeatureInput(this)" 
                    class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition duration-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add event listener to the new feature input
    const newFeatureInput = featuresContainer.querySelector('.feature-input');
    if (newFeatureInput) {
        newFeatureInput.addEventListener('input', updateLivePreview);
    }
    
    // Reset editing state
    editingPlanIndex = -1;
    
    // Reset form UI
    resetFormUIToAddMode();
    
    // Remove live preview
    removeLivePreview();
    
    // Update preview to remove editing highlights
    updatePreview();
    
    //console.log('Form cleared successfully');
}

// Reset form UI to add mode
function resetFormUIToAddMode() {
    // Reset header
    const formHeader = document.getElementById('form-header');
    if (formHeader) {
        formHeader.innerHTML = '<i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New Pricing Plan';
    }
    
    const formSubtitle = document.getElementById('form-subtitle');
    if (formSubtitle) {
        formSubtitle.textContent = 'Fill out the form below to create a new plan';
    }
    
    // Reset main button
    const mainBtn = document.getElementById('main-action-btn');
    if (mainBtn) {
        mainBtn.innerHTML = '<i class="fas fa-plus mr-2"></i>➕ Add Plan';
        mainBtn.className = mainBtn.className.replace('bg-green-500', 'bg-blue-500').replace('hover:bg-green-600', 'hover:bg-blue-600');
    }
    
    // Hide cancel button
    const cancelBtn = document.getElementById('cancel-edit-btn');
    if (cancelBtn) {
        cancelBtn.style.display = 'none';
    }
}

// Cancel edit mode
function cancelEdit() {
    //console.log('=== CANCEL EDIT CALLED ===');
    clearForm();
    showPricingNotification('✖️ Edit cancelled', 'info');
}

// Copy/duplicate plan
function copyPlan(index) {
    //console.log('=== COPY PLAN CALLED ===');
    
    if (index < 0 || index >= plansData.length) {
        showPricingNotification('❌ Invalid plan selected!', 'error');
        return;
    }
    
    const originalPlan = plansData[index];
    const copiedPlan = {
        ...originalPlan,
        name: originalPlan.name + ' (Copy)',
        popular: false // Don't copy popular status
    };
    
    plansData.splice(index + 1, 0, copiedPlan);
    updateTextarea();
    updatePreview();
    
    showPricingNotification(`📋 Plan "${originalPlan.name}" copied successfully!`, 'success');
}

// Remove plan
function removePlan(index) {
    //console.log('=== REMOVE PLAN CALLED ===');
    
    if (index < 0 || index >= plansData.length) {
        showPricingNotification('❌ Invalid plan selected!', 'error');
        return;
    }
    
    const planName = plansData[index].name;
    
    if (confirm(`🗑️ Are you sure you want to delete "${planName}"?\n\nThis action cannot be undone.`)) {
        plansData.splice(index, 1);
        
        // Handle editing state
        if (editingPlanIndex === index) {
            clearForm();
        } else if (editingPlanIndex > index) {
            editingPlanIndex--;
        }
        
        updateTextarea();
        updatePreview();
        
        showPricingNotification(`🗑️ Plan "${planName}" deleted successfully!`, 'success');
    }
}

// Clear all plans
function clearAllPlans() {
    if (confirm('🚨 Are you sure you want to delete ALL plans?\n\nThis action cannot be undone.')) {
        plansData = [];
        clearForm();
        updateTextarea();
        updatePreview();
        
        showPricingNotification('🗑️ All plans cleared!', 'success');
    }
}

// ✅ FIXED: Update textarea with plans data including popular status
function updateTextarea() {
    try {
        const contentTextarea = document.getElementById('content');
        if (!contentTextarea) return;
        
        const formatted = plansData.map(plan => {
            // Create the plan string: Name|Price|Period|Description|Popular|Feature1|Feature2|...
            const parts = [
                plan.name || '',
                plan.price || '',
                plan.period || '/month',
                plan.description || '',
                plan.popular ? '1' : '0' // ✅ FIXED: Include popular status as 1/0
            ];
            
            // Add features
            if (plan.features && plan.features.length > 0) {
                parts.push(...plan.features);
            }
            
            return parts.join('|');
        }).join('||');
        
        //console.log('Updating textarea with formatted content:', formatted);
        
        // Mark as form submitting to prevent reload
        isFormSubmitting = true;
        contentTextarea.value = formatted;
        
        // Trigger change event
        const event = new Event('input', { bubbles: true });
        contentTextarea.dispatchEvent(event);
        
        updatePlanCount();
        
        // Reset flag after delay
        setTimeout(() => {
            isFormSubmitting = false;
        }, 100);
        
    } catch (error) {
        //console.error('Error updating textarea:', error);
        isFormSubmitting = false;
    }
}

// Generate plan HTML
function generatePlanHTML(plan, index, isPreview = false) {
    // Format price display
    let displayPrice = plan.price;
    if (displayPrice && !displayPrice.includes('$') && !displayPrice.includes('€') && !displayPrice.includes('£') && displayPrice !== 'Free' && displayPrice !== 'Custom') {
        displayPrice = '$' + displayPrice;
    }

    const isEditing = editingPlanIndex === index;
    const editingClass = isEditing ? 'ring-2 ring-orange-400 border-orange-300' : 'border-gray-200';
    
    return `
        <div class="bg-white rounded-lg shadow-md p-6 relative border ${editingClass} ${plan.popular ? 'ring-2 ring-blue-500 border-blue-200' : ''}" 
             ${!isPreview ? `data-plan-index="${index}"` : ''}>
            ${isEditing && !isPreview ? `
                <div class="absolute -top-2 -right-2 bg-orange-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold animate-pulse z-10">
                    ✏️
                </div>
            ` : ''}
            
            ${plan.popular ? '<div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-1 rounded-full text-xs font-medium shadow-lg">Most Popular</div>' : ''}
            
            <div class="text-center mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-2">${plan.name || 'Plan Name'}</h3>
                <div class="mb-3">
                    <span class="text-4xl font-bold text-blue-600">${displayPrice || '$0'}</span>
                    <span class="text-gray-600 text-lg">${plan.period || '/month'}</span>
                </div>
                ${plan.description ? `<p class="text-gray-600 text-sm italic">${plan.description}</p>` : ''}
            </div>

            ${plan.features && plan.features.length > 0 ? `
            <ul class="space-y-3 mb-6 min-h-[120px]">
                ${plan.features.map(feature => `
                    <li class="flex items-start text-sm">
                        <svg class="w-4 h-4 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-gray-700">${feature}</span>
                    </li>
                `).join('')}
            </ul>
            ` : '<div class="mb-6 min-h-[120px] flex items-center justify-center text-gray-400 text-sm">No features added</div>'}

            <div class="space-y-2">
                <button class="w-full bg-blue-500 text-white py-3 px-4 rounded-lg hover:bg-blue-600 text-sm font-medium transition-colors">
                    Choose Plan
                </button>
                ${!isPreview ? `
                <div class="flex gap-2">
                    <button onclick="editPlan(${index})" 
                            class="flex-1 bg-yellow-500 text-white py-2 px-3 rounded-lg hover:bg-yellow-600 text-xs font-medium transition-colors">
                        ✏️ Edit
                    </button>
                    <button onclick="copyPlan(${index})" 
                            class="flex-1 bg-green-500 text-white py-2 px-3 rounded-lg hover:bg-green-600 text-xs font-medium transition-colors">
                        📋 Copy
                    </button>
                    <button onclick="removePlan(${index})" 
                            class="flex-1 bg-red-500 text-white py-2 px-3 rounded-lg hover:bg-red-600 text-xs font-medium transition-colors">
                        🗑️ Remove
                    </button>
                </div>
                ` : ''}
            </div>
        </div>
    `;
}

// Update preview display
function updatePreview() {
    const container = document.getElementById('plans-display');
    if (!container) return;
    
    //console.log('Updating preview with', plansData.length, 'plans');
    
    if (plansData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center col-span-full">No plans added yet. Add your first plan above.</p>';
        updatePlanCount();
        return;
    }

    container.innerHTML = plansData.map((plan, index) => generatePlanHTML(plan, index)).join('');
    updatePlanCount();
}

// Show notification
function showPricingNotification(message, type = 'success') {
    // Remove existing notification
    const existing = document.querySelector('.pricing-notification');
    if (existing) existing.remove();
    
    const colors = {
        success: 'bg-green-500 text-white',
        error: 'bg-red-500 text-white',
        info: 'bg-blue-500 text-white',
        warning: 'bg-yellow-500 text-black'
    };
    
    const notification = document.createElement('div');
    notification.className = `pricing-notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${colors[type] || colors.success}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-lg hover:opacity-75">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 4000);
}

// ✅ FIXED: Added debug function back
// window.debugPricing = function() {
//     console.log('=== PRICING DEBUG INFO ===');
//     console.log('Plans Data:', plansData);
//     console.log('Editing Index:', editingPlanIndex);
//     console.log('Is Form Submitting:', isFormSubmitting);
//     console.log('Textarea Content:', document.getElementById('content')?.value);
//     console.log('=== END DEBUG INFO ===');
// };

// Export functions for external use
window.pricingManager = {
    addPlan: addNewPlan,
    editPlan: editPlan,
    removePlan: removePlan,
    copyPlan: copyPlan,
    clearAll: clearAllPlans,
    getData: () => plansData,
    getCount: () => plansData.length,
    debug: window.debugPricing
};

//console.log('💰 Enhanced Pricing Management Script Loaded Successfully!');

// Add CSS for animations
const pricingCSS = `
<style>
.pricing-notification {
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

[data-plan-index] {
    transition: all 0.2s ease;
    position: relative;
}

[data-plan-index]:hover {
    transform: translateY(-2px);
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.feature-row {
    transition: all 0.2s ease;
}

.feature-row:hover {
    background-color: #f9fafb;
    border-radius: 0.375rem;
    padding: 0.25rem;
    margin: -0.25rem;
}

/* Form validation styles */
.border-red-300 {
    border-color: #fca5a5;
}

.border-green-300 {
    border-color: #86efac;
}

/* Loading state */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    #plans-display {
        grid-template-columns: 1fr;
    }
    
    .feature-row {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .feature-row button {
        align-self: flex-end;
        width: auto;
    }
}
</style>
`;

// Inject CSS
if (!document.querySelector('#pricing-section-styles')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'pricing-section-styles';
    styleElement.textContent = pricingCSS.replace(/<\/?style>/g, '');
    document.head.appendChild(styleElement);
}

// ===== FAQ SECTION FUNCTIONS =====
        
        let faqData = [];
        let editingFAQIndex = -1;

        // Initialize FAQ manager when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('faq-preview')) {
                //console.log('=== FAQ SECTION LOADED ===');
                loadExistingFAQs();
                updateFAQPreview();
                setupFAQFormListeners();
            }
        });

        // Load existing FAQs from textarea
        function loadExistingFAQs() {
            const contentTextarea = document.getElementById('content');
            if (!contentTextarea) {
                //console.error('Content textarea not found');
                return;
            }
            
            const content = contentTextarea.value.trim();
            faqData = [];
            
            //console.log('Loading FAQs from content:', content);
            
            if (content) {
                const faqs = content.split('||');
                faqData = faqs.map(faq => {
                    const parts = faq.split('|').map(part => part.trim());
                    if (parts.length >= 2) {
                        return {
                            question: parts[0] || '',
                            answer: parts[1] || '',
                            category: parts[2] || ''
                        };
                    }
                    return null;
                }).filter(faq => faq && faq.question);
            }
            
            //console.log('Loaded FAQs:', faqData);
            updateFAQCount();
        }

        // Update FAQ count display
        function updateFAQCount() {
            const faqCountElement = document.getElementById('faq-count');
            if (faqCountElement) {
                faqCountElement.textContent = faqData.length;
            }
            
            const textareaLengthElement = document.getElementById('faq-textarea-length');
            if (textareaLengthElement) {
                const content = document.getElementById('content').value;
                textareaLengthElement.textContent = content.length;
            }
        }

        // Update FAQ preview display
        function updateFAQPreview() {
            const container = document.getElementById('faq-display');
            if (!container) return;
            
            if (faqData.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-8">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-question-circle text-4xl"></i>
                        </div>
                        <p class="text-gray-500 text-lg mb-2">No FAQs yet</p>
                        <p class="text-gray-400 text-sm">Add your first FAQ using the form below</p>
                    </div>
                `;
                updateFAQCount();
                return;
            }

            // Group FAQs by category if categories exist
            const categorizedFAQs = {};
            const uncategorized = [];
            
            faqData.forEach((faq, index) => {
                faq.index = index; // Add index for editing
                if (faq.category && faq.category.trim()) {
                    if (!categorizedFAQs[faq.category]) {
                        categorizedFAQs[faq.category] = [];
                    }
                    categorizedFAQs[faq.category].push(faq);
                } else {
                    uncategorized.push(faq);
                }
            });

            let html = '';

            // Display categorized FAQs
            Object.keys(categorizedFAQs).forEach(category => {
                html += `
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-folder text-blue-600 mr-2"></i>${category}
                        </h4>
                        <div class="space-y-3">
                            ${categorizedFAQs[category].map(faq => generateFAQHTML(faq)).join('')}
                        </div>
                    </div>
                `;
            });

            // Display uncategorized FAQs
            if (uncategorized.length > 0) {
                if (Object.keys(categorizedFAQs).length > 0) {
                    html += `
                        <div class="mb-6">
                            <h4 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-question text-gray-600 mr-2"></i>General Questions
                            </h4>
                            <div class="space-y-3">
                                ${uncategorized.map(faq => generateFAQHTML(faq)).join('')}
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="space-y-3">
                            ${uncategorized.map(faq => generateFAQHTML(faq)).join('')}
                        </div>
                    `;
                }
            }

            container.innerHTML = html;
            updateFAQCount();
        }

        // Generate HTML for a single FAQ
        function generateFAQHTML(faq) {
            const isEditing = editingFAQIndex === faq.index;
            const editingClass = isEditing ? 'ring-2 ring-orange-400 border-orange-300' : 'border-gray-200';
            
            return `
                <div class="bg-white rounded-lg border ${editingClass} shadow-sm hover:shadow-md transition-shadow duration-200" data-faq-index="${faq.index}">
                    ${isEditing ? `
                        <div class="absolute -top-2 -right-2 bg-orange-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold animate-pulse">
                            ✏️
                        </div>
                    ` : ''}
                    
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h5 class="text-sm font-semibold text-gray-900 mb-2 flex items-start">
                                    <i class="fas fa-question-circle text-blue-600 mr-2 mt-0.5"></i>
                                    ${faq.question}
                                </h5>
                                <p class="text-sm text-gray-700 leading-relaxed mb-3">${faq.answer}</p>
                                ${faq.category ? `<span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">${faq.category}</span>` : ''}
                            </div>
                        </div>
                        
                        <div class="flex gap-2 mt-3 pt-3 border-t border-gray-100">
                            <button onclick="editFAQ(${faq.index})" 
                                    class="flex-1 bg-yellow-500 text-white py-2 px-3 rounded-lg hover:bg-yellow-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-edit mr-1"></i> Edit
                            </button>
                            <button onclick="duplicateFAQ(${faq.index})" 
                                    class="flex-1 bg-green-500 text-white py-2 px-3 rounded-lg hover:bg-green-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-copy mr-1"></i> Copy
                            </button>
                            <button onclick="moveFAQ(${faq.index}, -1)" 
                                    class="bg-gray-500 text-white py-2 px-3 rounded-lg hover:bg-gray-600 transition-colors duration-200 text-xs font-medium" 
                                    ${faq.index === 0 ? 'disabled' : ''}>
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button onclick="moveFAQ(${faq.index}, 1)" 
                                    class="bg-gray-500 text-white py-2 px-3 rounded-lg hover:bg-gray-600 transition-colors duration-200 text-xs font-medium" 
                                    ${faq.index === faqData.length - 1 ? 'disabled' : ''}>
                                <i class="fas fa-arrow-down"></i>
                            </button>
                            <button onclick="removeFAQ(${faq.index})" 
                                    class="bg-red-500 text-white py-2 px-3 rounded-lg hover:bg-red-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // EDIT FAQ FUNCTION
        function editFAQ(index) {
            // console.log('=== EDIT FAQ CLICKED ===');
            // console.log('Index:', index);
            // console.log('FAQ data:', faqData[index]);
            
            try {
                // Validate input
                if (!faqData || !faqData[index]) {
                    console.error('FAQ not found at index:', index);
                    showFAQNotification('❌ FAQ not found!', 'error');
                    return;
                }
                
                const faq = faqData[index];
                editingFAQIndex = index;
                
                // console.log('Editing FAQ:', faq);
                
                // Get form elements
                const questionField = document.getElementById('new-faq-question');
                const answerField = document.getElementById('new-faq-answer');
                const categoryField = document.getElementById('new-faq-category');
                
                // Check if all elements exist
                if (!questionField || !answerField || !categoryField) {
                    console.error('Missing form elements:', {
                        question: !!questionField,
                        answer: !!answerField,
                        category: !!categoryField
                    });
                    showFAQNotification('❌ Form elements not found!', 'error');
                    return;
                }
                
                // Clear and populate form fields
                questionField.value = faq.question || '';
                answerField.value = faq.answer || '';
                categoryField.value = faq.category || '';
                
                // console.log('Form populated with:', {
                //     question: questionField.value,
                //     answer: answerField.value,
                //     category: categoryField.value
                // });
                
                // Update UI to show editing mode
                updateFAQFormUIForEditing();
                
                // Scroll to form
                scrollToFAQForm();
                
                // Update preview with highlighting
                updateFAQPreview();
                
                // Show success notification
                showFAQNotification(`✏️ Now editing FAQ: "${faq.question}"`, 'info');
                
                // console.log('FAQ edit setup completed successfully');
                
            } catch (error) {
                console.error('Error in editFAQ:', error);
                showFAQNotification('❌ Error: ' + error.message, 'error');
            }
        }

        // Update form UI for editing mode
        function updateFAQFormUIForEditing() {
            // Update the main button
            const addButton = document.querySelector('button[onclick="addNewFAQ()"]');
            if (addButton) {
                addButton.innerHTML = '<i class="fas fa-save mr-2"></i>💾 Update FAQ';
                addButton.className = addButton.className.replace('bg-blue-500', 'bg-green-500').replace('hover:bg-blue-600', 'hover:bg-green-600');
            }
            
            // Show cancel button
            const cancelButton = document.querySelector('button[onclick="cancelFAQEdit()"]');
            if (cancelButton) {
                cancelButton.style.display = 'inline-block';
            }
            
            // Update form header
            const formHeaders = document.querySelectorAll('h4');
            formHeaders.forEach(header => {
                if (header.textContent.includes('Add New FAQ')) {
                    header.innerHTML = '<i class="fas fa-edit mr-2 text-orange-600"></i>✏️ Edit FAQ';
                }
            });
        }

        // Scroll to form
        function scrollToFAQForm() {
            const formElement = document.querySelector('.bg-white.border-2.border-gray-300');
            if (formElement) {
                formElement.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
                
                // Add temporary highlight
                formElement.style.boxShadow = '0 0 20px rgba(59, 130, 246, 0.3)';
                setTimeout(() => {
                    formElement.style.boxShadow = '';
                }, 2000);
            }
        }

        // Add new FAQ or update existing
        function addNewFAQ() {
            const question = document.getElementById('new-faq-question').value.trim();
            const answer = document.getElementById('new-faq-answer').value.trim();
            const category = document.getElementById('new-faq-category').value.trim();
            
            // Validation
            if (!question) {
                showFAQNotification('❌ Please enter a question.', 'error');
                document.getElementById('new-faq-question').focus();
                return;
            }
            
            if (!answer) {
                showFAQNotification('❌ Please enter an answer.', 'error');
                document.getElementById('new-faq-answer').focus();
                return;
            }

            const faqDataItem = {
                question: question,
                answer: answer,
                category: category
            };

            if (editingFAQIndex >= 0) {
                // Update existing FAQ
                const oldQuestion = faqData[editingFAQIndex].question;
                faqData[editingFAQIndex] = faqDataItem;
                
                // console.log('FAQ updated:', faqDataItem);
                showFAQNotification(`✅ FAQ updated successfully!`, 'success');
            } else {
                // Add new FAQ
                faqData.push(faqDataItem);
                showFAQNotification(`🎉 New FAQ "${question}" added successfully!`, 'success');
            }
            
            // Reset form and update
            clearFAQForm();
            updateFAQTextarea();
            updateFAQPreview();
        }

        // Clear FAQ form
        function clearFAQForm() {
            // Clear form fields
            const fields = ['new-faq-question', 'new-faq-answer', 'new-faq-category'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) field.value = '';
            });
            
            // Reset form UI
            const addButton = document.querySelector('button[onclick="addNewFAQ()"]');
            if (addButton) {
                addButton.innerHTML = '<i class="fas fa-plus mr-2"></i>➕ Add FAQ';
                addButton.className = addButton.className.replace('bg-green-500', 'bg-blue-500').replace('hover:bg-green-600', 'hover:bg-blue-600');
            }
            
            // Hide cancel button
            const cancelButton = document.querySelector('button[onclick="cancelFAQEdit()"]');
            if (cancelButton) {
                cancelButton.style.display = 'none';
            }
            
            // Reset form header
            const formHeaders = document.querySelectorAll('h4');
            formHeaders.forEach(header => {
                if (header.textContent.includes('Edit FAQ')) {
                    header.innerHTML = '<i class="fas fa-plus-circle mr-2 text-green-600"></i>Add New FAQ';
                }
            });
            
            editingFAQIndex = -1;
            
            // Reset preview highlighting
            updateFAQPreview();
        }

        // Cancel FAQ edit
        function cancelFAQEdit() {
            clearFAQForm();
            showFAQNotification('✖️ Edit cancelled', 'info');
        }

        // Duplicate FAQ
        function duplicateFAQ(index) {
            const faq = { ...faqData[index] };
            faq.question = faq.question + ' (Copy)';
            
            faqData.splice(index + 1, 0, faq);
            updateFAQTextarea();
            updateFAQPreview();
            
            showFAQNotification('📋 FAQ duplicated successfully!', 'success');
        }

        // Remove FAQ
        function removeFAQ(index) {
            const faqQuestion = faqData[index].question;
            if (confirm(`🗑️ Are you sure you want to delete this FAQ?\n\n"${faqQuestion}"`)) {
                faqData.splice(index, 1);
                updateFAQTextarea();
                updateFAQPreview();
                
                showFAQNotification(`🗑️ FAQ deleted!`, 'success');
                
                // If we were editing this FAQ, clear the form
                if (editingFAQIndex === index) {
                    clearFAQForm();
                } else if (editingFAQIndex > index) {
                    editingFAQIndex--;
                }
            }
        }

        // Move FAQ up or down
        function moveFAQ(index, direction) {
            const newIndex = index + direction;
            if (newIndex >= 0 && newIndex < faqData.length) {
                [faqData[index], faqData[newIndex]] = [faqData[newIndex], faqData[index]];
                
                // Update editing index if necessary
                if (editingFAQIndex === index) {
                    editingFAQIndex = newIndex;
                } else if (editingFAQIndex === newIndex) {
                    editingFAQIndex = index;
                }
                
                updateFAQTextarea();
                updateFAQPreview();
            }
        }

        // Clear all FAQs
        function clearAllFAQs() {
            if (confirm('🚨 Are you sure you want to delete ALL FAQs?')) {
                faqData = [];
                clearFAQForm();
                updateFAQTextarea();
                updateFAQPreview();
                
                showFAQNotification('🗑️ All FAQs cleared!', 'success');
            }
        }

        // Update textarea with FAQ data
// FIXED: Update textarea with FAQ data - Proper || separator handling
function updateFAQTextarea() {
    try {
        const contentTextarea = document.getElementById('content');
        if (!contentTextarea) {
            //console.error('❌ Content textarea not found during update');
            return;
        }
        
        // FIXED: Proper formatting with || separator between FAQs
        const formatted = faqData.map(faq => {
            // Only include category if it exists and is not empty
            if (faq.category && faq.category.trim()) {
                return `${faq.question}|${faq.answer}|${faq.category}`;
            } else {
                return `${faq.question}|${faq.answer}`;
            }
        }).join('||'); // Use || to separate different FAQs
        
        // console.log('💾 Updating textarea with:', formatted);
        contentTextarea.value = formatted;
        
        // Trigger change event for any listeners
        const event = new Event('input', { bubbles: true });
        contentTextarea.dispatchEvent(event);
        
        updateFAQCount();
        
    } catch (error) {
        //console.error('❌ Error updating textarea:', error);
    }
}

// FIXED: Load existing FAQs from textarea - Handle both || and ||| separators
function loadExistingFAQs() {
    const contentTextarea = document.getElementById('content');
    if (!contentTextarea) {
        //console.error('❌ Content textarea not found');
        return;
    }
    
    let content = contentTextarea.value.trim();
    faqData = [];
    
    //console.log('📥 Loading FAQs from content:', content);
    
    if (content) {
        // FIXED: Handle both ||| and || separators for backward compatibility
        // First, normalize triple pipes to double pipes
        content = content.replace(/\|\|\|/g, '||');
        
        // Split by || for multiple FAQs
        const faqs = content.split('||');
        faqData = faqs.map((faq, index) => {
            const parts = faq.split('|').map(part => part.trim());
            if (parts.length >= 2 && parts[0] && parts[1]) {
                return {
                    question: parts[0],
                    answer: parts[1],
                    category: parts[2] || '' // Category is optional
                };
            }
            return null;
        }).filter(faq => faq !== null);
    }
    
    //console.log('✅ Loaded FAQs:', faqData.length, faqData);
    updateFAQCount();
}

// UTILITY FUNCTION: Clean up existing content with triple pipes
function cleanupFAQContent() {
    const contentTextarea = document.getElementById('content');
    if (contentTextarea && contentTextarea.value) {
        // Replace ||| with || in existing content
        let content = contentTextarea.value;
        const originalContent = content;
        
        // Replace multiple consecutive pipes with double pipes
        content = content.replace(/\|\|\|+/g, '||');
        
        if (content !== originalContent) {
            contentTextarea.value = content;
            // console.log('🧹 Cleaned up FAQ content - removed extra pipes');
            showFAQNotification('🧹 Fixed pipe separators in existing content', 'info');
        }
    }
}

// ENHANCED: Add new FAQ with proper validation and formatting
function addNewFAQ() {
    // console.log('🚀 ADD NEW FAQ FUNCTION CALLED');
    
    try {
        // Get form values
        const question = document.getElementById('new-faq-question')?.value?.trim() || '';
        const answer = document.getElementById('new-faq-answer')?.value?.trim() || '';
        const category = document.getElementById('new-faq-category')?.value?.trim() || '';
        
        // console.log('📝 Form values:', { question, answer, category });
        
        // Validation
        if (!question) {
            showFAQNotification('❌ Please enter a question.', 'error');
            document.getElementById('new-faq-question')?.focus();
            return false;
        }
        
        if (!answer) {
            showFAQNotification('❌ Please enter an answer.', 'error');
            document.getElementById('new-faq-answer')?.focus();
            return false;
        }

        // FIXED: Clean the inputs to prevent pipe issues
        const faqItem = {
            question: question.replace(/\|/g, '').trim(), // Remove any pipes from question
            answer: answer.replace(/\|/g, '').trim(),     // Remove any pipes from answer
            category: category.replace(/\|/g, '').trim()  // Remove any pipes from category
        };

        if (editingFAQIndex >= 0 && editingFAQIndex < faqData.length) {
            // Update existing FAQ
            const oldQuestion = faqData[editingFAQIndex].question;
            faqData[editingFAQIndex] = faqItem;
            // console.log('✏️ FAQ updated:', faqItem);
            showFAQNotification(`✅ FAQ "${oldQuestion}" updated successfully!`, 'success');
        } else {
            // Add new FAQ
            faqData.push(faqItem);
            console.log('➕ New FAQ added:', faqItem);
            showFAQNotification(`🎉 New FAQ "${question}" added successfully!`, 'success');
        }
        
        // Update everything
        updateFAQTextarea();
        updateFAQPreview();
        clearFAQForm();
        
        // console.log('✅ FAQ operation completed successfully');
        // console.log('📊 Current FAQ data:', faqData);
        // console.log('📄 Current textarea content:', document.getElementById('content')?.value);
        
        return true;
        
    } catch (error) {
        console.error('❌ Error in addNewFAQ:', error);
        showFAQNotification('❌ Error: ' + error.message, 'error');
        return false;
    }
}

// UTILITY FUNCTION: Fix existing content on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-fix existing content when page loads
    setTimeout(() => {
        if (document.getElementById('content') && document.getElementById('faq-preview')) {
            cleanupFAQContent();
            loadExistingFAQs();
            updateFAQPreview();
        }
    }, 1000);
});

// DEBUG FUNCTION: Show current FAQ format
function debugFAQFormat() {
    const content = document.getElementById('content')?.value || '';
    // console.log('=== FAQ FORMAT DEBUG ===');
    // console.log('Raw content:', JSON.stringify(content));
    // console.log('Content with pipes highlighted:', content.replace(/\|/g, '[PIPE]'));
    // console.log('FAQ Data:', faqData);
    
    // Show expected vs actual format
    const expected = faqData.map(faq => {
        if (faq.category && faq.category.trim()) {
            return `${faq.question}|${faq.answer}|${faq.category}`;
        } else {
            return `${faq.question}|${faq.answer}`;
        }
    }).join('||');
    
    // console.log('Expected format:', expected);
    // console.log('Actual format:', content);
    // console.log('Formats match:', expected === content);
    // console.log('=======================');
    
    // Show in notification too
    showFAQNotification(`📊 Debug info logged to console. FAQs: ${faqData.length}`, 'info');
}

        // Setup FAQ form listeners
        function setupFAQFormListeners() {
            const formInputs = ['new-faq-question', 'new-faq-answer', 'new-faq-category'];
            formInputs.forEach(inputId => {
                const element = document.getElementById(inputId);
                if (element) {
                    element.addEventListener('input', function() {
                        //console.log(`${inputId} changed to:`, this.value);
                    });
                }
            });
        }

        // Show FAQ notification
        function showFAQNotification(message, type = 'success') {
            // Remove existing notification
            const existing = document.querySelector('.faq-notification');
            if (existing) existing.remove();
            
            const colors = {
                success: 'bg-green-500 text-white',
                error: 'bg-red-500 text-white',
                info: 'bg-blue-500 text-white',
                warning: 'bg-yellow-500 text-black'
            };
            
            const notification = document.createElement('div');
            notification.className = `faq-notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${colors[type] || colors.success}`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-lg hover:opacity-75">×</button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 4000);
        }

        // Monitor textarea changes for external edits
        document.addEventListener('DOMContentLoaded', function() {
            const contentTextarea = document.getElementById('content');
            if (contentTextarea && document.getElementById('faq-preview')) {
                contentTextarea.addEventListener('input', function() {
                    loadExistingFAQs();
                    updateFAQPreview();
                });
            }
        });

// ===== FIXED TEAM SECTION JAVASCRIPT =====

// FIXED: Remove duplicate imageField declaration and global variables
let teamData = [];
let editingMemberIndex = -1;
let currentMemberImageTarget = null;

// FIXED: Add safe element getter
function safeGetElement(id) {
    try {
        return document.getElementById(id);
    } catch (error) {
        console.warn(`Element '${id}' not found:`, error);
        return null;
    }
}

// FIXED: Better error handling in setupTeamFormListeners
function setupTeamFormListeners() {
    //console.log('Setting up team form listeners...');
    
    // FIXED: Check if we're actually on a team section first
    if (!safeGetElement('team-preview')) {
        //console.log('Team section not found, skipping team form listeners');
        return;
    }
    
    // FIXED: Safe element access with null check
    const imageField = safeGetElement('new-member-image');
    
    if (imageField) {
        //console.log('Setting up image field listener');
        try {
            imageField.addEventListener('input', function() {
                const inputValue = this.value.trim();
                if (inputValue) {
                    // Clean the URL and update field to show relative path
                    const cleanedPath = removeBaseUrl(inputValue);
                    if (this.value !== cleanedPath) {
                        this.value = cleanedPath;
                    }
                    // Use full URL for preview
                    updateMemberImagePreview(addBaseUrl(cleanedPath));
                } else {
                    clearMemberImagePreview();
                }
                
                // Update textarea if we're editing a member
                if (editingMemberIndex >= 0) {
                    updateEditingMemberInTextarea();
                }
            });
        } catch (error) {
            //console.error('Error adding image field listener:', error);
        }
    } else {
        //console.warn('Team member image field not found - skipping image setup');
    }
    
    // FIXED: Setup other team form listeners with better error handling
    const formInputs = ['new-member-name', 'new-member-position', 'new-member-bio'];
    formInputs.forEach(inputId => {
        const element = safeGetElement(inputId);
        if (element) {
            //console.log(`Setting up listener for ${inputId}`);
            try {
                element.addEventListener('input', function() {
                    //console.log(`${inputId} changed to:`, this.value);
                    
                    // Update textarea if we're editing a member
                    if (editingMemberIndex >= 0) {
                        updateEditingMemberInTextarea();
                    }
                });
            } catch (error) {
                //console.error(`Error setting up ${inputId} listener:`, error);
            }
        } else {
            //console.warn(`Form input '${inputId}' not found - skipping`);
        }
    });
    
    //console.log('Team form listeners setup completed');
}

// Add this new function too
function updateEditingMemberInTextarea() {
    if (editingMemberIndex < 0 || !teamData[editingMemberIndex]) return;
    
    // FIXED: Safe element access
    const nameEl = safeGetElement('new-member-name');
    const positionEl = safeGetElement('new-member-position');
    const bioEl = safeGetElement('new-member-bio');
    const imageEl = safeGetElement('new-member-image');
    
    // Get current form values safely
    const name = nameEl ? nameEl.value.trim() : '';
    const position = positionEl ? positionEl.value.trim() : '';
    const bio = bioEl ? bioEl.value.trim() : '';
    const image = imageEl ? imageEl.value.trim() : '';
    
    // Update the team data array
    teamData[editingMemberIndex] = {
        name: name || teamData[editingMemberIndex].name,
        position: position || teamData[editingMemberIndex].position,
        bio: bio || teamData[editingMemberIndex].bio,
        image: removeBaseUrl(image) || teamData[editingMemberIndex].image
    };
    
    // Update textarea and preview
    updateTeamTextarea();
    updateTeamPreview();
}

// FIXED: Single DOMContentLoaded handler with proper error handling
document.addEventListener('DOMContentLoaded', function() {
    //console.log('DOM loaded, checking for team section...');
    
    // FIXED: Only initialize if team elements exist
    if (safeGetElement('team-preview')) {
        //console.log('=== TEAM SECTION LOADED ===');
        try {
            loadExistingTeamMembers();
            updateTeamPreview();
            setupTeamFormListeners();
            //console.log('Team section initialized successfully');
        } catch (error) {
            //console.error('Error initializing team section:', error);
        }
    } else {
        //console.log('Team section not found - skipping initialization');
    }
});

// Load existing team members from textarea
function loadExistingTeamMembers() {
    const contentTextarea = safeGetElement('content');
    if (!contentTextarea) {
        console.error('Content textarea not found');
        return;
    }
    
    const content = contentTextarea.value.trim();
    teamData = [];
    
    //console.log('Loading team members from content:', content);
    
    if (content) {
        const members = content.split('||');
        teamData = members.map(member => {
            const parts = member.split('|').map(part => part.trim());
            if (parts.length >= 3) {
                return {
                    name: parts[0] || '',
                    position: parts[1] || '',
                    bio: parts[2] || '',
                    image: parts[3] || ''
                };
            }
            return null;
        }).filter(member => member && member.name);
    }
    
    //console.log('Loaded team members:', teamData);
    updateTeamCount();
}

// Update team count display
function updateTeamCount() {
    const teamCountElement = safeGetElement('team-count');
    if (teamCountElement) {
        teamCountElement.textContent = teamData.length;
    }
    
    const textareaLengthElement = safeGetElement('team-textarea-length');
    if (textareaLengthElement) {
        const content = safeGetElement('content');
        if (content) {
            textareaLengthElement.textContent = content.value.length;
        }
    }
}

// Update team preview display
function updateTeamPreview() {
    const container = safeGetElement('team-display');
    if (!container) {
        //console.warn('Team display container not found');
        return;
    }
    
    if (teamData.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 col-span-full">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-users text-4xl"></i>
                </div>
                <p class="text-gray-500 text-lg mb-2">No team members yet</p>
                <p class="text-gray-400 text-sm">Add your first team member using the form below</p>
            </div>
        `;
        updateTeamCount();
        return;
    }

    container.innerHTML = teamData.map((member, index) => {
        const isEditing = editingMemberIndex === index;
        const editingClass = isEditing ? 'ring-2 ring-orange-400 border-orange-300' : 'border-gray-200';
        
        return `
            <div class="bg-white rounded-lg shadow-md ${editingClass} overflow-hidden transition-all duration-200 hover:shadow-lg" data-member-index="${index}">
                ${isEditing ? `
                    <div class="absolute -top-2 -right-2 bg-orange-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold animate-pulse z-10">
                        ✏️
                    </div>
                ` : ''}
                
                <!-- Member Photo -->
                <div class="aspect-square bg-gray-100 relative overflow-hidden">
                    ${member.image ? `
                        <img src="${addBaseUrl(member.image)}" alt="${member.name}"
                             class="w-full h-full object-cover" 
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="w-full h-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center hidden">
                            <i class="fas fa-user text-gray-500 text-4xl"></i>
                        </div>
                    ` : `
                        <div class="w-full h-full bg-gradient-to-br from-gray-300 to-gray-400 flex items-center justify-center">
                            <i class="fas fa-user text-gray-500 text-4xl"></i>
                        </div>
                    `}
                </div>
                
                <!-- Member Info -->
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">${member.name}</h3>
                    <p class="text-blue-600 text-sm font-medium mb-2">${member.position}</p>
                    <p class="text-gray-600 text-sm leading-relaxed mb-4 line-clamp-3">${member.bio}</p>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <button onclick="editTeamMember(${index})" 
                                class="flex-1 bg-yellow-500 text-white py-2 px-3 rounded-lg hover:bg-yellow-600 transition-colors duration-200 text-xs font-medium">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </button>
                        <button onclick="duplicateTeamMember(${index})" 
                                class="flex-1 bg-green-500 text-white py-2 px-3 rounded-lg hover:bg-green-600 transition-colors duration-200 text-xs font-medium">
                            <i class="fas fa-copy mr-1"></i> Copy
                        </button>
                        <button onclick="moveTeamMember(${index}, -1)" 
                                class="bg-gray-500 text-white py-2 px-3 rounded-lg hover:bg-gray-600 transition-colors duration-200 text-xs font-medium" 
                                ${index === 0 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button onclick="moveTeamMember(${index}, 1)" 
                                class="bg-gray-500 text-white py-2 px-3 rounded-lg hover:bg-gray-600 transition-colors duration-200 text-xs font-medium" 
                                ${index === teamData.length - 1 ? 'disabled' : ''}>
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button onclick="removeTeamMember(${index})" 
                                class="bg-red-500 text-white py-2 px-3 rounded-lg hover:bg-red-600 transition-colors duration-200 text-xs font-medium">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    updateTeamCount();
}

// EDIT Team Member Function
function editTeamMember(index) {
    //console.log('=== EDIT TEAM MEMBER CLICKED ===');
    //console.log('Index:', index);
    //console.log('Member data:', teamData[index]);
    
    try {
        // Validate input
        if (!teamData || !teamData[index]) {
            //console.error('Team member not found at index:', index);
            showTeamNotification('❌ Team member not found!', 'error');
            return;
        }
        
        const member = teamData[index];
        editingMemberIndex = index;
        
        //console.log('Editing member:', member);
        
        // FIXED: Safe element access
        const nameField = safeGetElement('new-member-name');
        const positionField = safeGetElement('new-member-position');
        const bioField = safeGetElement('new-member-bio');
        const imageField = safeGetElement('new-member-image');
        
        // Check if all elements exist
        if (!nameField || !positionField || !bioField || !imageField) {
            //console.error('Missing form elements');
            showTeamNotification('❌ Form elements not found!', 'error');
            return;
        }
        
        // Clear and populate form fields
        nameField.value = member.name || '';
        positionField.value = member.position || '';
        bioField.value = member.bio || '';
        imageField.value = member.image || '';
        
        // console.log('Form populated with:', {
        //     name: nameField.value,
        //     position: positionField.value,
        //     bio: bioField.value,
        //     image: imageField.value
        // });
        
        // Update UI to show editing mode
        updateTeamFormUIForEditing();
        
        // Scroll to form
        scrollToTeamForm();
        
        // Update preview with highlighting
        updateTeamPreview();
        
        // Show success notification
        showTeamNotification(`✏️ Now editing: "${member.name}"`, 'info');
        
        //console.log('Team member edit setup completed successfully');
        
    } catch (error) {
        //console.error('Error in editTeamMember:', error);
        showTeamNotification('❌ Error: ' + error.message, 'error');
    }
}

// Update form UI for editing mode
function updateTeamFormUIForEditing() {
    // Update the main button
    const addButton = document.querySelector('button[onclick="addNewTeamMember()"]');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-save mr-2"></i>💾 Update Member';
        addButton.className = addButton.className.replace('bg-blue-500', 'bg-green-500').replace('hover:bg-blue-600', 'hover:bg-green-600');
    }
    
    // Show cancel button
    const cancelButton = document.querySelector('button[onclick="cancelTeamMemberEdit()"]');
    if (cancelButton) {
        cancelButton.style.display = 'inline-block';
    }
    
    // Update form header
    const formHeaders = document.querySelectorAll('h4');
    formHeaders.forEach(header => {
        if (header.textContent.includes('Add New Team Member')) {
            header.innerHTML = '<i class="fas fa-edit mr-2 text-orange-600"></i>✏️ Edit Team Member';
        }
    });
}

// Scroll to form
function scrollToTeamForm() {
    const formElement = document.querySelector('.bg-white.border-2.border-gray-300');
    if (formElement) {
        formElement.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
        
        // Add temporary highlight
        formElement.style.boxShadow = '0 0 20px rgba(6, 182, 212, 0.3)';
        setTimeout(() => {
            formElement.style.boxShadow = '';
        }, 2000);
    }
}

// Add new team member or update existing
function addNewTeamMember() {
    // FIXED: Safe element access
    const nameEl = safeGetElement('new-member-name');
    const positionEl = safeGetElement('new-member-position');
    const bioEl = safeGetElement('new-member-bio');
    const imageEl = safeGetElement('new-member-image');
    
    const name = nameEl ? nameEl.value.trim() : '';
    const position = positionEl ? positionEl.value.trim() : '';
    const bio = bioEl ? bioEl.value.trim() : '';
    const image = imageEl ? imageEl.value.trim() : '';
    
    // Validation
    if (!name) {
        showTeamNotification('❌ Please enter the member name.', 'error');
        if (nameEl) nameEl.focus();
        return;
    }
    
    if (!position) {
        showTeamNotification('❌ Please enter the member position.', 'error');
        if (positionEl) positionEl.focus();
        return;
    }
    
    if (!bio) {
        showTeamNotification('❌ Please enter the member bio.', 'error');
        if (bioEl) bioEl.focus();
        return;
    }

    const memberData = {
        name: name,
        position: position,
        bio: bio,
        image: removeBaseUrl(image)
    };

    if (editingMemberIndex >= 0) {
        // Update existing member
        const oldName = teamData[editingMemberIndex].name;
        teamData[editingMemberIndex] = memberData;
        
        //console.log('Team member updated:', memberData);
        showTeamNotification(`✅ "${oldName}" updated successfully!`, 'success');
    } else {
        // Add new member
        teamData.push(memberData);
        showTeamNotification(`🎉 New team member "${name}" added successfully!`, 'success');
    }
    
    // Reset form and update
    clearTeamForm();
    updateTeamTextarea();
    updateTeamPreview();
}

// Clear team form
function clearTeamForm() {
    // FIXED: Safe element access
    const fields = ['new-member-name', 'new-member-position', 'new-member-bio', 'new-member-image'];
    fields.forEach(fieldId => {
        const field = safeGetElement(fieldId);
        if (field) field.value = '';
    });
    
    // Clear image preview
    clearMemberImagePreview();
    
    // Reset form UI
    const addButton = document.querySelector('button[onclick="addNewTeamMember()"]');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-plus mr-2"></i>➕ Add Member';
        addButton.className = addButton.className.replace('bg-green-500', 'bg-blue-500').replace('hover:bg-green-600', 'hover:bg-blue-600');
    }
    
    // Hide cancel button
    const cancelButton = document.querySelector('button[onclick="cancelTeamMemberEdit()"]');
    if (cancelButton) {
        cancelButton.style.display = 'none';
    }
    
    // Reset form header
    const formHeaders = document.querySelectorAll('h4');
    formHeaders.forEach(header => {
        if (header.textContent.includes('Edit Team Member')) {
            header.innerHTML = '<i class="fas fa-user-plus mr-2 text-green-600"></i>Add New Team Member';
        }
    });
    
    editingMemberIndex = -1;
    
    // Reset preview highlighting
    updateTeamPreview();
}

// Cancel team member edit
function cancelTeamMemberEdit() {
    clearTeamForm();
    showTeamNotification('✖️ Edit cancelled', 'info');
}

// Duplicate team member
function duplicateTeamMember(index) {
    const member = { ...teamData[index] };
    member.name = member.name + ' (Copy)';
    
    teamData.splice(index + 1, 0, member);
    updateTeamTextarea();
    updateTeamPreview();
    
    showTeamNotification('📋 Team member duplicated successfully!', 'success');
}

// Remove team member
function removeTeamMember(index) {
    const memberName = teamData[index].name;
    if (confirm(`🗑️ Are you sure you want to remove "${memberName}" from the team?`)) {
        teamData.splice(index, 1);
        updateTeamTextarea();
        updateTeamPreview();
        
        showTeamNotification(`🗑️ "${memberName}" removed from team!`, 'success');
        
        // If we were editing this member, clear the form
        if (editingMemberIndex === index) {
            clearTeamForm();
        } else if (editingMemberIndex > index) {
            editingMemberIndex--;
        }
    }
}

// Move team member up or down
function moveTeamMember(index, direction) {
    const newIndex = index + direction;
    if (newIndex >= 0 && newIndex < teamData.length) {
        [teamData[index], teamData[newIndex]] = [teamData[newIndex], teamData[index]];
        
        // Update editing index if necessary
        if (editingMemberIndex === index) {
            editingMemberIndex = newIndex;
        } else if (editingMemberIndex === newIndex) {
            editingMemberIndex = index;
        }
        
        updateTeamTextarea();
        updateTeamPreview();
    }
}

// Clear all team members
function clearAllTeamMembers() {
    if (confirm('🚨 Are you sure you want to remove ALL team members?')) {
        teamData = [];
        clearTeamForm();
        updateTeamTextarea();
        updateTeamPreview();
        
        showTeamNotification('🗑️ All team members cleared!', 'success');
    }
}

// Update textarea with team data
function updateTeamTextarea() {
    try {
        const contentTextarea = safeGetElement('content');
        if (!contentTextarea) {
            //console.error('❌ Content textarea not found during update');
            return;
        }
        
        const formatted = teamData.map(member => 
            `${member.name}|${member.position}|${member.bio}|${member.image}`
        ).join('||');
        
        //console.log('💾 Updating textarea with:', formatted);
        contentTextarea.value = formatted;
        
        // Trigger change event for any listeners
        const event = new Event('input', { bubbles: true });
        contentTextarea.dispatchEvent(event);
        
        updateTeamCount();
        
    } catch (error) {
        //console.error('❌ Error updating textarea:', error);
    }
}

// Image preview functions
function updateMemberImagePreview(imageUrl) {
    const previewContainer = safeGetElement('member-image-preview');
    const previewImg = safeGetElement('preview-member-image');
    
    if (previewContainer && previewImg && imageUrl.trim()) {
        previewImg.src = imageUrl;
        previewContainer.classList.remove('hidden');
        
        previewImg.onerror = function() {
            clearMemberImagePreview();
            showTeamNotification('❌ Failed to load image', 'error');
        };
    }
}

function clearMemberImagePreview() {
    const previewContainer = safeGetElement('member-image-preview');
    const imageField = safeGetElement('new-member-image');
    
    if (previewContainer) {
        previewContainer.classList.add('hidden');
    }
    if (imageField) {
        imageField.value = '';
    }
}

// Image picker and upload functions for team members
function openImagePickerForMember() {
    currentMemberImageTarget = 'form';
    openImagePicker();
}

function openImageUploadForMember() {
    currentMemberImageTarget = 'form';
    openImageUpload();
}

// FIXED: Safer selectImage override
const originalSelectImage = window.selectImage;
window.selectImage = function(url) {
    try {
        if (currentMemberImageTarget === 'form') {
            const relativePath = removeBaseUrl(url);
            const imageField = safeGetElement('new-member-image');
            if (imageField) {
                imageField.value = relativePath;
                updateMemberImagePreview(addBaseUrl(relativePath));
            }
            currentMemberImageTarget = null;
            closeImagePicker();
            return;
        }
        
        // Fall back to original behavior
        if (originalSelectImage) {
            originalSelectImage(url);
        }
    } catch (error) {
        //console.error('Error in selectImage override:', error);
    }
};

// Show team notification
function showTeamNotification(message, type = 'success') {
    // Remove existing notification
    const existing = document.querySelector('.team-notification');
    if (existing) existing.remove();
    
    const colors = {
        success: 'bg-green-500 text-white',
        error: 'bg-red-500 text-white',
        info: 'bg-blue-500 text-white',
        warning: 'bg-yellow-500 text-black'
    };
    
    const notification = document.createElement('div');
    notification.className = `team-notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${colors[type] || colors.success}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-lg hover:opacity-75">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 4000);
}

// FIXED: Single textarea monitor
document.addEventListener('DOMContentLoaded', function() {
    const contentTextarea = safeGetElement('content');
    if (contentTextarea && safeGetElement('team-preview')) {
        contentTextarea.addEventListener('input', function() {
            loadExistingTeamMembers();
            updateTeamPreview();
        });
    }
});

// FIXED: CSS injection - only once
if (!document.querySelector('#team-section-styles')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'team-section-styles';
    styleElement.textContent = `
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .team-notification {
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .aspect-square {
            aspect-ratio: 1 / 1;
        }
        
        [data-member-index] {
            transition: all 0.2s ease;
        }
        
        [data-member-index]:hover {
            transform: translateY(-2px);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    `;
    document.head.appendChild(styleElement);
}

//console.log('✅ Fixed Team Section JavaScript Loaded');

// ===== FEATURES MANAGEMENT SCRIPT - JSON ONLY FORMAT =====

// Global Variables
let featuresData = [];
let editingFeatureIndex = -1;
let currentFeatureImageTarget = null;
let isFormSubmitting = false;

// Sample features for quick start
const sampleFeatures = [
    {
        title: "Lightning Fast",
        description: "Optimized infrastructure ensures instant loading",
        icon: "fas fa-bolt",
        image: "",
        iconBg: "#3b82f6",
        iconColor: "#ffffff",
        featureurl: 'https://example.com/performance'
    },
    {
        title: "24/7 Support",
        description: "Round-the-clock customer support team",
        icon: "fas fa-headset",
        image: "",
        iconBg: "#10b981",
        iconColor: "#ffffff",
        featureurl: 'https://example.com/support'
    },
    {
        title: "Secure & Reliable",
        description: "Enterprise-grade security keeps data safe",
        icon: "fas fa-shield-alt",
        image: "",
        iconBg: "#f59e0b",
        iconColor: "#ffffff",
        featureurl: 'https://example.com/security'
    }
];

// ===== INITIALIZATION =====
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('features-preview')) {
        //console.log('=== FEATURES SECTION INITIALIZED (JSON ONLY) ===');
        loadFeaturesFromJSON();
        updateFeaturesPreview();
        setupColorSyncForFeatures();
        setupScrollIndicator();
        setupFormSubmitProtection();
        setupTextareaMonitoring();
    }
});

// ===== FORM SUBMIT PROTECTION =====
function setupFormSubmitProtection() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            //console.log('=== FORM SUBMITTING - DISABLING TEXTAREA MONITORING ===');
            isFormSubmitting = true;
            
            setTimeout(() => {
                isFormSubmitting = false;
                //console.log('=== FORM SUBMIT COMPLETE - RE-ENABLING MONITORING ===');
            }, 2000);
        });
    }
}

// ===== TEXTAREA MONITORING (JSON ONLY) =====
function setupTextareaMonitoring() {
    const jsonTextarea = document.getElementById('content-json') || document.getElementById('content');
    
    if (jsonTextarea) {
        //console.log('Setting up JSON textarea monitoring');
        
        let lastValidJsonContent = jsonTextarea.value;
        
        jsonTextarea.addEventListener('input', function() {
            if (isFormSubmitting) {
                //console.log('Ignoring textarea change - form is submitting');
                return;
            }
            
            const newContent = this.value;
            
            if (newContent !== lastValidJsonContent) {
                //console.log('JSON textarea changed by user, reloading features');
                lastValidJsonContent = newContent;
                
                clearTimeout(window.jsonTextareaDebounceTimer);
                window.jsonTextareaDebounceTimer = setTimeout(() => {
                    loadFeaturesFromJSON();
                    updateFeaturesPreview();
                    updateCharacterCount();
                }, 300);
            }
        });
        
        updateCharacterCount();
    }
}

// ===== DATA LOADING (JSON ONLY) =====
function loadFeaturesFromJSON() {
    if (isFormSubmitting) {
        //console.log('Skipping JSON feature loading - form is submitting');
        return;
    }
    
    const jsonTextarea = document.getElementById('content-json') || document.getElementById('content');
    if (!jsonTextarea) {
        //console.error('JSON textarea not found');
        return;
    }
    
    const jsonContent = jsonTextarea.value.trim();
    //console.log('Loading features from JSON:', jsonContent);
    
    const previousCount = featuresData.length;
    featuresData = [];
    
    if (!jsonContent) {
        //console.log('No JSON content found, features cleared');
        updateFeaturesCount();
        updateCharacterCount();
        return;
    }
    
    try {
        const parsedData = JSON.parse(jsonContent);
        
        // Handle different JSON structures
        let features = [];
        if (Array.isArray(parsedData)) {
            features = parsedData;
        } else if (parsedData.features && Array.isArray(parsedData.features)) {
            features = parsedData.features;
        } else if (parsedData.data && Array.isArray(parsedData.data)) {
            features = parsedData.data;
        } else {
            //console.error('Invalid JSON structure - expected array or object with features/data property');
            showFeatureNotification('❌ Invalid JSON structure', 'error');
            return;
        }
        
        featuresData = features.filter(feature => {
            return feature && 
                   feature.title && 
                   feature.description && 
                   feature.title.trim() && 
                   feature.description.trim();
        }).map(feature => ({
            title: feature.title || '',
            description: feature.description || '',
            icon: feature.icon || 'fas fa-star',
            image: feature.image || '',
            iconBg: feature.iconBg || feature.iconBackground || '#3b82f6',
            iconColor: feature.iconColor || '#ffffff',
            featureurl: feature.featureurl || feature.url || feature.link || ''
        }));
        
        //console.log(`Loaded ${featuresData.length} valid features from JSON (was ${previousCount})`);
        
    } catch (error) {
        console.error('Error parsing JSON features:', error);
        showFeatureNotification('❌ Invalid JSON format', 'error');
        featuresData = [];
    }
    
    updateFeaturesCount();
    updateCharacterCount();
}

// ===== UI SETUP FUNCTIONS =====
function setupScrollIndicator() {
    const container = document.getElementById('features-display');
    const indicator = document.getElementById('scroll-indicator');
    
    if (!container || !indicator) return;
    
    function checkScrollNeeded() {
        const isScrollable = container.scrollHeight > container.clientHeight;
        
        if (isScrollable && featuresData.length > 6) {
            indicator.classList.remove('hidden');
        } else {
            indicator.classList.add('hidden');
        }
    }
    
    container.addEventListener('scroll', function() {
        const isAtBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 10;
        
        if (isAtBottom) {
            indicator.classList.add('hidden');
        } else if (featuresData.length > 6) {
            indicator.classList.remove('hidden');
        }
    });
    
    setTimeout(checkScrollNeeded, 100);
    window.checkScrollNeeded = checkScrollNeeded;
}

function setupColorSyncForFeatures() {
    const iconBgInput = document.getElementById('new-feature-icon-bg');
    const iconBgText = document.getElementById('new-feature-icon-bg-text');
    const iconColorInput = document.getElementById('new-feature-icon-color');
    const iconColorText = document.getElementById('new-feature-icon-color-text');
    
    if (iconBgInput && iconBgText) {
        iconBgInput.addEventListener('input', function() {
            iconBgText.value = this.value;
        });
        
        iconBgText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                iconBgInput.value = this.value;
            }
        });
    }
    
    if (iconColorInput && iconColorText) {
        iconColorInput.addEventListener('input', function() {
            iconColorText.value = this.value;
        });
        
        iconColorText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                iconColorInput.value = this.value;
            }
        });
    }
}

// ===== DISPLAY UPDATE FUNCTIONS =====
function updateFeaturesCount() {
    const featuresCountElement = document.getElementById('features-count');
    if (featuresCountElement) {
        featuresCountElement.textContent = featuresData.length;
    }
    
    updateCharacterCount();
}

function updateCharacterCount() {
    const jsonLengthElement = document.getElementById('features-json-length') || document.getElementById('features-textarea-length');
    
    if (jsonLengthElement) {
        const jsonTextarea = document.getElementById('content-json') || document.getElementById('content');
        const jsonContent = jsonTextarea?.value || '';
        
        const length = jsonContent.length;
        let displayText = `${length} characters`;
        let colorClass = 'text-gray-500';
        
        if (length === 0) {
            displayText = '0 characters (empty)';
            colorClass = 'text-gray-400';
        } else if (length > 5000) {
            displayText = `${length} characters (large dataset)`;
            colorClass = 'text-orange-600';
        } else if (length > 1000) {
            displayText = `${length} characters (good size)`;
            colorClass = 'text-green-600';
        } else {
            displayText = `${length} characters`;
            colorClass = 'text-blue-600';
        }
        
        jsonLengthElement.textContent = displayText;
        jsonLengthElement.className = `text-sm ${colorClass}`;
    }
}

function updateFeaturesPreview() {
    const container = document.getElementById('features-display');
    if (!container) return;
    
    //console.log('Updating preview with', featuresData.length, 'features');
    
    if (featuresData.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 col-span-full">
                <div class="text-gray-400 mb-4">
                    <i class="fas fa-th-large text-4xl"></i>
                </div>
                <p class="text-gray-500 text-lg mb-2">No features yet</p>
                <p class="text-gray-400 text-sm">Add your first feature using the form below</p>
            </div>
        `;
        updateFeaturesCount();
        return;
    }

    container.innerHTML = featuresData.map((feature, index) => {
        const isEditing = editingFeatureIndex === index;
        const editingClass = isEditing ? 'ring-2 ring-orange-400 border-orange-300' : 'border-gray-200';
        
        return `
            <div class="bg-white rounded-lg shadow-md ${editingClass} overflow-hidden transition-all duration-200 hover:shadow-lg relative" data-feature-index="${index}">
                ${isEditing ? `
                    <div class="absolute -top-2 -right-2 bg-orange-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold animate-pulse z-10">
                        ✏️
                    </div>
                ` : ''}
                
                <div class="p-4 text-center">
                    <!-- Feature Image -->
                    ${feature.image ? `
                        <div class="mb-3">
                            <img src="${feature.image}" alt="${feature.title}" 
                                 class="w-full h-24 object-cover rounded-lg border" 
                                 onerror="this.style.display='none';">
                        </div>
                    ` : ''}
                    
                    <!-- Feature Icon -->
                    <div class="mb-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-2" 
                             style="background-color: ${feature.iconBg};">
                            <i class="${feature.icon} text-lg" style="color: ${feature.iconColor};"></i>
                        </div>
                        <h4 class="text-sm font-semibold text-gray-900 mb-1">${feature.title}</h4>
                        <p class="text-gray-600 text-xs leading-relaxed line-clamp-2">${feature.description}</p>
                        ${feature.featureurl ? `
                            <div class="mt-2">
                                <a href="${feature.featureurl}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs flex items-center justify-center">
                                    <i class="fas fa-external-link-alt mr-1"></i>Learn more
                                </a>
                            </div>
                        ` : ''}
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-1">
                        <button onclick="editFeature(${index})" 
                                class="flex-1 bg-yellow-500 text-white py-1 px-2 rounded text-xs hover:bg-yellow-600 transition-colors duration-200"
                                title="Edit this feature">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="duplicateFeature(${index})" 
                                class="flex-1 bg-green-500 text-white py-1 px-2 rounded text-xs hover:bg-green-600 transition-colors duration-200"
                                title="Copy this feature">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button onclick="moveFeature(${index}, -1)" 
                                class="bg-gray-500 text-white py-1 px-2 rounded text-xs hover:bg-gray-600 transition-colors duration-200" 
                                ${index === 0 ? 'disabled style="opacity:0.5"' : ''}
                                title="Move up">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                        <button onclick="moveFeature(${index}, 1)" 
                                class="bg-gray-500 text-white py-1 px-2 rounded text-xs hover:bg-gray-600 transition-colors duration-200" 
                                ${index === featuresData.length - 1 ? 'disabled style="opacity:0.5"' : ''}
                                title="Move down">
                            <i class="fas fa-arrow-down"></i>
                        </button>
                        <button onclick="removeFeature(${index})" 
                                class="bg-red-500 text-white py-1 px-2 rounded text-xs hover:bg-red-600 transition-colors duration-200"
                                title="Delete this feature">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    updateFeaturesCount();
    
    setTimeout(() => {
        if (window.checkScrollNeeded) {
            window.checkScrollNeeded();
        }
    }, 100);
}

// ===== CORE FEATURE MANAGEMENT FUNCTIONS =====

// ADD NEW FEATURE
function addNewFeature() {
    //console.log('=== ADD/UPDATE FEATURE CALLED ===');
    //console.log('Current editingFeatureIndex:', editingFeatureIndex);
    
    // Check if we're in editing mode
    const isEditing = editingFeatureIndex >= 0 && editingFeatureIndex < featuresData.length;
    //console.log('Is editing mode:', isEditing);
    
    const title = document.getElementById('new-feature-title')?.value?.trim() || '';
    const description = document.getElementById('new-feature-description')?.value?.trim() || '';
    const icon = document.getElementById('new-feature-icon')?.value?.trim() || '';
    const image = document.getElementById('new-feature-image')?.value?.trim() || '';
    const iconBg = document.getElementById('new-feature-icon-bg')?.value?.trim() || '';
    const iconColor = document.getElementById('new-feature-icon-color')?.value?.trim() || '';
    const featureurl = document.getElementById('new-feature-url')?.value?.trim() || '';
    
    //console.log('Form data collected:', { title, description, icon, image, iconBg, iconColor, featureurl });
    //console.log('Features array before operation:', featuresData.length);
    
    // Validation
    if (!title) {
        showFeatureNotification('❌ Please enter the feature title.', 'error');
        document.getElementById('new-feature-title')?.focus();
        return;
    }
    
    if (!description) {
        showFeatureNotification('❌ Please enter the feature description.', 'error');
        document.getElementById('new-feature-description')?.focus();
        return;
    }

    const featureData = {
        title: title,
        description: description,
        icon: icon || 'fas fa-star',
        image: image,
        iconBg: iconBg || '#3b82f6',
        iconColor: iconColor || '#ffffff',
        featureurl: featureurl
    };

    //console.log('Feature data to save:', featureData);

    if (isEditing) {
        // Update existing feature
        //console.log('UPDATING existing feature at index:', editingFeatureIndex);
        featuresData[editingFeatureIndex] = featureData;
        showFeatureNotification(`✅ Feature "${title}" updated successfully!`, 'success');
    } else {
        // Add new feature
        //console.log('ADDING new feature');
        featuresData.push(featureData);
        showFeatureNotification(`🎉 New feature "${title}" added successfully!`, 'success');
    }
    
    //console.log('Features array after operation:', featuresData.length);
    
    clearFeatureForm();
    updateJSONTextarea();
    updateFeaturesPreview();
}

// EDIT FEATURE
function editFeature(index) {
    //console.log('=== EDIT FEATURE CALLED ===');
    
    if (typeof index !== 'number' || index < 0 || index >= featuresData.length) {
        console.error('Invalid feature index:', index);
        showFeatureNotification('❌ Invalid feature selected!', 'error');
        return;
    }
    
    const feature = featuresData[index];
    if (!feature) {
        console.error('Feature not found at index:', index);
        showFeatureNotification('❌ Feature not found!', 'error');
        return;
    }
    
    //console.log('Editing feature:', feature);
    editingFeatureIndex = index;
    
    // Populate form with feature data
    const formElements = {
        title: document.getElementById('new-feature-title'),
        description: document.getElementById('new-feature-description'),
        icon: document.getElementById('new-feature-icon'),
        image: document.getElementById('new-feature-image'),
        iconBg: document.getElementById('new-feature-icon-bg'),
        iconBgText: document.getElementById('new-feature-icon-bg-text'),
        iconColor: document.getElementById('new-feature-icon-color'),
        iconColorText: document.getElementById('new-feature-icon-color-text'),
        url: document.getElementById('new-feature-url')
    };
    
    if (formElements.title) formElements.title.value = feature.title || '';
    if (formElements.description) formElements.description.value = feature.description || '';
    if (formElements.icon) formElements.icon.value = feature.icon || 'fas fa-star';
    if (formElements.image) formElements.image.value = feature.image || '';
    if (formElements.iconBg) formElements.iconBg.value = feature.iconBg || '#3b82f6';
    if (formElements.iconBgText) formElements.iconBgText.value = feature.iconBg || '#3b82f6';
    if (formElements.iconColor) formElements.iconColor.value = feature.iconColor || '#ffffff';
    if (formElements.iconColorText) formElements.iconColorText.value = feature.iconColor || '#ffffff';
    if (formElements.url) formElements.url.value = feature.featureurl || '';

    // Update image preview
    if (feature.image) {
        updateFeatureImagePreview(feature.image);
    } else {
        clearFeatureImagePreview();
    }
    
    // Update UI for editing mode
    const addButton = document.querySelector('button[onclick="addNewFeature()"]');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-save mr-2"></i>💾 Update Feature';
        addButton.className = addButton.className.replace('bg-blue-500', 'bg-green-500').replace('hover:bg-blue-600', 'hover:bg-green-600');
    }
    
    const cancelButton = document.querySelector('button[onclick="cancelFeatureEdit()"]');
    if (cancelButton) {
        cancelButton.style.display = 'inline-block';
    }
    
    updateFeaturesPreview();
    showFeatureNotification(`✏️ Now editing: "${feature.title}"`, 'info');
    
    // Scroll to form
    const formElement = document.querySelector('.bg-white.border-2.border-gray-300');
    if (formElement) {
        formElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// DUPLICATE FEATURE
function duplicateFeature(index) {
    if (typeof index !== 'number' || index < 0 || index >= featuresData.length) {
        showFeatureNotification('❌ Invalid feature selected!', 'error');
        return;
    }
    
    const originalFeature = featuresData[index];
    if (!originalFeature) {
        showFeatureNotification('❌ Feature not found!', 'error');
        return;
    }
    
    const duplicatedFeature = {
        title: originalFeature.title + ' (Copy)',
        description: originalFeature.description,
        icon: originalFeature.icon,
        image: originalFeature.image,
        iconBg: originalFeature.iconBg,
        iconColor: originalFeature.iconColor,
        featureurl: originalFeature.featureurl
    };
    
    featuresData.splice(index + 1, 0, duplicatedFeature);
    
    updateJSONTextarea();
    updateFeaturesPreview();
    
    showFeatureNotification(`📋 Feature "${originalFeature.title}" copied successfully!`, 'success');
}

// REMOVE FEATURE
function removeFeature(index) {
    if (typeof index !== 'number' || index < 0 || index >= featuresData.length) {
        showFeatureNotification('❌ Invalid feature selected!', 'error');
        return;
    }
    
    const featureToRemove = featuresData[index];
    if (!featureToRemove) {
        showFeatureNotification('❌ Feature not found!', 'error');
        return;
    }
    
    const featureTitle = featureToRemove.title;
    
    if (confirm(`🗑️ Are you sure you want to delete "${featureTitle}"?\n\nThis action cannot be undone.`)) {
        featuresData.splice(index, 1);
        
        // Handle editing state
        if (editingFeatureIndex === index) {
            clearFeatureForm();
        } else if (editingFeatureIndex > index) {
            editingFeatureIndex--;
        }
        
        updateJSONTextarea();
        updateFeaturesPreview();
        
        showFeatureNotification(`🗑️ Feature "${featureTitle}" deleted successfully!`, 'success');
    }
}

// MOVE FEATURE
function moveFeature(index, direction) {
    const newIndex = index + direction;
    
    if (newIndex >= 0 && newIndex < featuresData.length) {
        [featuresData[index], featuresData[newIndex]] = [featuresData[newIndex], featuresData[index]];
        
        if (editingFeatureIndex === index) {
            editingFeatureIndex = newIndex;
        } else if (editingFeatureIndex === newIndex) {
            editingFeatureIndex = index;
        }
        
        updateJSONTextarea();
        updateFeaturesPreview();
        
        showFeatureNotification('✅ Feature moved successfully!', 'success');
    }
}

// ===== FORM MANAGEMENT =====

function clearFeatureForm() {
    const fields = ['new-feature-title', 'new-feature-description', 'new-feature-icon', 'new-feature-image', 'new-feature-url'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) field.value = '';
    });
    
    // Reset colors to defaults
    const iconBg = document.getElementById('new-feature-icon-bg');
    const iconBgText = document.getElementById('new-feature-icon-bg-text');
    const iconColor = document.getElementById('new-feature-icon-color');
    const iconColorText = document.getElementById('new-feature-icon-color-text');
    
    if (iconBg) iconBg.value = '#3b82f6';
    if (iconBgText) iconBgText.value = '#3b82f6';
    if (iconColor) iconColor.value = '#ffffff';
    if (iconColorText) iconColorText.value = '#ffffff';
    
    clearFeatureImagePreview();
    editingFeatureIndex = -1;
    
    const addButton = document.querySelector('button[onclick="addNewFeature()"]');
    if (addButton) {
        addButton.innerHTML = '<i class="fas fa-plus mr-2"></i>➕ Add Feature';
        addButton.className = addButton.className.replace('bg-green-500', 'bg-blue-500').replace('hover:bg-green-600', 'hover:bg-blue-600');
    }
    
    const cancelButton = document.querySelector('button[onclick="cancelFeatureEdit()"]');
    if (cancelButton) {
        cancelButton.style.display = 'none';
    }
    
    updateFeaturesPreview();
}

function cancelFeatureEdit() {
    clearFeatureForm();
    showFeatureNotification('✖️ Edit cancelled', 'info');
}

// ===== JSON DATA PERSISTENCE =====
function updateJSONTextarea() {
    try {
        const jsonTextarea = document.getElementById('content-json') || document.getElementById('content');
        if (!jsonTextarea) {
            //console.log('JSON textarea not found');
            return;
        }
        
        //console.log('Updating JSON textarea with', featuresData.length, 'features');
        
        // Create clean JSON structure
        const jsonData = {
            features: featuresData.map(feature => {
                const cleanFeature = {
                    title: feature.title,
                    description: feature.description
                };
                
                // Only include non-default values
                if (feature.icon && feature.icon !== 'fas fa-star') {
                    cleanFeature.icon = feature.icon;
                }
                if (feature.image && feature.image.trim()) {
                    cleanFeature.image = feature.image;
                }
                if (feature.iconBg && feature.iconBg !== '#3b82f6') {
                    cleanFeature.iconBg = feature.iconBg;
                }
                if (feature.iconColor && feature.iconColor !== '#ffffff') {
                    cleanFeature.iconColor = feature.iconColor;
                }
                if (feature.featureurl && feature.featureurl.trim()) {
                    cleanFeature.featureurl = feature.featureurl;
                }
                
                return cleanFeature;
            }),
            metadata: {
                totalFeatures: featuresData.length,
                lastUpdated: new Date().toISOString(),
                version: '2.0'
            }
        };
        
        const formattedJSON = JSON.stringify(jsonData, null, 2);
        
        // Mark that we're updating programmatically
        isFormSubmitting = true;
        jsonTextarea.value = formattedJSON;
        
        // Dispatch change event
        const event = new Event('input', { bubbles: true });
        jsonTextarea.dispatchEvent(event);
        
        updateCharacterCount();
        
        setTimeout(() => {
            isFormSubmitting = false;
        }, 100);
        
    } catch (error) {
        //console.error('Error updating JSON textarea:', error);
        showFeatureNotification('❌ Error saving JSON features', 'error');
        isFormSubmitting = false;
    }
}

// ===== BULK ACTIONS =====

function clearAllFeatures() {
    if (confirm('🚨 Are you sure you want to delete ALL features?\n\nThis action cannot be undone.')) {
        featuresData = [];
        clearFeatureForm();
        updateJSONTextarea();
        updateFeaturesPreview();
        showFeatureNotification('🗑️ All features cleared!', 'success');
    }
}

function addSampleFeatures() {
    if (confirm('Add sample features? This will add example features to get you started.')) {
        featuresData = [...featuresData, ...sampleFeatures];
        updateJSONTextarea();
        updateFeaturesPreview();
        showFeatureNotification(`🎉 Added ${sampleFeatures.length} sample features!`, 'success');
    }
}

function sortFeaturesAlphabetically() {
    if (featuresData.length === 0) {
        showFeatureNotification('❌ No features to sort', 'error');
        return;
    }
    
    featuresData.sort((a, b) => a.title.localeCompare(b.title));
    editingFeatureIndex = -1;
    clearFeatureForm();
    updateJSONTextarea();
    updateFeaturesPreview();
    showFeatureNotification('✅ Features sorted alphabetically!', 'success');
}

function exportFeaturesAsJSON() {
    if (featuresData.length === 0) {
        showFeatureNotification('❌ No features to export', 'error');
        return;
    }
    
    const exportData = {
        features: featuresData,
        metadata: {
            exportDate: new Date().toISOString(),
            totalFeatures: featuresData.length,
            version: '2.0'
        }
    };
    
    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `features-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    
    showFeatureNotification('📁 Features exported successfully!', 'success');
}

// ===== IMAGE MANAGEMENT =====

function updateFeatureImagePreview(imageUrl) {
    const previewContainer = document.getElementById('feature-image-preview');
    const previewImg = document.getElementById('preview-feature-image');
    
    if (previewContainer && previewImg && imageUrl.trim()) {
        previewImg.src = imageUrl;
        previewContainer.classList.remove('hidden');
        
        previewImg.onerror = function() {
            clearFeatureImagePreview();
            showFeatureNotification('❌ Failed to load image', 'error');
        };
    }
}

function clearFeatureImagePreview() {
    const previewContainer = document.getElementById('feature-image-preview');
    const imageField = document.getElementById('new-feature-image');
    
    if (previewContainer) {
        previewContainer.classList.add('hidden');
    }
    if (imageField) {
        imageField.value = '';
    }
}

function openImagePickerForFeature() {
    currentFeatureImageTarget = 'form';
    openImagePicker();
}

function openImageUploadForFeature() {
    currentFeatureImageTarget = 'form';
    openImageUpload();
}

// ===== ICON PICKER FUNCTIONS =====

function openIconPicker() {
    document.getElementById('iconPickerModal').classList.remove('hidden');
    loadIcons();
}

function closeIconPicker() {
    document.getElementById('iconPickerModal').classList.add('hidden');
}

function loadIcons() {
    const iconGrid = document.getElementById('iconGrid');
    
    const popularIcons = [
        'fas fa-star', 'fas fa-heart', 'fas fa-bolt', 'fas fa-rocket', 'fas fa-shield-alt',
        'fas fa-lock', 'fas fa-clock', 'fas fa-users', 'fas fa-chart-line', 'fas fa-trophy',
        'fas fa-thumbs-up', 'fas fa-check-circle', 'fas fa-fire', 'fas fa-gem', 'fas fa-crown',
        'fas fa-magic', 'fas fa-lightbulb', 'fas fa-cog', 'fas fa-globe', 'fas fa-wifi',
        'fas fa-mobile-alt', 'fas fa-laptop', 'fas fa-server', 'fas fa-cloud', 'fas fa-database',
        'fas fa-headset', 'fas fa-phone', 'fas fa-envelope', 'fas fa-comments', 'fas fa-video',
        'fas fa-camera', 'fas fa-image', 'fas fa-play', 'fas fa-music', 'fas fa-volume-up',
        'fas fa-shopping-cart', 'fas fa-credit-card', 'fas fa-dollar-sign', 'fas fa-gift', 'fas fa-tag',
        'fas fa-home', 'fas fa-building', 'fas fa-car', 'fas fa-plane', 'fas fa-ship',
        'fas fa-map-marker-alt', 'fas fa-compass', 'fas fa-flag', 'fas fa-calendar', 'fas fa-clipboard',
        'fas fa-edit', 'fas fa-save', 'fas fa-print', 'fas fa-download', 'fas fa-upload',
        'fas fa-search', 'fas fa-filter', 'fas fa-sort', 'fas fa-list', 'fas fa-table',
        'fas fa-chart-bar', 'fas fa-chart-pie', 'fas fa-analytics', 'fas fa-trending-up', 'fas fa-percentage',
        'fas fa-shield', 'fas fa-key', 'fas fa-fingerprint', 'fas fa-eye', 'fas fa-eye-slash',
        'fas fa-bell', 'fas fa-notification', 'fas fa-alarm-clock', 'fas fa-stopwatch', 'fas fa-hourglass',
        'fas fa-tools', 'fas fa-wrench', 'fas fa-hammer', 'fas fa-screwdriver', 'fas fa-paint-brush'
    ];
    
    iconGrid.innerHTML = '';
    popularIcons.forEach(iconClass => {
        const iconDiv = document.createElement('div');
        iconDiv.className = 'p-3 border border-gray-200 rounded-lg hover:bg-blue-50 hover:border-blue-300 cursor-pointer transition-colors duration-200 text-center';
        iconDiv.innerHTML = `<i class="${iconClass} text-xl text-gray-600"></i>`;
        iconDiv.onclick = () => selectIcon(iconClass);
        iconGrid.appendChild(iconDiv);
    });
}

function selectIcon(iconClass) {
    document.getElementById('new-feature-icon').value = iconClass;
    closeIconPicker();
    showFeatureNotification(`✅ Icon selected: ${iconClass}`, 'success');
}

function filterIcons() {
    const searchTerm = document.getElementById('iconSearch').value.toLowerCase();
    const iconItems = document.querySelectorAll('#iconGrid > div');
    
    iconItems.forEach(item => {
        const iconClass = item.querySelector('i').className.toLowerCase();
        if (iconClass.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// ===== NOTIFICATION SYSTEM =====
function showFeatureNotification(message, type = 'success') {
    const existing = document.querySelector('.feature-notification');
    if (existing) existing.remove();
    
    const colors = {
        success: 'bg-green-500 text-white',
        error: 'bg-red-500 text-white',
        info: 'bg-blue-500 text-white',
        warning: 'bg-yellow-500 text-black'
    };
    
    const notification = document.createElement('div');
    notification.className = `feature-notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${colors[type] || colors.success}`;
    notification.innerHTML = `
        <div class="flex items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-lg hover:opacity-75">×</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 4000);
}

// ===== INTEGRATION WITH EXISTING IMAGE SYSTEM =====

const originalSelectImageFunc = window.selectImage || function() {};
window.selectImage = function(url) {
    if (window.currentFeatureImageTarget === 'form') {
        document.getElementById('new-feature-image').value = url;
        updateFeatureImagePreview(url);
        window.currentFeatureImageTarget = null;
        closeImagePicker();
        showFeatureNotification('✅ Image selected successfully!', 'success');
        return;
    }
    
    originalSelectImageFunc(url);
};

// Setup image field listener
document.addEventListener('DOMContentLoaded', function() {
    const imageField = document.getElementById('new-feature-image');
    if (imageField) {
        imageField.addEventListener('input', function() {
            if (this.value.trim()) {
                updateFeatureImagePreview(this.value);
            } else {
                clearFeatureImagePreview();
            }
        });
    }
});

// ===== UTILITY FUNCTIONS =====

function importFeatures(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const importData = JSON.parse(e.target.result);
            
            let features = [];
            if (Array.isArray(importData)) {
                features = importData;
            } else if (importData.features && Array.isArray(importData.features)) {
                features = importData.features;
            } else if (importData.data && Array.isArray(importData.data)) {
                features = importData.data;
            } else {
                showFeatureNotification('❌ Invalid file format', 'error');
                return;
            }
            
            const validFeatures = features.filter(feature => 
                feature && 
                feature.title && 
                feature.description &&
                feature.title.trim() &&
                feature.description.trim()
            ).map(feature => ({
                title: feature.title,
                description: feature.description,
                icon: feature.icon || 'fas fa-star',
                image: feature.image || '',
                iconBg: feature.iconBg || feature.iconBackground || '#3b82f6',
                iconColor: feature.iconColor || '#ffffff',
                featureurl: feature.featureurl || feature.url || feature.link || ''
            }));
            
            if (validFeatures.length > 0) {
                if (confirm(`Import ${validFeatures.length} features? This will replace all current features.`)) {
                    featuresData = validFeatures;
                    clearFeatureForm();
                    updateJSONTextarea();
                    updateFeaturesPreview();
                    showFeatureNotification(`📥 Imported ${validFeatures.length} features successfully!`, 'success');
                }
            } else {
                showFeatureNotification('❌ No valid features found in file', 'error');
            }
        } catch (error) {
            //console.error('Import error:', error);
            showFeatureNotification('❌ Error reading file', 'error');
        }
    };
    
    reader.readAsText(file);
    event.target.value = '';
}

// Debug mode
// window.debugFeatures = function() {
//     console.log('=== FEATURES DEBUG INFO (JSON ONLY) ===');
//     console.log('Features Data:', featuresData);
//     console.log('Editing Index:', editingFeatureIndex);
//     console.log('Is Form Submitting:', isFormSubmitting);
//     console.log('JSON Content:', document.getElementById('content-json')?.value || document.getElementById('content')?.value);
//     console.log('=== END DEBUG INFO ===');
// };

// ===== MIGRATION HELPER FOR PIPE-DELIMITED DATA =====
// function migratePipeDataToJSON(pipeData) {
//     //console.log('Migrating pipe data to JSON:', pipeData);
    
//     if (!pipeData || !pipeData.trim()) {
//         console.log('No pipe data to migrate');
//         return;
//     }
    
//     try {
//         const featuresArray = pipeData.split('||').filter(item => item.trim());
//         //console.log('Pipe features found:', featuresArray.length);
        
//         const migratedFeatures = featuresArray.map((featureString, index) => {
//             const parts = featureString.split('|');
//             //console.log(`Migrating feature ${index}:`, parts);
            
//             if (parts.length >= 2 && 
//                 parts[0]?.trim() && 
//                 parts[1]?.trim()) {
                
//                 return {
//                     title: parts[0].trim(),
//                     description: parts[1].trim(),
//                     icon: parts[2]?.trim() || 'fas fa-star',
//                     image: parts[3]?.trim() || '',
//                     iconBg: parts[4]?.trim() || '#3b82f6',
//                     iconColor: parts[5]?.trim() || '#ffffff',
//                     featureurl: parts[6]?.trim() || ''
//                 };
//             }
//             return null;
//         }).filter(feature => feature !== null);
        
//         //console.log('Successfully migrated features:', migratedFeatures.length);
        
//         if (migratedFeatures.length > 0) {
//             featuresData = migratedFeatures;
//             updateJSONTextarea();
//             updateFeaturesPreview();
//             showFeatureNotification(`🔄 Migrated ${migratedFeatures.length} features to JSON format!`, 'success');
//         }
        
//     } catch (error) {
//         console.error('Error migrating pipe data:', error);
//         showFeatureNotification('❌ Error migrating pipe data', 'error');
//     }
// }

// // Auto-migrate pipe data if JSON textarea is empty but pipe data exists
// document.addEventListener('DOMContentLoaded', function() {
//     setTimeout(() => {
//         const jsonTextarea = document.getElementById('content-json');
//         const pipeTextarea = document.getElementById('content');
        
//         if (jsonTextarea && pipeTextarea) {
//             const hasJsonData = jsonTextarea.value.trim();
//             const hasPipeData = pipeTextarea.value.trim();
            
//             if (!hasJsonData && hasPipeData) {
//                 //console.log('Auto-migrating pipe data to JSON format');
//                 migratePipeDataToJSON(hasPipeData);
//             }
//         } else if (pipeTextarea && !jsonTextarea) {
//             // If only pipe textarea exists, use it for JSON
//             const hasPipeData = pipeTextarea.value.trim();
//             if (hasPipeData) {
//                 //console.log('Converting existing pipe data to JSON format in same textarea');
//                 migratePipeDataToJSON(hasPipeData);
//             }
//         }
//     }, 500);
// });

// Export functions for external use
window.featuresManager = {
    addFeature: addNewFeature,
    editFeature: editFeature,
    removeFeature: removeFeature,
    duplicateFeature: duplicateFeature,
    moveFeature: moveFeature,
    clearAll: clearAllFeatures,
    sortAlphabetically: sortFeaturesAlphabetically,
    exportJSON: exportFeaturesAsJSON,
    importFeatures: importFeatures,
    //migratePipeData: migratePipeDataToJSON,
    getData: () => featuresData,
    getJSON: () => {
        return {
            features: featuresData,
            metadata: {
                totalFeatures: featuresData.length,
                lastUpdated: new Date().toISOString(),
                version: '2.0'
            }
        };
    },
    getCount: () => featuresData.length,
    debug: window.debugFeatures
};
//console.log('🎯 Features Management Script Loaded Successfully!');

// Enhanced CTA JavaScript Functions

// Button color setting function
function setButtonColor(inputId, color) {
    document.getElementById(inputId).value = color;
    const textInputId = inputId.replace('_color', '_text');
    if (document.getElementById(textInputId)) {
        document.getElementById(textInputId).value = color;
    }
    
    // Update previews
    updatePrimaryButtonPreview();
    updateSecondButtonPreview();
    updateCombinedPreview();
}

// Toggle second button visibility
function toggleSecondButton() {
    const checkbox = document.getElementById('enable_second_button');
    const fields = document.getElementById('second-button-fields');
    
    if (checkbox.checked) {
        fields.classList.remove('hidden');
        // Set default values if empty
        if (!document.getElementById('button_text_2').value) {
            document.getElementById('button_text_2').value = 'Learn More';
        }
    } else {
        fields.classList.add('hidden');
        // Clear values
        document.getElementById('button_text_2').value = '';
        document.getElementById('button_url_2').value = '';
    }
    
    updateCombinedPreview();
}

// Update primary button preview
function updatePrimaryButtonPreview() {
    const preview = document.getElementById('primary-button-preview');
    const previewCombined = document.getElementById('preview-primary-btn');
    
    const text = document.getElementById('button_text').value || 'Get Started Now';
    const bgColor = document.getElementById('button_bg_color').value;
    const textColor = document.getElementById('button_text_color').value;
    
    [preview, previewCombined].forEach(btn => {
        if (btn) {
            btn.textContent = text;
            btn.style.backgroundColor = bgColor;
            btn.style.color = textColor;
        }
    });
}

// Update secondary button preview
function updateSecondButtonPreview() {
    const preview = document.getElementById('secondary-button-preview');
    const previewCombined = document.getElementById('preview-secondary-btn');
    
    const text = document.getElementById('button_text_2').value || 'Learn More';
    const bgColor = document.getElementById('button_bg_color_2').value;
    const textColor = document.getElementById('button_text_color_2').value;
    const style = document.querySelector('input[name="button_style_2"]:checked')?.value || 'solid';
    
    [preview, previewCombined].forEach(btn => {
        if (btn) {
            btn.textContent = text;
            
            // Apply style
            if (style === 'outline') {
                btn.style.backgroundColor = 'transparent';
                btn.style.color = bgColor;
                btn.style.border = `2px solid ${bgColor}`;
            } else if (style === 'ghost') {
                btn.style.backgroundColor = 'transparent';
                btn.style.color = bgColor;
                btn.style.border = 'none';
            } else {
                btn.style.backgroundColor = bgColor;
                btn.style.color = textColor;
                btn.style.border = 'none';
            }
        }
    });
}

// Update combined preview
function updateCombinedPreview() {
    const container = document.getElementById('buttons-preview-container');
    const alignment = document.getElementById('button_alignment').value;
    const layout = document.getElementById('button_layout').value;
    const hasSecondButton = document.getElementById('enable_second_button').checked && 
                          document.getElementById('button_text_2').value.trim();
    
    // Update alignment
    container.className = `flex items-center ${layout === 'vertical' ? 'flex-col space-y-4' : 'space-x-4'} `;
    if (alignment === 'left') {
        container.className += 'justify-start';
    } else if (alignment === 'right') {
        container.className += 'justify-end';
    } else {
        container.className += 'justify-center';
    }
    
    // Show/hide second button
    const secondBtn = document.getElementById('preview-secondary-btn');
    if (hasSecondButton) {
        if (!secondBtn) {
            // Create second button if it doesn't exist
            const newBtn = document.createElement('button');
            newBtn.type = 'button';
            newBtn.id = 'preview-secondary-btn';
            newBtn.className = 'px-6 py-3 rounded-lg font-medium transition duration-200 shadow-md hover:shadow-lg transform hover:scale-105';
            container.appendChild(newBtn);
        }
        updateSecondButtonPreview();
    } else {
        if (secondBtn) {
            secondBtn.remove();
        }
    }
}

// Event listeners for real-time updates
document.addEventListener('DOMContentLoaded', function() {
    // Primary button listeners
    ['button_text', 'button_bg_color', 'button_text_color'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', updatePrimaryButtonPreview);
        }
    });
    
    // Secondary button listeners
    ['button_text_2', 'button_bg_color_2', 'button_text_color_2'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', updateSecondButtonPreview);
        }
    });
    
    // Layout listeners
    ['button_alignment', 'button_layout'].forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', updateCombinedPreview);
        }
    });
    
// Event listeners for real-time updates - FIXED VERSION
document.addEventListener('DOMContentLoaded', function() {
    //console.log('Setting up button event listeners...');
    
    // FIXED: Primary button listeners with null checks
    const primaryButtonElements = ['button_text', 'button_bg_color', 'button_text_color'];
    primaryButtonElements.forEach(id => {
        const element = document.getElementById(id);
        if (element && typeof updatePrimaryButtonPreview === 'function') {
            element.addEventListener('input', updatePrimaryButtonPreview);
            //console.log(`✅ Added listener to ${id}`);
        } else {
            //console.log(`⚠️ Skipped ${id} - element not found`);
        }
    });
    
    // FIXED: Secondary button listeners with null checks
    const secondaryButtonElements = ['button_text_2', 'button_bg_color_2', 'button_text_color_2'];
    secondaryButtonElements.forEach(id => {
        const element = document.getElementById(id);
        if (element && typeof updateSecondButtonPreview === 'function') {
            element.addEventListener('input', updateSecondButtonPreview);
            //console.log(`✅ Added listener to ${id}`);
        } else {
            //console.log(`⚠️ Skipped ${id} - element not found`);
        }
    });
    
    // FIXED: Layout listeners with null checks
    const layoutElements = ['button_alignment', 'button_layout'];
    layoutElements.forEach(id => {
        const element = document.getElementById(id);
        if (element && typeof updateCombinedPreview === 'function') {
            element.addEventListener('change', updateCombinedPreview);
            //console.log(`✅ Added listener to ${id}`);
        } else {
            //console.log(`⚠️ Skipped ${id} - element not found`);
        }
    });
    
    // FIXED: Color picker synchronization with SAFE null checks
    
    // Primary button background color sync
    const buttonBgColor = document.getElementById('button_bg_color');
    const buttonBgText = document.getElementById('button_bg_text');
    if (buttonBgColor && buttonBgText) {
        buttonBgColor.addEventListener('input', function() {
            buttonBgText.value = this.value;
        });
        //console.log('✅ Primary button background color sync setup');
    } else {
        //console.log('⚠️ Skipped primary button background color sync - elements not found');
    }
    
    // Primary button text color sync
    const buttonTextColor = document.getElementById('button_text_color');
    const buttonTextColorText = document.getElementById('button_text_color_text');
    if (buttonTextColor && buttonTextColorText) {
        buttonTextColor.addEventListener('input', function() {
            buttonTextColorText.value = this.value;
        });
        //console.log('✅ Primary button text color sync setup');
    } else {
        //console.log('⚠️ Skipped primary button text color sync - elements not found');
    }
    
    // Secondary button background color sync
    const buttonBgColor2 = document.getElementById('button_bg_color_2');
    const buttonBgText2 = document.getElementById('button_bg_text_2');
    if (buttonBgColor2 && buttonBgText2) {
        buttonBgColor2.addEventListener('input', function() {
            buttonBgText2.value = this.value;
        });
        //console.log('✅ Secondary button background color sync setup');
    } else {
        //console.log('⚠️ Skipped secondary button background color sync - elements not found');
    }
    
    // Secondary button text color sync
    const buttonTextColor2 = document.getElementById('button_text_color_2');
    const buttonTextColorText2 = document.getElementById('button_text_color_2_text');
    if (buttonTextColor2 && buttonTextColorText2) {
        buttonTextColor2.addEventListener('input', function() {
            buttonTextColorText2.value = this.value;
        });
        //console.log('✅ Secondary button text color sync setup');
    } else {
        //console.log('⚠️ Skipped secondary button text color sync - elements not found');
    }
    
    // FIXED: Content text area listener with null check
    const contentTextarea = document.getElementById('content');
    if (contentTextarea) {
        contentTextarea.addEventListener('input', function() {
            const previewContainer = document.querySelector('#buttons-preview-container');
            if (previewContainer) {
                const preview = previewContainer.previousElementSibling?.querySelector('p');
                if (preview) {
                    preview.textContent = this.value || 'Take action today and transform your business...';
                }
            }
        });
        //console.log('✅ Content textarea listener setup');
    } else {
        //console.log('⚠️ Skipped content textarea listener - element not found');
    }
    
    //console.log('Button event listeners setup completed');
});
    
    // Content text area listener
    // document.getElementById('content').addEventListener('input', function() {
    //     const preview = document.querySelector('#buttons-preview-container').previousElementSibling.querySelector('p');
    //     if (preview) {
    //         preview.textContent = this.value || 'Take action today and transform your business...';
    //     }
    // });
});


// ===== ENHANCED STATISTICS SECTION JAVASCRIPT =====

// ===== COMPLETELY ISOLATED STATS MANAGER =====
// Wrap everything in IIFE to prevent conflicts
(function() {
    'use strict';
    
    // Private variables - no conflicts possible
    let statsData = [];
    let editingStatIndex = -1;
    let animationSpeed = 'medium';
    let isStatsPageActive = false;
    let initializationComplete = false;
    
    // Check if we're on stats page
    function isStatsPage() {
        return document.getElementById('stats-preview') && 
               document.getElementById('stats-display') && 
               document.getElementById('content');
    }
    
    // Initialize only once and only for stats
    function initializeStats() {
        if (initializationComplete || !isStatsPage()) {
            return;
        }
        
        //console.log('🎯 Initializing ISOLATED Stats Manager');
        isStatsPageActive = true;
        initializationComplete = true;
        
        // Load and display immediately
        loadStatsData();
        renderStatsDisplay();
        setupFormListeners();
        setupColorSync();
        injectStatsCSS();
        
        //console.log('✅ Stats Manager Ready');
    }
    
    // Load stats data from textarea
    function loadStatsData() {
        const textarea = document.getElementById('content');
        if (!textarea) return;
        
        const content = textarea.value.trim();
        statsData = [];
        
        if (content) {
            try {
                // Handle both old format (Number|Label) and new format
                const statPairs = content.split('|');
                
                // Group pairs
                for (let i = 0; i < statPairs.length; i += 2) {
                    if (statPairs[i] && statPairs[i + 1]) {
                        statsData.push({
                            number: statPairs[i].trim(),
                            label: statPairs[i + 1].trim(),
                            icon: '',
                            color: '#3b82f6',
                            animation: 'countup'
                        });
                    }
                }
            } catch (e) {
                //console.warn('Error parsing stats:', e);
                statsData = [];
            }
        }
        
        //console.log(`📊 Loaded ${statsData.length} stats`);
        updateCounters();
    }
    
    // Update counters
    function updateCounters() {
        const countEl = document.getElementById('stats-count');
        const lengthEl = document.getElementById('stats-textarea-length');
        const textarea = document.getElementById('content');
        
        if (countEl) countEl.textContent = statsData.length;
        if (lengthEl && textarea) lengthEl.textContent = textarea.value.length;
        
        updateFormatDisplay();
    }
    
    // Update format display
    function updateFormatDisplay() {
        const formatElement = document.getElementById('stats-format-display');
        if (formatElement && statsData.length > 0) {
            const formatInfo = `📊 ${statsData.length} statistic${statsData.length !== 1 ? 's' : ''} configured`;
            formatElement.innerHTML = `<span class="text-green-600 font-medium">${formatInfo}</span>`;
        } else if (formatElement) {
            formatElement.innerHTML = `<span class="text-gray-500">📊 Format: Number|Label (pairs separated by |)</span>`;
        }
    }
    
    // Render stats display
    function renderStatsDisplay() {
        const container = document.getElementById('stats-display');
        if (!container) return;
        
        if (statsData.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 col-span-full">
                    <div class="text-gray-400 mb-4">
                        <i class="fas fa-chart-line text-4xl"></i>
                    </div>
                    <p class="text-gray-500 text-lg mb-2">No statistics yet</p>
                    <p class="text-gray-400 text-sm">Add your first statistic using the form below</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = statsData.map((stat, index) => {
            const isEditing = editingStatIndex === index;
            const editingClass = isEditing ? 'ring-2 ring-orange-400 border-orange-300' : 'border-gray-200';
            
            return `
                <div class="bg-white rounded-lg shadow-md ${editingClass} p-6 text-center stat-card relative overflow-hidden" data-stat-index="${index}">
                    ${isEditing ? `
                        <div class="absolute -top-2 -right-2 bg-orange-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold animate-pulse z-10">
                            ✏️
                        </div>
                    ` : ''}
                    
                    <!-- Animated Background -->
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-purple-50 opacity-50"></div>
                    
                    <!-- Content -->
                    <div class="relative z-10">
                        ${stat.icon ? `
                            <div class="mb-3">
                                <i class="${escapeHtml(stat.icon)} text-2xl" style="color: ${escapeHtml(stat.color)};"></i>
                            </div>
                        ` : ''}
                        
                        <div class="text-3xl font-bold mb-2 stat-counter" 
                             style="color: ${escapeHtml(stat.color)};" 
                             data-target="${escapeHtml(stat.number)}" 
                             data-animation="${escapeHtml(stat.animation)}">
                            ${escapeHtml(stat.number)}
                        </div>
                        
                        <div class="text-gray-600 font-medium text-sm uppercase tracking-wide">
                            ${escapeHtml(stat.label)}
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex gap-2 mt-4 justify-center">
                            <button onclick="IsolatedStats.edit(${index})" 
                                    class="bg-yellow-500 text-white px-3 py-1 rounded-lg hover:bg-yellow-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="IsolatedStats.duplicate(${index})" 
                                    class="bg-green-500 text-white px-3 py-1 rounded-lg hover:bg-green-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button onclick="IsolatedStats.move(${index}, -1)" 
                                    class="bg-gray-500 text-white px-3 py-1 rounded-lg hover:bg-gray-600 transition-colors duration-200 text-xs font-medium" 
                                    ${index === 0 ? 'disabled style="opacity:0.5"' : ''}>
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <button onclick="IsolatedStats.move(${index}, 1)" 
                                    class="bg-gray-500 text-white px-3 py-1 rounded-lg hover:bg-gray-600 transition-colors duration-200 text-xs font-medium" 
                                    ${index === statsData.length - 1 ? 'disabled style="opacity:0.5"' : ''}>
                                <i class="fas fa-arrow-right"></i>
                            </button>
                            <button onclick="IsolatedStats.remove(${index})" 
                                    class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 transition-colors duration-200 text-xs font-medium">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        updateCounters();
        setupScrollAnimations();
        //console.log(`🎨 Rendered ${statsData.length} stats`);
    }
    
    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Save to textarea without triggering conflicts
    function saveToTextarea() {
        const textarea = document.getElementById('content');
        if (!textarea) return;
        
        // Keep the simple format for backward compatibility
        const formatted = statsData.map(stat => `${stat.number}|${stat.label}`).join('|');
        
        // Temporarily disable any existing listeners
        const oldValue = textarea.value;
        textarea.value = formatted;
        
        // Only trigger change if value actually changed
        if (oldValue !== formatted) {
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        updateCounters();
        //console.log('💾 Saved stats to textarea');
    }
    
    // Animation functions
    function animateCounter(element, target, speed) {
        const duration = speed === 'fast' ? 1000 : speed === 'slow' ? 3000 : 2000;
        const targetNum = parseInt(target.replace(/[^\d]/g, '')) || 0;
        const suffix = target.replace(/\d/g, '');
        const increment = targetNum / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= targetNum) {
                element.textContent = target;
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current) + suffix;
            }
        }, 16);
    }
    
    // Setup scroll animations
    function setupScrollAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target.querySelector('.stat-counter');
                    if (counter) {
                        const target = counter.getAttribute('data-target');
                        const animationType = counter.getAttribute('data-animation');
                        
                        if (animationType === 'countup' && /\d/.test(target)) {
                            animateCounter(counter, target, animationSpeed);
                        }
                        
                        // Add visual effects
                        entry.target.style.transform = 'translateY(0)';
                        entry.target.style.opacity = '1';
                    }
                    
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.5
        });
        
        // Observe all stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.style.transform = 'translateY(20px)';
            card.style.opacity = '0';
            card.style.transition = 'all 0.6s ease-out';
            observer.observe(card);
        });
    }
    
    // Setup form listeners
    function setupFormListeners() {
        const formInputs = ['new-stat-number', 'new-stat-label', 'new-stat-icon', 'new-stat-animation'];
        
        formInputs.forEach(inputId => {
            const element = document.getElementById(inputId);
            if (element) {
                element.addEventListener('input', function() {
                    if (editingStatIndex >= 0) {
                        updateEditingStatInTextarea();
                    }
                });
            }
        });
        
        // Animation speed listener
        const speedSelect = document.getElementById('animation-speed');
        if (speedSelect) {
            speedSelect.addEventListener('change', function() {
                animationSpeed = this.value;
            });
        }
    }
    
    // Setup color synchronization
    function setupColorSync() {
        const colorInput = document.getElementById('new-stat-color');
        const colorText = document.getElementById('new-stat-color-text');
        
        if (colorInput && colorText) {
            colorInput.addEventListener('input', function() {
                colorText.value = this.value;
                if (editingStatIndex >= 0) {
                    updateEditingStatInTextarea();
                }
            });
            
            colorText.addEventListener('input', function() {
                if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                    colorInput.value = this.value;
                    if (editingStatIndex >= 0) {
                        updateEditingStatInTextarea();
                    }
                }
            });
        }
    }
    
    function updateEditingStatInTextarea() {
        if (editingStatIndex < 0 || !statsData[editingStatIndex]) return;
        
        // Get current form values
        const number = document.getElementById('new-stat-number')?.value.trim() || '';
        const label = document.getElementById('new-stat-label')?.value.trim() || '';
        const icon = document.getElementById('new-stat-icon')?.value.trim() || '';
        const color = document.getElementById('new-stat-color')?.value.trim() || '#3b82f6';
        const animation = document.getElementById('new-stat-animation')?.value.trim() || 'countup';
        
        // Update the stats data array
        statsData[editingStatIndex] = {
            number: number || statsData[editingStatIndex].number,
            label: label || statsData[editingStatIndex].label,
            icon: icon || statsData[editingStatIndex].icon,
            color: color || statsData[editingStatIndex].color,
            animation: animation || statsData[editingStatIndex].animation
        };
        
        // Update textarea and preview
        saveToTextarea();
        renderStatsDisplay();
    }
    
    // Notification system
    function showNotification(message, type = 'success') {
        const existing = document.querySelector('.isolated-stats-notification');
        if (existing) existing.remove();
        
        const colors = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            info: 'bg-blue-500 text-white',
            warning: 'bg-yellow-500 text-black'
        };
        
        const notification = document.createElement('div');
        notification.className = `isolated-stats-notification fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${colors[type] || colors.success}`;
        notification.innerHTML = `
            <div class="flex items-center">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-lg hover:opacity-75">×</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 4000);
    }
    
    // Inject CSS for stats animations
    function injectStatsCSS() {
        if (document.querySelector('#isolated-stats-css')) return;
        
        const style = document.createElement('style');
        style.id = 'isolated-stats-css';
        style.textContent = `
            /* Enhanced Stats Animations */
            @keyframes countUp {
                from {
                    opacity: 0;
                    transform: translateY(30px) scale(0.8);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(40px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            @keyframes bounceIn {
                0% {
                    opacity: 0;
                    transform: scale(0.3) translateY(50px);
                }
                50% {
                    opacity: 1;
                    transform: scale(1.1) translateY(-10px);
                }
                70% {
                    transform: scale(0.95) translateY(5px);
                }
                100% {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
            
            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                }
                50% {
                    transform: scale(1.05);
                }
            }
            
            .stat-card {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .stat-card:hover {
                transform: translateY(-8px) scale(1.02);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            }
            
            .stat-counter {
                transition: all 0.3s ease;
            }
            
            .stat-counter:hover {
                transform: scale(1.1);
            }
            
            @media (max-width: 768px) {
                .stat-card {
                    margin-bottom: 1rem;
                }
                .stat-counter {
                    font-size: 2rem;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Public API functions
    window.IsolatedStats = {
        add: function() {
            const number = document.getElementById('new-stat-number')?.value.trim() || '';
            const label = document.getElementById('new-stat-label')?.value.trim() || '';
            const icon = document.getElementById('new-stat-icon')?.value.trim() || '';
            const color = document.getElementById('new-stat-color')?.value.trim() || '#3b82f6';
            const animation = document.getElementById('new-stat-animation')?.value.trim() || 'countup';
            
            if (!number || !label) {
                showNotification('❌ Please enter both number and label.', 'error');
                return;
            }
            
            const statData = { number, label, icon, color, animation };
            
            if (editingStatIndex >= 0) {
                statsData[editingStatIndex] = statData;
                showNotification(`✅ Statistic "${label}" updated!`, 'success');
            } else {
                statsData.push(statData);
                showNotification(`🎉 New statistic "${label}" added!`, 'success');
            }
            
            this.clearForm();
            saveToTextarea();
            renderStatsDisplay();
        },
        
        edit: function(index) {
            if (!statsData[index]) {
                showNotification('❌ Statistic not found!', 'error');
                return;
            }
            
            const stat = statsData[index];
            editingStatIndex = index;
            
            const numberField = document.getElementById('new-stat-number');
            const labelField = document.getElementById('new-stat-label');
            const iconField = document.getElementById('new-stat-icon');
            const colorField = document.getElementById('new-stat-color');
            const colorTextField = document.getElementById('new-stat-color-text');
            const animationField = document.getElementById('new-stat-animation');
            
            if (numberField) numberField.value = stat.number || '';
            if (labelField) labelField.value = stat.label || '';
            if (iconField) iconField.value = stat.icon || '';
            if (colorField) colorField.value = stat.color || '#3b82f6';
            if (colorTextField) colorTextField.value = stat.color || '#3b82f6';
            if (animationField) animationField.value = stat.animation || 'countup';
            
            // Update form UI
            const addButton = document.querySelector('button[onclick*="IsolatedStats.add"]');
            if (addButton) {
                addButton.innerHTML = '<i class="fas fa-save mr-2"></i>💾 Update Statistic';
                addButton.className = addButton.className.replace('bg-blue-500', 'bg-green-500').replace('hover:bg-blue-600', 'hover:bg-green-600');
            }
            
            const cancelButton = document.querySelector('button[onclick*="IsolatedStats.cancel"]');
            if (cancelButton) cancelButton.style.display = 'inline-block';
            
            renderStatsDisplay();
            showNotification(`✏️ Now editing: "${stat.label}"`, 'info');
        },
        
        cancel: function() {
            this.clearForm();
            showNotification('✖️ Edit cancelled', 'info');
        },
        
        clearForm: function() {
            const fields = ['new-stat-number', 'new-stat-label', 'new-stat-icon'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) field.value = '';
            });
            
            const colorField = document.getElementById('new-stat-color');
            const colorTextField = document.getElementById('new-stat-color-text');
            const animationField = document.getElementById('new-stat-animation');
            
            if (colorField) colorField.value = '#3b82f6';
            if (colorTextField) colorTextField.value = '#3b82f6';
            if (animationField) animationField.value = 'countup';
            
            editingStatIndex = -1;
            
            const addButton = document.querySelector('button[onclick*="IsolatedStats.add"]');
            if (addButton) {
                addButton.innerHTML = '<i class="fas fa-plus mr-2"></i>➕ Add Statistic';
                addButton.className = addButton.className.replace('bg-green-500', 'bg-blue-500').replace('hover:bg-green-600', 'hover:bg-blue-600');
            }
            
            const cancelButton = document.querySelector('button[onclick*="IsolatedStats.cancel"]');
            if (cancelButton) cancelButton.style.display = 'none';
            
            renderStatsDisplay();
        },
        
        duplicate: function(index) {
            if (!statsData[index]) return;
            
            const stat = { ...statsData[index] };
            stat.label = stat.label + ' (Copy)';
            
            statsData.splice(index + 1, 0, stat);
            saveToTextarea();
            renderStatsDisplay();
            showNotification('📋 Statistic duplicated successfully!', 'success');
        },
        
        remove: function(index) {
            if (!statsData[index]) return;
            
            const statLabel = statsData[index].label;
            if (confirm(`🗑️ Are you sure you want to delete "${statLabel}"?`)) {
                statsData.splice(index, 1);
                
                if (editingStatIndex === index) {
                    this.clearForm();
                } else if (editingStatIndex > index) {
                    editingStatIndex--;
                }
                
                saveToTextarea();
                renderStatsDisplay();
                showNotification(`🗑️ Statistic "${statLabel}" deleted!`, 'success');
            }
        },
        
        move: function(index, direction) {
            const newIndex = index + direction;
            if (newIndex >= 0 && newIndex < statsData.length) {
                [statsData[index], statsData[newIndex]] = [statsData[newIndex], statsData[index]];
                
                if (editingStatIndex === index) {
                    editingStatIndex = newIndex;
                } else if (editingStatIndex === newIndex) {
                    editingStatIndex = index;
                }
                
                saveToTextarea();
                renderStatsDisplay();
            }
        },
        
        clearAll: function() {
            if (confirm('🚨 Are you sure you want to delete ALL statistics?')) {
                statsData = [];
                this.clearForm();
                saveToTextarea();
                renderStatsDisplay();
                showNotification('🗑️ All statistics cleared!', 'success');
            }
        },
        
        previewAnimation: function() {
            const speed = document.getElementById('animation-speed')?.value || 'medium';
            const counters = document.querySelectorAll('.stat-counter');
            
            counters.forEach((counter, index) => {
                const target = counter.getAttribute('data-target');
                const animationType = counter.getAttribute('data-animation');
                
                // Reset counter
                counter.style.opacity = '0';
                counter.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    counter.style.transition = 'all 0.6s ease-out';
                    counter.style.opacity = '1';
                    counter.style.transform = 'translateY(0)';
                    
                    // Animate number if it contains digits
                    if (animationType === 'countup' && /\d/.test(target)) {
                        animateCounter(counter, target, speed);
                    } else {
                        counter.textContent = target;
                    }
                }, index * 200);
            });
            
            showNotification('🎬 Animation preview started!', 'info');
        },
        
        addSampleStats: function(type) {
            let samples = [];
            
            switch(type) {
                case 'business':
                    samples = [
                        { number: '500+', label: 'Happy Clients', icon: 'fas fa-users', color: '#10b981' },
                        { number: '1000+', label: 'Projects Done', icon: 'fas fa-project-diagram', color: '#3b82f6' },
                        { number: '24/7', label: 'Support', icon: 'fas fa-headset', color: '#f59e0b' },
                        { number: '99%', label: 'Satisfaction', icon: 'fas fa-star', color: '#ef4444' }
                    ];
                    break;
                case 'tech':
                    samples = [
                        { number: '10M+', label: 'Lines of Code', icon: 'fas fa-code', color: '#8b5cf6' },
                        { number: '99.9%', label: 'Uptime', icon: 'fas fa-server', color: '#10b981' },
                        { number: '50+', label: 'Integrations', icon: 'fas fa-plug', color: '#f59e0b' },
                        { number: '< 100ms', label: 'Response Time', icon: 'fas fa-bolt', color: '#ef4444' }
                    ];
                    break;
                case 'service':
                    samples = [
                        { number: '5★', label: 'Average Rating', icon: 'fas fa-star', color: '#f59e0b' },
                        { number: '24/7', label: 'Availability', icon: 'fas fa-clock', color: '#3b82f6' },
                        { number: '100+', label: 'Countries Served', icon: 'fas fa-globe', color: '#10b981' },
                        { number: '1M+', label: 'Customers', icon: 'fas fa-heart', color: '#ef4444' }
                    ];
                    break;
            }
            
            if (confirm(`Add ${samples.length} sample ${type} statistics?`)) {
                statsData = [...statsData, ...samples];
                saveToTextarea();
                renderStatsDisplay();
                showNotification(`🎉 Added ${samples.length} sample ${type} statistics!`, 'success');
            }
        }
    };
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeStats);
    } else {
        // DOM already loaded
        setTimeout(initializeStats, 100);
    }
    
    // Also try to initialize periodically until successful
    const initInterval = setInterval(() => {
        if (isStatsPage() && !initializationComplete) {
            initializeStats();
        }
        if (initializationComplete) {
            clearInterval(initInterval);
        }
    }, 500);
    
    // Clear interval after 10 seconds to avoid infinite checking
    setTimeout(() => clearInterval(initInterval), 10000);
    
    //console.log('🚀 Isolated Stats Manager Script Loaded');
    
})();

// Legacy function compatibility
window.addNewStat = function() { 
    if (window.IsolatedStats) window.IsolatedStats.add(); 
};
window.editStat = function(index) { 
    if (window.IsolatedStats) window.IsolatedStats.edit(index); 
};
window.cancelStatEdit = function() { 
    if (window.IsolatedStats) window.IsolatedStats.cancel(); 
};
window.duplicateStat = function(index) { 
    if (window.IsolatedStats) window.IsolatedStats.duplicate(index); 
};
window.removeStat = function(index) { 
    if (window.IsolatedStats) window.IsolatedStats.remove(index); 
};
window.moveStat = function(index, direction) { 
    if (window.IsolatedStats) window.IsolatedStats.move(index, direction); 
};
window.clearAllStats = function() { 
    if (window.IsolatedStats) window.IsolatedStats.clearAll(); 
};
window.previewAnimation = function() { 
    if (window.IsolatedStats) window.IsolatedStats.previewAnimation(); 
};
window.addSampleStats = function(type) { 
    if (window.IsolatedStats) window.IsolatedStats.addSampleStats(type); 
};

const statsCSS = `
<style>
/* Enhanced Stats Animations */
@keyframes countUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.8);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes bounceIn {
    0% {
        opacity: 0;
        transform: scale(0.3) translateY(50px);
    }
    50% {
        opacity: 1;
        transform: scale(1.1) translateY(-10px);
    }
    70% {
        transform: scale(0.95) translateY(5px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

@keyframes glow {
    0%, 100% {
        box-shadow: 0 0 5px currentColor;
    }
    50% {
        box-shadow: 0 0 20px currentColor, 0 0 30px currentColor;
    }
}

.stat-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.stat-counter {
    transition: all 0.3s ease;
}

.stat-counter:hover {
    transform: scale(1.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .stat-card {
        margin-bottom: 1rem;
    }
    
    .stat-counter {
        font-size: 2rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .stat-card {
        background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
        color: white;
    }
}
</style>
`;

// Inject CSS
if (!document.querySelector('#stats-section-styles')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'stats-section-styles';
    styleElement.textContent = statsCSS.replace(/<\/?style>/g, '');
    document.head.appendChild(styleElement);
}

// Simplified Styling JavaScript Functions (No Image Upload)

// Background type switching
function switchBackgroundType(type) {
    //console.log('Switching background type to:', type);
    
    // Check if background type input exists (only in sections with styling)
    const backgroundTypeInput = safeGetElement('background_type');
    if (!backgroundTypeInput) {
        //console.log('Background type input not found - skipping styling update');
        return;
    }
    
    // Update hidden input
    backgroundTypeInput.value = type;
    
    // Update tab buttons - only if they exist
    const tabs = ['solid', 'gradient'];
    tabs.forEach(tabType => {
        const tabButton = safeGetElement(`tab-${tabType}`);
        if (tabButton) {
            if (tabType === type) {
                tabButton.classList.add('active');
            } else {
                tabButton.classList.remove('active');
            }
        }
    });
    
    // Show/hide options - only if they exist
    tabs.forEach(optionType => {
        const optionDiv = safeGetElement(`${optionType}-options`);
        if (optionDiv) {
            if (optionType === type) {
                optionDiv.classList.remove('hidden');
            } else {
                optionDiv.classList.add('hidden');
            }
        }
    });
    
    // Update preview if function exists
    if (typeof updateStylingPreview === 'function') {
        updateStylingPreview();
    }
}

// Background color functions
function setBackgroundColor(color) {
    document.getElementById('background_color').value = color;
    document.getElementById('background_color_picker').value = color;
    updateStylingPreview();
}

function setTextColor(color) {
    document.getElementById('text_color').value = color;
    document.getElementById('text_color_picker').value = color;
    updateStylingPreview();
}

// Gradient functions
function setGradient(startColor, endColor) {
    document.getElementById('gradient_start').value = startColor;
    document.getElementById('gradient_end').value = endColor;
    document.getElementById('gradient_start_picker').value = startColor;
    document.getElementById('gradient_end_picker').value = endColor;
    updateGradientPreview();
    updateStylingPreview();
}

function updateGradientPreview() {
    const start = document.getElementById('gradient_start').value;
    const end = document.getElementById('gradient_end').value;
    const direction = document.getElementById('gradient_direction').value;
    
    const gradientPreview = document.getElementById('gradient-preview');
    if (gradientPreview) {
        gradientPreview.style.background = `linear-gradient(${direction}, ${start}, ${end})`;
    }
}

// Update live preview
function updateStylingPreview() {
    const preview = document.getElementById('styling-preview');
    if (!preview) return;
    
    const contentText = preview.querySelector('h3');
    const contentParagraph = preview.querySelector('p');
    
    // Get background type
    const backgroundType = document.getElementById('background_type').value;
    
    switch (backgroundType) {
        case 'gradient':
            const direction = document.getElementById('gradient_direction').value;
            const start = document.getElementById('gradient_start').value;
            const end = document.getElementById('gradient_end').value;
            const gradientTextColor = document.getElementById('gradient_text_color').value;
            
            preview.style.background = `linear-gradient(${direction}, ${start}, ${end})`;
            if (contentText) contentText.style.color = gradientTextColor;
            if (contentParagraph) contentParagraph.style.color = gradientTextColor;
            break;
            
        default: // solid
            const bgColor = document.getElementById('background_color').value;
            const textColor = document.getElementById('text_color').value;
            
            preview.style.background = bgColor;
            if (contentText) contentText.style.color = textColor;
            if (contentParagraph) contentParagraph.style.color = textColor;
            break;
    }
}

// Sync color pickers with text inputs
function syncColorPickers() {
    // Background color sync
    const bgColorPicker = document.getElementById('background_color_picker');
    const bgColorText = document.getElementById('background_color');
    
    if (bgColorPicker && bgColorText) {
        bgColorPicker.addEventListener('input', function() {
            bgColorText.value = this.value;
            updateStylingPreview();
        });
        
        bgColorText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                bgColorPicker.value = this.value;
                updateStylingPreview();
            }
        });
    }
    
    // Text color sync
    const textColorPicker = document.getElementById('text_color_picker');
    const textColorText = document.getElementById('text_color');
    
    if (textColorPicker && textColorText) {
        textColorPicker.addEventListener('input', function() {
            textColorText.value = this.value;
            updateStylingPreview();
        });
        
        textColorText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                textColorPicker.value = this.value;
                updateStylingPreview();
            }
        });
    }
    
    // Gradient start color sync
    const gradientStartPicker = document.getElementById('gradient_start_picker');
    const gradientStartText = document.getElementById('gradient_start');
    
    if (gradientStartPicker && gradientStartText) {
        gradientStartPicker.addEventListener('input', function() {
            gradientStartText.value = this.value;
            updateGradientPreview();
            updateStylingPreview();
        });
        
        gradientStartText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                gradientStartPicker.value = this.value;
                updateGradientPreview();
                updateStylingPreview();
            }
        });
    }
    
    // Gradient end color sync
    const gradientEndPicker = document.getElementById('gradient_end_picker');
    const gradientEndText = document.getElementById('gradient_end');
    
    if (gradientEndPicker && gradientEndText) {
        gradientEndPicker.addEventListener('input', function() {
            gradientEndText.value = this.value;
            updateGradientPreview();
            updateStylingPreview();
        });
        
        gradientEndText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                gradientEndPicker.value = this.value;
                updateGradientPreview();
                updateStylingPreview();
            }
        });
    }
    
    // Gradient text color sync
    const gradientTextColorPicker = document.getElementById('gradient_text_color_picker');
    const gradientTextColorText = document.getElementById('gradient_text_color');
    
    if (gradientTextColorPicker && gradientTextColorText) {
        gradientTextColorPicker.addEventListener('input', function() {
            gradientTextColorText.value = this.value;
            updateStylingPreview();
        });
        
        gradientTextColorText.addEventListener('input', function() {
            if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                gradientTextColorPicker.value = this.value;
                updateStylingPreview();
            }
        });
    }
}

// Event listeners for real-time updates
document.addEventListener('DOMContentLoaded', function() {
    // Initialize background type based on saved value
    const backgroundType = document.getElementById('background_type')?.value || 'solid';
    switchBackgroundType(backgroundType);
    
    // Setup color picker synchronization
    syncColorPickers();
    
    // Gradient direction listener
    const gradientDirection = document.getElementById('gradient_direction');
    if (gradientDirection) {
        gradientDirection.addEventListener('change', function() {
            updateGradientPreview();
            updateStylingPreview();
        });
    }
    
    // Initial preview update
    setTimeout(function() {
        updateGradientPreview();
        updateStylingPreview();
    }, 100);
});

function safeGetElement(id) {
    return document.getElementById(id);
}

// Helper function to check if element exists before using
function elementExists(id) {
    return document.getElementById(id) !== null;
}


    </script>
</body>
</html>