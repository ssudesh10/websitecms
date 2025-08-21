<?php
// subscribe.php - Updated for FormData requests
require_once 'config.php';
require_once 'function.php';
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get email from POST data (FormData)
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

try {
    // Use the existing PDO connection from config.php
    // Create table if it doesn't exist
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(255) NOT NULL,
            `subscribed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `is_active` tinyint(1) DEFAULT 1,
            `unsubscribe_token` varchar(64) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email_unique` (`email`),
            KEY `subscribed_at` (`subscribed_at`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createTableSQL);
    
    // Check if email already exists and is active
    $stmt = $pdo->prepare('SELECT id FROM newsletter_subscribers WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already subscribed']);
        exit;
    }
    
    // Generate unsubscribe token
    $unsubscribe_token = bin2hex(random_bytes(32));
    
    // Insert new subscriber or reactivate existing
    $stmt = $pdo->prepare('
        INSERT INTO newsletter_subscribers (email, unsubscribe_token) 
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE 
        is_active = 1, 
        subscribed_at = CURRENT_TIMESTAMP,
        unsubscribe_token = ?
    ');
    
    $stmt->execute([$email, $unsubscribe_token, $unsubscribe_token]);
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Successfully subscribed to newsletter!'
    ]);
    
} catch (Exception $e) {
    // Log the actual error for debugging
    error_log('Newsletter subscription error: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred. Please try again.'
    ]);
}
?>