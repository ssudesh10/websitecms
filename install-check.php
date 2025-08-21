<?php
// install-check.php
// This page is shown when the install directory exists

// Check if install directory still exists
$installDirExists = is_dir(__DIR__ . '/install');

// If install directory has been removed, redirect to homepage
if (!$installDirExists) {
    header('Location: ./');
    exit();
}

// Get basic site info if config is available
$siteName = 'Your Website';
if (file_exists(__DIR__ . '/config.php')) {
    try {
        require_once __DIR__ . '/config.php';
        if (function_exists('getSetting')) {
            $siteName = getSetting('site_name', 'Your Website');
        }
    } catch (Exception $e) {
        // Ignore errors if config is not properly set up
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Security Check - <?= htmlspecialchars($siteName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .pulse-bg {
            animation: pulse-bg 2s ease-in-out infinite alternate;
        }
        
        @keyframes pulse-bg {
            0% { background-color: rgba(239, 68, 68, 0.1); }
            100% { background-color: rgba(239, 68, 68, 0.2); }
        }
        
        .warning-icon {
            animation: warning-pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes warning-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-2xl mx-auto p-6">
        <!-- Main Warning Card -->
        <div class="bg-white rounded-lg shadow-xl overflow-hidden">
            <!-- Header with Warning -->
            <div class="bg-red-500 text-white p-6 text-center pulse-bg">
                <div class="warning-icon text-6xl mb-4">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1 class="text-2xl font-bold mb-2">Security Warning</h1>
                <p class="text-red-100">Installation Directory Detected</p>
            </div>
            
            <!-- Content -->
            <div class="p-8">
                <div class="text-center mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">
                        Website Access Restricted
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        For security reasons, this website cannot be accessed while the installation directory exists. 
                        This is a critical security measure to prevent unauthorized access to sensitive installation files.
                    </p>
                </div>
                
                <!-- Installation Notice -->
                <div class="bg-blue-50 border-l-4 border-blue-400 p-6 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-download text-blue-400 text-xl mt-1"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-blue-800 mb-3">
                                Haven't Installed CMS Yet?
                            </h3>
                            <p class="text-blue-700 mb-3">
                                If you haven't completed the CMS installation process yet, click the button below to start the installation wizard.
                            </p>
                            <div class="bg-blue-100 rounded-lg p-3 mb-4">
                                <p class="text-blue-600 text-sm font-medium">
                                    <i class="fas fa-lightbulb mr-1"></i>
                                    The installation wizard will guide you through setting up your database, creating admin accounts, and configuring your website.
                                </p>
                            </div>
                            <div class="mt-4">
                                <a href="./install/" 
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors inline-flex items-center">
                                    <i class="fas fa-download mr-2"></i>
                                    Install CMS Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-6 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-yellow-400 text-xl mt-1"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-yellow-800 mb-3">
                                Already Installed? Complete These Actions:
                            </h3>
                            <ol class="text-yellow-700 space-y-2">
                                <li class="flex items-start">
                                    <span class="bg-yellow-400 text-yellow-800 rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5">1</span>
                                    <span>Delete or rename the <code class="bg-yellow-200 px-2 py-1 rounded text-sm font-mono">install</code> directory from your server</span>
                                </li>
                                <li class="flex items-start">
                                    <span class="bg-yellow-400 text-yellow-800 rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold mr-3 mt-0.5">2</span>
                                    <span>Refresh this page or visit your website again</span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <!-- Directory Path Info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h4 class="font-semibold text-gray-700 mb-2">
                        <i class="fas fa-folder mr-2 text-gray-500"></i>
                        Directory to Remove:
                    </h4>
                    <code class="bg-gray-800 text-green-400 px-3 py-2 rounded block font-mono text-sm">
                        <?= htmlspecialchars(realpath(__DIR__)) ?>/install/
                    </code>
                </div>
                
                <!-- Technical Details -->
                <details class="mb-6">
                    <summary class="cursor-pointer text-gray-700 font-medium hover:text-gray-900 transition-colors">
                        <i class="fas fa-chevron-right mr-2 transition-transform"></i>
                        Why is this necessary?
                    </summary>
                    <div class="mt-4 pl-6 text-gray-600 space-y-2 text-sm">
                        <p>• Installation directories often contain sensitive configuration files and database setup scripts</p>
                        <p>• Leaving these accessible can expose your website to security vulnerabilities</p>
                        <p>• This is a standard security practice for web applications</p>
                        <p>• Once removed, your website will function normally</p>
                    </div>
                </details>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button onclick="location.reload()" 
                            class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center justify-center">
                        <i class="fas fa-sync-alt mr-2"></i>
                        Check Again
                    </button>
                    
                    <?php if (file_exists(__DIR__ . '/admin')): ?>
                    <a href="./admin/" 
                       class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors text-center inline-flex items-center justify-center">
                        <i class="fas fa-cog mr-2"></i>
                        Admin Panel
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-100 px-8 py-4 text-center text-sm text-gray-600">
                <p>
                    <i class="fas fa-shield-alt mr-1"></i>
                    This security check protects your website from potential vulnerabilities
                </p>
            </div>
        </div>
        
        <!-- Additional Help -->
        <div class="mt-6 text-center">
            <p class="text-gray-500 text-sm">
                Need help? Contact your system administrator or hosting provider.
            </p>
        </div>
    </div>
    
    <!-- Auto-refresh script -->
    <script>
        // Auto-refresh every 30 seconds to check if install directory is removed
        let refreshInterval;
        let refreshCounter = 0;
        
        function startAutoRefresh() {
            refreshInterval = setInterval(function() {
                refreshCounter++;
                
                // Update button text to show countdown
                const checkButton = document.querySelector('button[onclick="location.reload()"]');
                if (checkButton) {
                    const originalText = '<i class="fas fa-sync-alt mr-2"></i>Check Again';
                    const timeLeft = 30 - (refreshCounter % 30);
                    
                    if (timeLeft === 30) {
                        // Check if directory still exists
                        fetch(window.location.href, { method: 'HEAD' })
                            .then(response => {
                                if (response.redirected) {
                                    // If redirected, the install directory was removed
                                    window.location.href = './';
                                }
                            })
                            .catch(() => {
                                // If there's an error, just reload the page
                                location.reload();
                            });
                    }
                    
                    checkButton.innerHTML = `<i class="fas fa-sync-alt mr-2"></i>Check Again (${timeLeft}s)`;
                }
            }, 1000);
        }
        
        // Start auto-refresh when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startAutoRefresh();
            
            // Add click animation to details summary
            const details = document.querySelector('details');
            if (details) {
                const summary = details.querySelector('summary');
                const chevron = summary.querySelector('.fas');
                
                details.addEventListener('toggle', function() {
                    if (details.open) {
                        chevron.style.transform = 'rotate(90deg)';
                    } else {
                        chevron.style.transform = 'rotate(0deg)';
                    }
                });
            }
        });
        
        // Clear interval when user manually refreshes
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>