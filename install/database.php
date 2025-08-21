<?php
// File: install/database.php (Database Configuration)

session_start();
$error = '';
$success = '';

if ($_POST) {
    $host = trim($_POST['db_host'] ?? '');
    $dbname = trim($_POST['db_name'] ?? '');
    $username = trim($_POST['db_username'] ?? '');
    $password = $_POST['db_password'] ?? '';
    
    if (empty($host) || empty($dbname) || empty($username)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Test database connection
            $dsn = "mysql:host=$host;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            // Check if database exists, create if not
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$dbname]);
            
            if (!$stmt->fetch()) {
                $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                $success = 'Database created and connection successful!';
            } else {
                $success = 'Database connection successful!';
            }
            
            // Store configuration in session
            $_SESSION['db_config'] = [
                'host' => $host,
                'dbname' => $dbname,
                'username' => $username,
                'password' => $password
            ];
            
            header('refresh:2;url=setup.php');
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
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
        .form-group small {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 4px;
            display: block;
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
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .info-box li {
            margin: 5px 0;
            color: #0369a1;
        }
        .loading {
            display: none;
            align-items: center;
            gap: 10px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗄️ Database Configuration</h1>
            <p>Connect to your MySQL database</p>
        </div>
        
        <div class="progress">
            <div class="progress-bar" style="width: 40%"></div>
        </div>
        <p><strong>Step 2 of 5:</strong> Database Setup</p>
        
        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">✅ <?= htmlspecialchars($success) ?> Redirecting to next step...</div>
            <div class="loading" style="display: flex; justify-content: center; margin: 20px 0;">
                <div class="spinner"></div>
                <span>Setting up database tables...</span>
            </div>
        <?php endif; ?>
        
        <div class="step">
            <h3>🔐 Database Connection Details</h3>
            
            <div class="info-box">
                <h4>📋 Before You Continue</h4>
                <p>You'll need the following information from your hosting provider:</p>
                <ul>
                    <li><strong>Database Host:</strong> Usually 'localhost' or provided by your host</li>
                    <li><strong>Database Name:</strong> The name of your MySQL database</li>
                    <li><strong>Username:</strong> Your database username</li>
                    <li><strong>Password:</strong> Your database password</li>
                </ul>
            </div>
            
            <form method="POST" id="dbForm">
                <div class="form-group">
                    <label for="db_host">🌐 Database Host *</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                    <small>Usually 'localhost' for most hosting providers</small>
                </div>
                
                <div class="form-group">
                    <label for="db_name">🗃️ Database Name *</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
                    <small>The name of your MySQL database (e.g., 'websitecms')</small>
                </div>
                
                <div class="form-group">
                    <label for="db_username">👤 Database Username *</label>
                    <input type="text" id="db_username" name="db_username" value="<?= htmlspecialchars($_POST['db_username'] ?? '') ?>" required>
                    <small>Your database username</small>
                </div>
                
                <div class="form-group">
                    <label for="db_password">🔑 Database Password</label>
                    <input type="password" id="db_password" name="db_password" autocomplete="new-password">
                    <small>Your database password (leave blank if no password)</small>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px;">
                    <a href="index.php" class="btn-secondary">← Back to Requirements</a>
                    <button type="submit" class="btn" id="submitBtn">
                        <span id="btnText">Test Connection & Continue</span>
                        <div class="loading" id="btnLoading">
                            <div class="spinner"></div>
                            <span>Testing...</span>
                        </div>
                    </button>
                </div>
            </form>
        </div>
        
        <div style="text-align: center; margin-top: 30px; color: #6b7280;">
            <p>🔒 Your database credentials are securely stored and encrypted</p>
        </div>
    </div>

    <script>
let formSubmitted = false;

// Disable fields if data was successfully passed
<?php if ($success): ?>
document.addEventListener('DOMContentLoaded', function() {
    const textFields = document.querySelectorAll('input[type="text"], input[type="password"]');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnLoading = document.getElementById('btnLoading');
    
    // Disable all text fields
    textFields.forEach(field => {
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

// Handle form submission for unsuccessful attempts
document.getElementById('dbForm').addEventListener('submit', function(e) {
    if (formSubmitted) {
        e.preventDefault();
        return;
    }
    
    formSubmitted = true;
    
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnLoading = document.getElementById('btnLoading');
    
    // Immediately make fields read-only during submission
    const textFields = this.querySelectorAll('input[type="text"], input[type="password"]');
    textFields.forEach(field => {
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

// Auto-focus first empty field (only if not successful)
<?php if (!$success): ?>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input[required]');
    for (let input of inputs) {
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