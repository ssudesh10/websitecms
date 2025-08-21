<?php
/**
 * reCAPTCHA Helper Functions
 * Save this as "recaptcha.php" in your admin directory
 * Include this file in your config.php: require_once 'recaptcha.php';
 */

/**
 * Check if reCAPTCHA is enabled
 */
function isRecaptchaEnabled() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'recaptcha_enabled'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result && $result['setting_value'] === '1';
    } catch (Exception $e) {
        error_log("Error checking reCAPTCHA status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get reCAPTCHA site key
 */
function getRecaptchaSiteKey() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'recaptcha_site_key'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : '';
    } catch (Exception $e) {
        error_log("Error getting reCAPTCHA site key: " . $e->getMessage());
        return '';
    }
}

/**
 * Get reCAPTCHA secret key
 */
function getRecaptchaSecretKey() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'recaptcha_secret_key'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : '';
    } catch (Exception $e) {
        error_log("Error getting reCAPTCHA secret key: " . $e->getMessage());
        return '';
    }
}

/**
 * Verify reCAPTCHA response
 */
function verifyRecaptcha($recaptchaResponse) {
    if (empty($recaptchaResponse)) {
        return false;
    }
    
    $secretKey = getRecaptchaSecretKey();
    if (empty($secretKey)) {
        error_log("reCAPTCHA secret key is not configured");
        return false;
    }
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secretKey,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === false) {
        error_log("Failed to verify reCAPTCHA: Unable to connect to Google");
        return false;
    }
    
    $resultJson = json_decode($result, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to parse reCAPTCHA response: " . json_last_error_msg());
        return false;
    }
    
    if (!isset($resultJson['success'])) {
        error_log("Invalid reCAPTCHA response format");
        return false;
    }
    
    if (!$resultJson['success']) {
        $errors = isset($resultJson['error-codes']) ? implode(', ', $resultJson['error-codes']) : 'Unknown error';
        error_log("reCAPTCHA verification failed: " . $errors);
        return false;
    }
    
    return true;
}

/**
 * Generate reCAPTCHA HTML for login form
 */
function generateRecaptchaHtml() {
    if (!isRecaptchaEnabled()) {
        return '';
    }
    
    $siteKey = getRecaptchaSiteKey();
    if (empty($siteKey)) {
        return '<div class="text-red-600 text-sm mb-4">reCAPTCHA is enabled but not properly configured.</div>';
    }
    
    return '
    <div class="mb-4">
        <div class="g-recaptcha" data-sitekey="' . htmlspecialchars($siteKey) . '"></div>
    </div>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}

/**
 * Generate reCAPTCHA script tag only (for custom implementations)
 */
function getRecaptchaScript() {
    if (!isRecaptchaEnabled()) {
        return '';
    }
    
    return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}

/**
 * Generate reCAPTCHA div only (for custom implementations)
 */
function getRecaptchaDiv() {
    if (!isRecaptchaEnabled()) {
        return '';
    }
    
    $siteKey = getRecaptchaSiteKey();
    if (empty($siteKey)) {
        return '<div class="text-red-600 text-sm">reCAPTCHA configuration error</div>';
    }
    
    return '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($siteKey) . '"></div>';
}
?>