<?php
require_once '../config.php';
require_once '../function.php';

// Include reCAPTCHA helper functions
// Make sure to create the recaptcha.php file or add these functions to your config.php
if (file_exists('recaptcha.php')) {
    require_once 'recaptcha.php';
} else {
    // If recaptcha.php doesn't exist, define the functions here
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
    
    function generateRecaptchaHtml() {
        if (!isRecaptchaEnabled()) {
            return '';
        }
        
        $siteKey = getRecaptchaSiteKey();
        if (empty($siteKey)) {
            return '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">reCAPTCHA is enabled but not properly configured.</div>';
        }
        
        return '
        <div class="mb-4">
            <div class="g-recaptcha" data-sitekey="' . htmlspecialchars($siteKey) . '"></div>
        </div>';
    }
    
    function getRecaptchaScript() {
        if (!isRecaptchaEnabled()) {
            return '';
        }
        
        return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    }
}

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(BASE_URL . '/admin/');
}

$error = '';

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        if (empty($username) || empty($password)) {
            throw new Exception('Please fill in all fields');
        }
        
        // Verify reCAPTCHA if enabled
        if (isRecaptchaEnabled()) {
            $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
            
            if (empty($recaptchaResponse)) {
                throw new Exception('Please complete the reCAPTCHA verification');
            }
            
            if (!verifyRecaptcha($recaptchaResponse)) {
                throw new Exception('reCAPTCHA verification failed. Please try again.');
            }
        }
        
        // Check credentials
        $stmt = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Update last login time if the column exists
            try {
                $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$admin['id']]);
            } catch (Exception $e) {
                // Column might not exist, ignore this error
                error_log("Could not update last_login: " . $e->getMessage());
            }
            
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            redirect(BASE_URL . '/admin/');
        } else {
            throw new Exception('Invalid username or password');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Login error: " . $error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?= getRecaptchaScript() ?>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center bg-blue-100 rounded-full">
                <i class="fas fa-lock text-blue-600 text-xl"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Admin Login
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Sign in to access the admin panel
                <?php if (isRecaptchaEnabled()): ?>
                    <br><span class="text-xs text-green-600"><i class="fas fa-shield-alt mr-1"></i>Protected by reCAPTCHA</span>
                <?php endif; ?>
            </p>
        </div>
        
        <form class="mt-8 space-y-6" method="POST">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input id="username" name="username" type="text" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                           placeholder="Enter your password">
                </div>
                
                <!-- ADDED: Forgot Password Link -->
                <div class="flex items-center justify-between">
                    <div class="text-sm">
                        <a href="forgot_password.php" class="font-medium text-blue-600 hover:text-blue-500 transition-colors duration-200">
                            <i class="fas fa-key mr-1"></i>Forgot your password?
                        </a>
                    </div>
                </div>
            </div>

            <!-- reCAPTCHA Integration -->
            <?= generateRecaptchaHtml() ?>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-sign-in-alt text-blue-500 group-hover:text-blue-400"></i>
                    </span>
                    Sign in
                </button>
            </div>
            
            <div class="text-center">
                <a href="<?= BASE_URL ?>/" class="text-blue-600 hover:text-blue-500 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Back to Website
                </a>
            </div>
        </form>
        
        <!-- reCAPTCHA Status -->
        <?php if (isRecaptchaEnabled()): ?>
            <div class="bg-green-50 border border-green-200 rounded-md p-3">
                <div class="flex items-center">
                    <i class="fas fa-shield-alt text-green-600 mr-2"></i>
                    <p class="text-sm text-green-800">
                        This login is protected by Google reCAPTCHA
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>