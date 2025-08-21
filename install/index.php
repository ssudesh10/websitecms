<?php
// File: install/index.php (Installation Welcome & Requirements Check)

session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Installation - Requirements Check</title>
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
        .success { color: #059669; font-weight: 600; } 
        .error { color: #dc2626; font-weight: 600; } 
        .warning { color: #d97706; font-weight: 600; }
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
        .req-list { 
            list-style: none; 
            padding: 0; 
            margin: 20px 0;
        }
        .req-list li { 
            padding: 12px 0; 
            display: flex; 
            align-items: center; 
            border-bottom: 1px solid #f3f4f6;
        }
        .req-list li:last-child {
            border-bottom: none;
        }
        .req-list .icon { 
            margin-right: 15px; 
            font-weight: bold; 
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }
        .req-item {
            flex: 1;
            font-size: 1rem;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-success {
            background: #d1fae5;
            color: #059669;
        }
        .status-error {
            background: #fee2e2;
            color: #dc2626;
        }
        .summary {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Website CMS Installation</h1>
            <p>Let's get your content management system up and running</p>
        </div>
        
        <div class="progress">
            <div class="progress-bar" style="width: 20%"></div>
        </div>
        <p><strong>Step 1 of 5:</strong> System Requirements Check</p>
        
        <div class="step">
            <h3>🔍 System Requirements Validation</h3>
            <?php
            $requirements = [
                'PHP Version >= 7.4' => [
                    'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
                    'current' => 'Current: PHP ' . PHP_VERSION
                ],
                'PDO Extension' => [
                    'status' => extension_loaded('pdo'),
                    'current' => extension_loaded('pdo') ? 'Installed' : 'Missing'
                ],
                'PDO MySQL Extension' => [
                    'status' => extension_loaded('pdo_mysql'),
                    'current' => extension_loaded('pdo_mysql') ? 'Installed' : 'Missing'
                ],
                'GD Extension' => [
                    'status' => extension_loaded('gd'),
                    'current' => extension_loaded('gd') ? 'Installed' : 'Missing'
                ],
                'JSON Extension' => [
                    'status' => extension_loaded('json'),
                    'current' => extension_loaded('json') ? 'Installed' : 'Missing'
                ],
                'Mbstring Extension' => [
                    'status' => extension_loaded('mbstring'),
                    'current' => extension_loaded('mbstring') ? 'Installed' : 'Missing'
                ],
                'Session Support' => [
                    'status' => function_exists('session_start'),
                    'current' => function_exists('session_start') ? 'Available' : 'Not Available'
                ],
                'File Upload Support' => [
                    'status' => ini_get('file_uploads'),
                    'current' => ini_get('file_uploads') ? 'Enabled' : 'Disabled'
                ],
            ];
            
            $directories = [
                'config/' => [
                    'status' => is_writable('../config/') || is_writable('../'),
                    'path' => is_writable('../config/') ? '../config/' : '../'
                ],
                'uploads/' => [
                    'status' => is_writable('../uploads/') || is_writable('../'),
                    'path' => is_writable('../uploads/') ? '../uploads/' : '../'
                ],
                'cache/' => [
                    'status' => is_writable('../cache/') || is_writable('../'),
                    'path' => is_writable('../cache/') ? '../cache/' : '../'
                ],
            ];
            
            $allGood = true;
            $phpRequirementsPassed = 0;
            $phpRequirementsTotal = count($requirements);
            $dirRequirementsPassed = 0;
            $dirRequirementsTotal = count($directories);
            
            echo "<h4>📋 PHP Requirements:</h4>";
            echo "<ul class='req-list'>";
            foreach ($requirements as $req => $data) {
                $status = $data['status'];
                $current = $data['current'];
                $class = $status ? 'success' : 'error';
                $icon = $status ? '✅' : '❌';
                $badgeClass = $status ? 'status-success' : 'status-error';
                
                if ($status) $phpRequirementsPassed++;
                if (!$status) $allGood = false;
                
                echo "<li>";
                echo "<span class='icon'>$icon</span>";
                echo "<div class='req-item'>$req<br><small style='color: #6b7280;'>$current</small></div>";
                echo "<span class='status-badge $badgeClass'>" . ($status ? 'OK' : 'FAIL') . "</span>";
                echo "</li>";
            }
            echo "</ul>";
            
            echo "<h4>📁 Directory Permissions:</h4>";
            echo "<ul class='req-list'>";
            foreach ($directories as $dir => $data) {
                $writable = $data['status'];
                $path = $data['path'];
                $class = $writable ? 'success' : 'error';
                $icon = $writable ? '✅' : '❌';
                $badgeClass = $writable ? 'status-success' : 'status-error';
                
                if ($writable) $dirRequirementsPassed++;
                if (!$writable) $allGood = false;
                
                echo "<li>";
                echo "<span class='icon'>$icon</span>";
                echo "<div class='req-item'>$dir (writable)<br><small style='color: #6b7280;'>Path: $path</small></div>";
                echo "<span class='status-badge $badgeClass'>" . ($writable ? 'OK' : 'FAIL') . "</span>";
                echo "</li>";
            }
            echo "</ul>";
            
            // Summary
            echo "<div class='summary'>";
            echo "<h4>📊 Requirements Summary</h4>";
            echo "<p><strong>PHP Requirements:</strong> $phpRequirementsPassed/$phpRequirementsTotal passed</p>";
            echo "<p><strong>Directory Permissions:</strong> $dirRequirementsPassed/$dirRequirementsTotal passed</p>";
            
            if ($allGood) {
                echo "<p class='success'><strong>🎉 Excellent! All requirements are met. You can proceed with the installation.</strong></p>";
                echo "<a href='database.php' class='btn'>Continue to Database Setup →</a>";
            } else {
                echo "<p class='error'><strong>⚠️ Please fix the issues above before continuing.</strong></p>";
                echo "<button class='btn' disabled>Continue to Database Setup →</button>";
                echo "<br><br><small style='color: #6b7280;'>Contact your hosting provider if you need help resolving these issues.</small>";
            }
            echo "</div>";
            ?>
        </div>
        
        <div style="text-align: center; margin-top: 30px; color: #6b7280;">
            <p>Website CMS Installer v1.0 | Powered by PHP <?= PHP_VERSION ?></p>
        </div>
    </div>
</body>
</html>