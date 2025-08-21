<?php
require_once '../config.php';
require_once '../function.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(BASE_URL . '/admin/');
}

$message = '';
$error = '';

/**
 * Download and setup PHPMailer automatically
 */
function setupPHPMailer() {
    $phpmailer_dir = '../PHPMailer';
    
    // Check if PHPMailer directory exists
    if (!is_dir($phpmailer_dir)) {
        mkdir($phpmailer_dir, 0755, true);
    }
    
    // PHPMailer files to download
    $files = [
        'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
        'SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
        'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
    ];
    
    foreach ($files as $filename => $url) {
        $filepath = $phpmailer_dir . '/' . $filename;
        
        // Download if file doesn't exist
        if (!file_exists($filepath)) {
            $content = file_get_contents($url);
            if ($content !== false) {
                file_put_contents($filepath, $content);
                //error_log("Downloaded: " . $filename);
            } else {
                //error_log("Failed to download: " . $filename);
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Load PHPMailer classes
 */
function loadPHPMailer() {
    // Try different locations for PHPMailer
    $phpmailer_paths = [
        // Composer autoload
        '../vendor/autoload.php',
        '../../vendor/autoload.php',
        
        // Manual download locations
        '../PHPMailer/PHPMailer.php',
        '../libraries/PHPMailer/src/PHPMailer.php',
        '../includes/PHPMailer/src/PHPMailer.php'
    ];
    
    foreach ($phpmailer_paths as $path) {
        if (file_exists($path)) {
            if (strpos($path, 'autoload.php') !== false) {
                // Composer autoload
                require_once $path;
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    return true;
                }
            } elseif (strpos($path, 'PHPMailer.php') !== false) {
                // Manual include
                $dir = dirname($path);
                require_once $path;
                require_once $dir . '/SMTP.php';
                require_once $dir . '/Exception.php';
                
                // Define classes in global namespace for older versions
                if (!class_exists('PHPMailer\PHPMailer\PHPMailer') && class_exists('PHPMailer')) {
                    class_alias('PHPMailer', 'PHPMailer\PHPMailer\PHPMailer');
                    class_alias('SMTP', 'PHPMailer\PHPMailer\SMTP');
                    class_alias('phpmailerException', 'PHPMailer\PHPMailer\Exception');
                }
                return true;
            }
        }
    }
    
    // Try to download PHPMailer automatically
    return setupPHPMailer() && loadPHPMailer();
}

/**
 * Send email using ONLY PHPMailer with SMTP from config.php
 */
function sendEmailWithPHPMailer($to, $subject, $message) {
    // Load PHPMailer
    if (!loadPHPMailer()) {
        error_log("PHPMailer could not be loaded");
        throw new Exception('PHPMailer is required but could not be loaded');
    }
    
    // Check SMTP configuration from config.php
    if (!SMTP_ENABLED) {
        throw new Exception('SMTP is disabled in configuration');
    }
    
    if (empty(SMTP_HOST) || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
        throw new Exception('SMTP configuration is incomplete in config.php');
    }
    
    try {
        // Create PHPMailer instance
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        } elseif (class_exists('PHPMailer')) {
            $mail = new PHPMailer(true);
        } else {
            throw new Exception('PHPMailer class not found');
        }
        
        // SMTP Configuration from config.php
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->Port = SMTP_PORT;
        
        // Set encryption from config.php
        if (SMTP_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = 'ssl';
        } elseif (SMTP_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = 'tls';
        }
        
        // SSL options for compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Email settings from config.php
        $mail->setFrom(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(EMAIL_FROM_ADDRESS, EMAIL_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->CharSet = 'UTF-8';
        
        // Send email
        $mail->send();
        //("PHPMailer SMTP email sent successfully to: " . $to . " via " . SMTP_HOST);
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer SMTP failed: " . $e->getMessage());
        throw new Exception('Email sending failed: ' . $e->getMessage());
    }
}

/**
 * Send password reset email using PHPMailer only
 */
function sendPasswordResetEmail($to, $username, $reset_link) {
    if (!EMAIL_ENABLED) {
        throw new Exception('Email system is disabled');
    }
    
    $subject = 'Password Reset Request - ' . SITE_NAME;
    
    // Email HTML message
    $email_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Password Reset</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #0073aa; color: white; padding: 20px; text-align: center; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
            .button { display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
            .info-box { background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; }
            .footer { background: #f8f9fa; padding: 15px; margin: 20px -20px -20px -20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
            .security { background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin: 15px 0; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Password Reset</h1>
            </div>
            
            <h2>Hello " . htmlspecialchars($username) . ",</h2>
            
            <p>Someone has requested a password reset for your account on <strong>" . htmlspecialchars(SITE_NAME) . "</strong>.</p>
            
            <p>If this was you, click the button below to reset your password:</p>
            
            <div style='text-align: center;'>
                <a href='" . htmlspecialchars($reset_link) . "' class='button'>Reset My Password</a>
            </div>
            
            <p>Or copy and paste this URL into your browser:</p>
            <p style='background: #f8f9fa; padding: 10px; border-radius: 4px; word-break: break-all; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($reset_link) . "</p>
            
            <div class='info-box'>
                <strong>⚠️ Security Information:</strong><br>
                • This link will expire in <strong>15 minutes</strong><br>
                • This link can only be used <strong>once</strong><br>
                • If you didn't request this reset, you can safely ignore this email
            </div>
            
            <div class='security'>
                <strong>🔒 Secure Email:</strong> Sent via PHPMailer with " . strtoupper(SMTP_ENCRYPTION) . " encryption through " . htmlspecialchars(SMTP_HOST) . "
            </div>
            
            <p>If you're having trouble clicking the button, copy and paste the URL above into your web browser.</p>
            
            <div class='footer'>
                <p>This email was sent from <strong>" . htmlspecialchars(SITE_NAME) . "</strong></p>
                <p>Website: " . htmlspecialchars(BASE_URL) . "</p>
                <p>From: " . htmlspecialchars(EMAIL_FROM_ADDRESS) . "</p>
                <p>Time sent: " . date('Y-m-d H:i:s T', strtotime('now')) . " (Sri Lanka Time)</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Send using PHPMailer only
    return sendEmailWithPHPMailer($to, $subject, $email_message);
}

if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    
    try {
        if (empty($email)) {
            throw new Exception('Please enter your email address');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
        }
        
        if (!EMAIL_ENABLED) {
            throw new Exception('Email system is disabled');
        }
        
        // Check if admin exists with this email
        $stmt = $pdo->prepare("SELECT id, username, email FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            // Set timezone to Sri Lanka
            date_default_timezone_set('Asia/Colombo');
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $created_at = date('Y-m-d H:i:s'); // Current timestamp in Sri Lanka time
            $expires_at = date('Y-m-d H:i:s', strtotime($created_at . ' +15 minutes')); // Created at + 15 minutes
            
            // Delete any existing unused tokens for this admin
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE admin_id = ? AND used = 0");
            $stmt->execute([$admin['id']]);
            
            // Store new token in database with created_at and expires_at
            $stmt = $pdo->prepare("INSERT INTO password_resets (admin_id, token, created_at, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin['id'], $token, $created_at, $expires_at]);
            
            // Generate reset link
            $reset_link = BASE_URL . "/admin/reset_password.php?token=" . urlencode($token);
            
            // Send email using PHPMailer only
            if (sendPasswordResetEmail($admin['email'], $admin['username'], $reset_link)) {
                $message = 'Password reset instructions have been sent to your email address via secure SMTP.';
                if (strlen($admin['email']) > 6) {
                    $masked_email = substr($admin['email'], 0, 3) . '***@' . substr(strrchr($admin['email'], '@'), 1);
                    $message .= ' (' . $masked_email . ')';
                }
            }
        } else {
            $message = 'If an account with that email exists, password reset instructions have been sent.';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Password reset error: " . $error);
    }
}

// Check PHPMailer and SMTP status
$phpmailer_status = loadPHPMailer() ? 'Available' : 'Not Available';
$smtp_configured = SMTP_ENABLED && !empty(SMTP_HOST) && !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Admin Panel</title>
    <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center bg-blue-100 rounded-full">
                <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Forgot Password
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Enter your email address and we'll send you a secure password reset link
            </p>
        </div>

            <!-- SMTP Status - Only show if not configured -->
            <?php if (!$smtp_configured): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong>SMTP Configuration:</strong> Not Configured
                <div class="mt-1 text-sm">
                    <i class="fas fa-info-circle mr-1"></i>Please configure the SMTP settings in config.php, or please contact your hosting provider.
                </div>
            </div>
            <?php endif; ?>
        <form class="mt-8 space-y-6" method="POST">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($message) ?>
                    <div class="mt-2 text-sm">
                        <i class="fas fa-info-circle mr-1"></i>Please check your email inbox and spam folder.
                    </div>
                </div>
            <?php endif; ?>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <div class="relative">
                    <input id="email" name="email" type="email" required 
                           class="mt-1 appearance-none relative block w-full px-3 py-2 pl-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                           placeholder="Enter your admin email address"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           <?= !$smtp_configured || $phpmailer_status !== 'Available' ? 'disabled' : '' ?>>
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
                        <?= !$smtp_configured || $phpmailer_status !== 'Available' ? 'disabled' : '' ?>>
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-paper-plane text-blue-500 group-hover:text-blue-400"></i>
                    </span>
                    Send Secure Reset Link
                </button>
            </div>
            
            <div class="text-center space-y-2">
                <a href="login.php" class="text-blue-600 hover:text-blue-500 text-sm transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-1"></i>Back to Login
                </a>
                <br>
                <a href="<?= BASE_URL ?>/" class="text-gray-600 hover:text-gray-500 text-sm transition-colors duration-200">
                    <i class="fas fa-home mr-1"></i>Back to Website
                </a>
            </div>
        </form>
    </div>
</body>
</html>