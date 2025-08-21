<?php
// error-page.php
// Get error details from URL parameters
$errorTitle = $_GET['title'] ?? 'Database Connection Error';
$errorMessage = $_GET['message'] ?? "We're experiencing technical difficulties. Please try again later.";
$debug = $_GET['debug'] ?? '';

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']));
}

// Function to get database error details (you may need to include your main file)
function getDatabaseError() {
    // This should return actual database error details
    // You might need to include your main functions file here
    return "Connection timeout or database server unavailable.";
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>DB Error</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .error-container {
                background: white;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                padding: 40px;
                max-width: 500px;
                text-align: center;
                margin: 20px;
            }
            .error-icon {
                font-size: 48px;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            h1 {
                color: #2c3e50;
                margin-bottom: 20px;
                font-size: 24px;
            }
            p {
                color: #7f8c8d;
                line-height: 1.6;
                margin-bottom: 30px;
            }
            .button-group {
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            .retry-btn, .install-btn {
                padding: 12px 30px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                text-decoration: none;
                display: inline-block;
                transition: background 0.3s;
                min-width: 120px;
                text-align: center;
            }
            .retry-btn {
                background: #3498db;
                color: white;
            }
            .retry-btn:hover {
                background: #2980b9;
            }
            .install-btn {
                background: #27ae60;
                color: white;
            }
            .install-btn:hover {
                background: #229954;
            }
            .error-details {
                background: #f8f9fa;
                border-left: 4px solid #e74c3c;
                padding: 15px;
                margin-top: 20px;
                text-align: left;
                font-size: 14px;
                color: #666;
                display: none;
            }
            .toggle-details {
                color: #3498db;
                cursor: pointer;
                text-decoration: underline;
                font-size: 14px;
                margin-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1><?php echo htmlspecialchars($errorTitle); ?></h1>
            <p><?php echo htmlspecialchars($errorMessage); ?></p>
            
            <div class="button-group">
                <a href="javascript:history.back()" class="retry-btn">Go Back</a>
                <a href="<?php echo BASE_URL; ?>" class="retry-btn">Try Again</a>
                <a href="<?php echo BASE_URL; ?>/install" class="install-btn">Install Site</a>
            </div>
            
            <?php if ($debug == '1'): ?>
                <div class="toggle-details" onclick="toggleDetails()">Show Technical Details</div>
                <div class="error-details" id="errorDetails">
                    <strong>Error Details:</strong><br>
                    <?php echo htmlspecialchars(getDatabaseError()); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
            function toggleDetails() {
                var details = document.getElementById('errorDetails');
                if (details.style.display === 'none' || details.style.display === '') {
                    details.style.display = 'block';
                } else {
                    details.style.display = 'none';
                }
            }
        </script>
    </body>
</html>