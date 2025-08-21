<?php
// File: install/admin.php (Create Admin User) - SERIAL NUMBER REQUIRED

session_start();

if (!isset($_SESSION['db_config']) || !isset($_SESSION['tables_created'])) {
    header('Location: setup.php');
    exit;
}

$error = '';
$success = '';
$db_config = $_SESSION['db_config'];

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $serial_number = trim($_POST['serial_number'] ?? '');
    $site_name = trim($_POST['site_name'] ?? '');
    $site_url = trim($_POST['site_url'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    
    // FIXED: Keep serial_number in required fields check
    if (empty($username) || empty($email) || empty($password) || empty($serial_number) || empty($site_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    } elseif (strlen($serial_number) < 10) {
        $error = 'Serial number must be at least 10 characters long.';
    } elseif (!preg_match('/^[a-zA-Z0-9\-]+$/', $serial_number)) {
        $error = 'Serial number can only contain letters, numbers, and hyphens.';
    } else {
        try {
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            // Check if serial number already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `settings` WHERE setting_key = 'serial_number' AND setting_value = ?");
            $stmt->execute([$serial_number]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'This serial number is already in use. Please contact support if you believe this is an error.';
            } else {
                // Create admin user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO `admins` (username, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password]);
                
                // Update site settings
                $settings_updates = [
                    ['site_name', $site_name],
                    ['site_url', rtrim($site_url, '/')],
                    ['admin_email', $email],
                    ['contact_email', $contact_email ?: $email],
                    ['contact_phone', $contact_phone],
                    ['serial_number', $serial_number],
                    ['installation_date', date('Y-m-d H:i:s')]
                ];
                
                $stmt = $pdo->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = ?");
                foreach ($settings_updates as $setting) {
                    $stmt->execute([$setting[1], $setting[0]]);
                }
                
                $_SESSION['admin_created'] = true;
                $_SESSION['site_url'] = $site_url;
                $_SESSION['serial_number'] = $serial_number;
                $success = 'Admin user created successfully!';
                header('refresh:2;url=finish.php');
            }
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Username or email already exists. Please choose different credentials.';
            } else {
                $error = 'Failed to create admin user: ' . $e->getMessage();
            }
        }
    }
}

// Get current site URL for default
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$default_site_url = $protocol . '://' . $host . str_replace('/install/admin.php', '', $_SERVER['REQUEST_URI']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - CMS Installation</title>
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
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .form-group { 
            margin: 20px 0; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #374151;
            font-size: 1rem;
        }
        .form-group input { 
            width: 100%; 
            padding: 12px 16px; 
            border: 2px solid #e5e7eb; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-group input.error-field {
            border-color: #dc2626;
            background-color: #fef2f2;
        }
        .form-group small {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 4px;
            display: block;
        }
        .password-strength {
            margin-top: 8px;
            padding: 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            display: none;
        }
        .strength-weak { background: #fef2f2; color: #dc2626; }
        .strength-medium { background: #fef3c7; color: #d97706; }
        .strength-strong { background: #f0fdf4; color: #059669; }
        .serial-validation {
            margin-top: 8px;
            padding: 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            display: none;
        }
        .serial-valid { background: #f0fdf4; color: #059669; }
        .serial-invalid { background: #fef2f2; color: #dc2626; }
        .serial-required { background: #fef3c7; color: #d97706; }
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
            box-shadow: none;
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
        .section-divider {
            border-top: 1px solid #e5e7eb;
            margin: 30px 0;
            padding-top: 30px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👨‍💼 Admin Account Setup</h1>
            <p>Create your administrator account and configure your site</p>
        </div>
        
        <div class="progress">
            <div class="progress-bar" style="width: 80%"></div>
        </div>
        <p><strong>Step 4 of 5:</strong> Admin User & Site Configuration</p>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?> Finalizing installation...</div>
            <div class="loading" style="display: flex;">
                <div class="spinner"></div>
                <span>Completing setup...</span>
            </div>
        <?php endif; ?>
        
        <div class="step">
            <form method="POST" id="adminForm" novalidate>
                <h3>🔐 Administrator Account</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">👤 Admin Username *</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required pattern="[a-zA-Z0-9_]+" maxlength="50">
                        <small>Only letters, numbers, and underscores allowed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">📧 Admin Email *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required maxlength="100">
                        <small>This will be used for admin notifications</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">🔑 Password *</label>
                        <input type="password" id="password" name="password" required minlength="6">
                        <small>Minimum 6 characters required</small>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">🔑 Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <small>Must match the password above</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="serial_number">🔢 License key *</label>
                    <input type="text" id="serial_number" name="serial_number" value="<?= htmlspecialchars($_POST['serial_number'] ?? '') ?>" required minlength="10" maxlength="50" pattern="[a-zA-Z0-9\-]+" placeholder="e.g., CMS-2024-ABCD123456">
                    <small>License serial number (minimum 10 characters, letters, numbers, and hyphens only)</small>
                    <div class="serial-validation" id="serialValidation"></div>
                </div>
                
                <div class="section-divider">
                    <h3>🌐 Site Configuration</h3>
                </div>
                
                <div class="form-group">
                    <label for="site_name">🏢 Site Name *</label>
                    <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($_POST['site_name'] ?? 'Your Website CMS') ?>" required maxlength="200">
                    <small>This will appear in your site title and branding</small>
                </div>
                
                <div class="form-group">
                    <label for="site_url">🌍 Site URL</label>
                    <input type="url" id="site_url" name="site_url" value="<?= htmlspecialchars($_POST['site_url'] ?? $default_site_url) ?>">
                    <small>Your website's full URL (e.g., https://yoursite.com)</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_email">📬 Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" value="<?= htmlspecialchars($_POST['contact_email'] ?? '') ?>">
                        <small>Public contact email for your website</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_phone">📞 Contact Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>">
                        <small>Optional phone number for contact</small>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 40px;">
                    <a href="setup.php" class="btn-secondary">← Back to Database Setup</a>
                    <button type="submit" class="btn" id="submitBtn">
                        <span id="btnText">🚀 Create Admin & Complete Setup</span>
                        <div class="loading" id="btnLoading" style="display: none;">
                            <div class="spinner"></div>
                            <span>Creating account...</span>
                        </div>
                    </button>
                </div>
            </form>
        </div>
        
        <div style="text-align: center; margin-top: 30px; color: #6b7280;">
            <p>🔐 Your credentials will be securely encrypted and stored</p>
        </div>
    </div>

    <script>
        let formSubmitted = false;

        // Disable all fields if data was successfully passed
        <?php if ($success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const allFields = document.querySelectorAll('#adminForm input');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            
            // Disable all form fields
            allFields.forEach(field => {
                field.disabled = true;
                field.style.backgroundColor = '#f3f4f6';
                field.style.color = '#6b7280';
                field.style.cursor = 'not-allowed';
                field.style.opacity = '0.7';
            });
            
            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed';
            
            // Show loading state
            btnText.style.display = 'none';
            btnLoading.style.display = 'flex';
            
            formSubmitted = true;
        });
        <?php endif; ?>

        // Check if form can be submitted
        function checkFormValidity() {
            if (formSubmitted) return;
            
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const serialNumber = document.getElementById('serial_number').value.trim();
            const siteName = document.getElementById('site_name').value.trim();
            const submitBtn = document.getElementById('submitBtn');
            
            // Check if all required fields are filled and valid
            const isValid = username.length > 0 && 
                           email.length > 0 && 
                           password.length >= 6 && 
                           confirmPassword.length > 0 && 
                           password === confirmPassword &&
                           serialNumber.length >= 10 && 
                           /^[a-zA-Z0-9\-]+$/.test(serialNumber) &&
                           siteName.length > 0 &&
                           /^[a-zA-Z0-9_]+$/.test(username);
            
            submitBtn.disabled = !isValid;
            if (!isValid) {
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            } else {
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            if (formSubmitted) return;
            
            const strengthDiv = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            strengthDiv.style.display = password.length > 0 ? 'block' : 'none';
            
            if (strength < 3) {
                strengthDiv.className = 'password-strength strength-weak';
                strengthDiv.textContent = '⚠️ Weak password - consider adding uppercase, numbers, or symbols';
            } else if (strength < 5) {
                strengthDiv.className = 'password-strength strength-medium';
                strengthDiv.textContent = '⚡ Good password - consider adding more variety';
            } else {
                strengthDiv.className = 'password-strength strength-strong';
                strengthDiv.textContent = '✅ Strong password!';
            }
            
            checkFormValidity();
        }

        // Serial number validation - REQUIRED FIELD
        function validateSerialNumber() {
            if (formSubmitted) return;
            
            const serialNumber = document.getElementById('serial_number').value.trim();
            const serialInput = document.getElementById('serial_number');
            const validationDiv = document.getElementById('serialValidation');
            
            validationDiv.style.display = 'block';
            
            if (serialNumber.length === 0) {
                validationDiv.className = 'serial-validation serial-required';
                validationDiv.textContent = '⚠️ Serial number is required';
                serialInput.style.borderColor = '#d97706';
                serialInput.classList.add('error-field');
            } else if (serialNumber.length < 10) {
                validationDiv.className = 'serial-validation serial-invalid';
                validationDiv.textContent = '❌ Serial number must be at least 10 characters long';
                serialInput.style.borderColor = '#dc2626';
                serialInput.classList.add('error-field');
            } else if (!/^[a-zA-Z0-9\-]+$/.test(serialNumber)) {
                validationDiv.className = 'serial-validation serial-invalid';
                validationDiv.textContent = '❌ Only letters, numbers, and hyphens are allowed';
                serialInput.style.borderColor = '#dc2626';
                serialInput.classList.add('error-field');
            } else {
                validationDiv.className = 'serial-validation serial-valid';
                validationDiv.textContent = '✅ Valid serial number format';
                serialInput.style.borderColor = '#059669';
                serialInput.classList.remove('error-field');
            }
            
            checkFormValidity();
        }

        // Password confirmation checker
        function checkPasswordMatch() {
            if (formSubmitted) return;
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmInput = document.getElementById('confirm_password');
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    confirmInput.style.borderColor = '#059669';
                    confirmInput.classList.remove('error-field');
                } else {
                    confirmInput.style.borderColor = '#dc2626';
                    confirmInput.classList.add('error-field');
                }
            } else {
                confirmInput.style.borderColor = '#e5e7eb';
                confirmInput.classList.remove('error-field');
            }
            
            checkFormValidity();
        }

        // Username validation
        function validateUsername() {
            if (formSubmitted) return;
            
            const username = document.getElementById('username').value.trim();
            const usernameInput = document.getElementById('username');
            
            if (username.length > 0) {
                if (/^[a-zA-Z0-9_]+$/.test(username)) {
                    usernameInput.style.borderColor = '#059669';
                    usernameInput.classList.remove('error-field');
                } else {
                    usernameInput.style.borderColor = '#dc2626';
                    usernameInput.classList.add('error-field');
                }
            } else {
                usernameInput.style.borderColor = '#e5e7eb';
                usernameInput.classList.remove('error-field');
            }
            
            checkFormValidity();
        }

        // Event listeners (only if not submitted)
        <?php if (!$success): ?>
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });

        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        document.getElementById('username').addEventListener('input', validateUsername);
        document.getElementById('serial_number').addEventListener('input', validateSerialNumber);
        document.getElementById('email').addEventListener('input', checkFormValidity);
        document.getElementById('site_name').addEventListener('input', checkFormValidity);

        // Auto-generate contact email from admin email
        document.getElementById('email').addEventListener('blur', function() {
            const contactEmail = document.getElementById('contact_email');
            if (!contactEmail.value) {
                contactEmail.value = this.value;
            }
        });

        // Validate serial number on page load and focus
        document.getElementById('serial_number').addEventListener('focus', validateSerialNumber);
        document.getElementById('serial_number').addEventListener('blur', validateSerialNumber);
        <?php endif; ?>

        // Form submission - STRICT VALIDATION
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return;
            }
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const serialNumber = document.getElementById('serial_number').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const siteName = document.getElementById('site_name').value.trim();
            
            // STRICT VALIDATION - Check all required fields
            if (!username) {
                e.preventDefault();
                alert('Username is required!');
                document.getElementById('username').focus();
                return;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Email is required!');
                document.getElementById('email').focus();
                return;
            }
            
            if (!password || password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
                document.getElementById('password').focus();
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                document.getElementById('confirm_password').focus();
                return;
            }
            
            // CRITICAL: Serial number validation
            if (!serialNumber || serialNumber.length === 0) {
                e.preventDefault();
                alert('Serial number is required!');
                document.getElementById('serial_number').focus();
                validateSerialNumber(); // Show the validation message
                return;
            }
            
            if (serialNumber.length < 10) {
                e.preventDefault();
                alert('Serial number must be at least 10 characters long!');
                document.getElementById('serial_number').focus();
                return;
            }
            
            if (!/^[a-zA-Z0-9\-]+$/.test(serialNumber)) {
                e.preventDefault();
                alert('Serial number can only contain letters, numbers, and hyphens!');
                document.getElementById('serial_number').focus();
                return;
            }
            
            if (!siteName) {
                e.preventDefault();
                alert('Site name is required!');
                document.getElementById('site_name').focus();
                return;
            }
            
            formSubmitted = true;
            
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');
            
            // Immediately disable all form fields during submission
            const allFields = this.querySelectorAll('input');
            allFields.forEach(field => {
                field.readOnly = true;
                field.style.backgroundColor = '#f3f4f6';
                field.style.color = '#6b7280';
                field.style.cursor = 'not-allowed';
                field.style.pointerEvents = 'none';
                field.tabIndex = -1;
            });
            
            // Update button state
            btnText.style.display = 'none';
            btnLoading.style.display = 'flex';
            submitBtn.disabled = true;
        });

        // Prevent any interaction during submission
        document.addEventListener('keydown', function(e) {
            if (formSubmitted && e.target.tagName === 'INPUT') {
                e.preventDefault();
            }
        });

        // Initialize form validation on page load
        <?php if (!$success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Initial validation check
            validateSerialNumber();
            checkFormValidity();
            
            // Auto-focus first empty required field
            const requiredInputs = document.querySelectorAll('input[required]');
            for (let input of requiredInputs) {
                if (!input.value && !input.disabled) {
                    input.focus();
                    break;
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>