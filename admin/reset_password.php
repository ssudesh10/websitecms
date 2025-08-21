<?php
require_once '../config.php';
require_once '../function.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Set timezone to Sri Lanka for consistent time handling
date_default_timezone_set('Asia/Colombo');

// Validate token
$reset_request = null;
if ($token) {
    // First, let's check what tokens exist (for debugging)
    $debug_stmt = $pdo->prepare("SELECT token, expires_at, used, admin_id FROM password_resets WHERE token = ?");
    $debug_stmt->execute([$token]);
    $debug_result = $debug_stmt->fetch();
    
    if ($debug_result) {
        
    } else {
        error_log("Token not found in database");
    }
    
    // Clean up expired tokens ONLY if they are actually expired
    $cleanup_stmt = $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW() AND token != ?");
    $cleanup_stmt->execute([$token]);
    
    // Check if the specific token is valid
    $stmt = $pdo->prepare("
        SELECT pr.*, a.username, a.email 
        FROM password_resets pr 
        JOIN admins a ON pr.admin_id = a.id 
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();
    
}

if (!$reset_request) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid Reset Link - Admin Panel</title>
        <link href="../public/css/style.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8">
            <div>
                <div class="mx-auto h-12 w-12 flex items-center justify-center bg-red-100 rounded-full">
                    <i class="fas fa-times text-red-600 text-xl"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Invalid or Expired Link
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    This password reset link is either invalid, has expired, or has already been used.
                </p>
            </div>
            
            <div class="bg-red-50 border border-red-200 rounded-md p-4">
                <div class="text-sm text-red-800">
                    <p class="font-medium mb-2">Possible reasons:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>The link has expired (links are valid for 15 minutes)</li>
                        <li>The link has already been used</li>
                        <li>The link is malformed or invalid</li>
                        <li>A newer reset request was made</li>
                    </ul>
                </div>
            </div>
            
            <!-- Debug information (remove in production) -->
            <?php if (defined('DEBUG') && DEBUG): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                <div class="text-sm text-yellow-800">
                    <p class="font-medium mb-2">Debug Info:</p>
                    <p>Token: <?= htmlspecialchars($token) ?></p>
                    <p>Current Time: <?= date('Y-m-d H:i:s') ?> (Sri Lanka Time)</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="text-center space-y-3">
                <a href="forgot_password.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-redo mr-2"></i>Request New Reset Link
                </a>
                <br>
                <a href="login.php" class="text-gray-600 hover:text-gray-500 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Back to Login
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Process password reset
if ($_POST && isset($_POST['password'])) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        if (empty($password)) {
            throw new Exception('Please enter a new password');
        }
        
        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }
        
        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }
        
        // Check password strength
        $strength_errors = [];
        if (!preg_match('/[a-z]/', $password)) {
            $strength_errors[] = 'at least one lowercase letter';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $strength_errors[] = 'at least one uppercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $strength_errors[] = 'at least one number';
        }
        
        if (!empty($strength_errors)) {
            throw new Exception('Password should contain: ' . implode(', ', $strength_errors));
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $reset_request['admin_id']]);
            
            // Mark token as used (only update the 'used' column)
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = true;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Password reset error: " . $error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Admin Panel</title>
    <link href="../public/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <?php if ($success): ?>
            <div>
                <div class="mx-auto h-12 w-12 flex items-center justify-center bg-green-100 rounded-full">
                    <i class="fas fa-check text-green-600 text-xl"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Password Reset Successful
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Your password has been successfully updated. You can now login with your new password.
                </p>
                
                <div class="mt-6 bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="text-sm text-green-800">
                        <p class="font-medium mb-2">✅ What happens next:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Your old password is no longer valid</li>
                            <li>This reset link cannot be used again</li>
                            <li>You can now login with your new password</li>
                        </ul>
                    </div>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="login.php" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login Now
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div>
                <div class="mx-auto h-12 w-12 flex items-center justify-center bg-blue-100 rounded-full">
                    <i class="fas fa-key text-blue-600 text-xl"></i>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Reset Password
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Create a new password for: <strong><?= htmlspecialchars($reset_request['username']) ?></strong>
                    <br><span class="text-xs text-gray-500"><?= htmlspecialchars($reset_request['email']) ?></span>
                </p>
            </div>

            <form class="mt-8 space-y-6" method="POST" id="resetForm">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <div class="space-y-4">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                        <div class="relative">
                            <input id="password" name="password" type="password" required 
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                                   placeholder="Enter new password">
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('password')">
                                <i class="fas fa-eye text-gray-400 hover:text-gray-600" id="password-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Password strength:</span>
                                <span id="strength-text" class="font-medium">Weak</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                                <div id="strength-bar" class="bg-red-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Must be at least 8 characters with uppercase, lowercase, and numbers
                        </p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <div class="relative">
                            <input id="confirm_password" name="confirm_password" type="password" required 
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                                   placeholder="Confirm your new password">
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye text-gray-400 hover:text-gray-600" id="confirm_password-eye"></i>
                            </button>
                        </div>
                        <div id="password-match" class="mt-1 text-xs hidden">
                            <span id="match-text"></span>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit" id="submit-btn" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-save text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        Update Password
                    </button>
                </div>
                
                <div class="text-center">
                    <a href="login.php" class="text-gray-600 hover:text-gray-500 text-sm">
                        <i class="fas fa-arrow-left mr-1"></i>Back to Login
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.className = 'fas fa-eye-slash text-gray-400 hover:text-gray-600';
            } else {
                field.type = 'password';
                eye.className = 'fas fa-eye text-gray-400 hover:text-gray-600';
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strength-bar');
            const strengthText = document.getElementById('strength-text');
            
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^A-Za-z0-9]/)) strength++;
            
            const colors = ['#ef4444', '#f97316', '#eab308', '#22c55e', '#16a34a'];
            const texts = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
            const widths = [20, 40, 60, 80, 100];
            
            strengthBar.style.width = widths[strength] + '%';
            strengthBar.style.backgroundColor = colors[strength];
            strengthText.textContent = texts[strength];
            strengthText.style.color = colors[strength];
            
            checkPasswordMatch();
        });

        // Password match checker
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            const matchText = document.getElementById('match-text');
            const submitBtn = document.getElementById('submit-btn');
            
            if (confirmPassword.length > 0) {
                matchDiv.classList.remove('hidden');
                
                if (password === confirmPassword) {
                    matchText.innerHTML = '<i class="fas fa-check text-green-600 mr-1"></i><span class="text-green-600">Passwords match</span>';
                    submitBtn.disabled = false;
                } else {
                    matchText.innerHTML = '<i class="fas fa-times text-red-600 mr-1"></i><span class="text-red-600">Passwords do not match</span>';
                    submitBtn.disabled = true;
                }
            } else {
                matchDiv.classList.add('hidden');
                submitBtn.disabled = false;
            }
        }
    </script>
</body>
</html>