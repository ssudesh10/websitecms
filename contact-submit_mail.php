<?php
// contact-submit_mail.php - Complete Enhanced version with database logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once 'config.php';
require_once 'function.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if this is an AJAX request
$isAjax = isset($_POST['ajax_request']) || 
          (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// Function to send JSON response for AJAX requests
function sendAjaxResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'debug' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ]
    ];
    echo json_encode($response);
    exit;
}

// Enhanced logging function with file output
function logDebug($message, $context = []) {
    $contextStr = empty($context) ? '' : ' | Context: ' . json_encode($context);
    $logMessage = "[CONTACT-FORM] " . date('Y-m-d H:i:s') . " - " . $message . $contextStr;
    
    // Log to PHP error log
    error_log($logMessage);
    
    // Also log to a specific file for easier debugging
    $logFile = 'contact_form_debug.log';
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND | LOCK_EX);
}

// Create contact submissions table if it doesn't exist
function createContactSubmissionsTable($pdo) {
    try {
        logDebug("Creating contact_submissions table if not exists");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contact_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                section_id INT,
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'pending',
                email_sent BOOLEAN DEFAULT FALSE,
                email_sent_at TIMESTAMP NULL,
                email_error TEXT NULL,
                INDEX idx_email (email),
                INDEX idx_submitted_at (submitted_at),
                INDEX idx_section_id (section_id),
                INDEX idx_status (status)
            )
        ");
        
        logDebug("Contact submissions table created/verified successfully");
        return true;
        
    } catch (Exception $e) {
        logDebug("Error creating contact_submissions table: " . $e->getMessage());
        throw new Exception("Database table creation failed: " . $e->getMessage());
    }
}

// Save contact submission to database
function saveContactSubmission($pdo, $formData, $status = 'pending') {
    try {
        logDebug("Saving contact submission to database", [
            'name' => $formData['name'],
            'email' => $formData['email'],
            'subject' => $formData['subject'],
            'section_id' => $formData['section_id'],
            'status' => $status
        ]);
        
        // Get client IP address
        $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 
                    $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
                    $_SERVER['HTTP_X_REAL_IP'] ?? 
                    $_SERVER['REMOTE_ADDR'] ?? 
                    'unknown';
        
        // Clean up IP if it contains multiple addresses
        if (strpos($ipAddress, ',') !== false) {
            $ipAddress = trim(explode(',', $ipAddress)[0]);
        }
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Prepare and execute insertion
        $stmt = $pdo->prepare("
            INSERT INTO contact_submissions 
            (name, email, subject, message, ip_address, user_agent, section_id, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $formData['name'],
            $formData['email'],
            $formData['subject'],
            $formData['message'],
            $ipAddress,
            $userAgent,
            $formData['section_id'],
            $status
        ]);
        
        if (!$result) {
            throw new Exception("Failed to save submission to database");
        }
        
        $submissionId = $pdo->lastInsertId();
        logDebug("Submission saved successfully", ['submission_id' => $submissionId]);
        
        return $submissionId;
        
    } catch (Exception $e) {
        logDebug("Error saving contact submission: " . $e->getMessage());
        throw new Exception("Failed to save submission: " . $e->getMessage());
    }
}

// Update submission status after email attempt
function updateSubmissionStatus($pdo, $submissionId, $emailSent, $error = null) {
    try {
        logDebug("Updating submission status", [
            'submission_id' => $submissionId,
            'email_sent' => $emailSent,
            'has_error' => !empty($error)
        ]);
        
        if ($emailSent) {
            $stmt = $pdo->prepare("
                UPDATE contact_submissions 
                SET status = 'sent', email_sent = TRUE, email_sent_at = NOW(), email_error = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$submissionId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE contact_submissions 
                SET status = 'failed', email_sent = FALSE, email_error = ? 
                WHERE id = ?
            ");
            $stmt->execute([$error, $submissionId]);
        }
        
        logDebug("Submission status updated successfully");
        return true;
        
    } catch (Exception $e) {
        logDebug("Error updating submission status: " . $e->getMessage());
        // Don't throw here as the submission was already saved
        return false;
    }
}

// Test database connection first
function testDatabaseConnection() {
    global $pdo;
    
    try {
        logDebug("Testing database connection");
        
        // Check if $pdo exists
        if (!isset($pdo)) {
            throw new Exception("PDO connection not found in global scope");
        }
        
        // Test basic query
        $stmt = $pdo->query("SELECT 1");
        if (!$stmt) {
            throw new Exception("Database query failed");
        }
        
        logDebug("Database connection test successful");
        return true;
        
    } catch (Exception $e) {
        logDebug("Database connection test failed: " . $e->getMessage());
        return false;
    }
}

// Get contact form settings with better error handling
function getContactFormSettings($pdo, $sectionId) {
    try {
        logDebug("Fetching contact form settings", ['section_id' => $sectionId]);
        
        // First try to get the specific section
        $stmt = $pdo->prepare("SELECT * FROM page_sections WHERE id = ? AND section_type = 'contact_form'");
        $stmt->execute([$sectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If not found, get the first contact form section
        if (!$section) {
            logDebug("Section not found, trying to get first contact form section");
            $stmt = $pdo->prepare("SELECT * FROM page_sections WHERE section_type = 'contact_form' ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$section) {
            throw new Exception("No contact form configuration found in database");
        }
        
        logDebug("Found section", ['id' => $section['id'], 'title' => $section['title'] ?? 'N/A']);
        
        // Parse email configuration from content field
        $emailConfig = [];
        if (!empty($section['content'])) {
            logDebug("Raw content field", ['content' => $section['content']]);
            
            $configLines = explode("\n", trim($section['content']));
            foreach ($configLines as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $emailConfig[trim($key)] = trim($value);
                }
            }
        }
        
        // Validate required configuration
        $required = ['recipient_email'];
        foreach ($required as $field) {
            if (empty($emailConfig[$field])) {
                throw new Exception("Missing required email configuration: $field");
            }
        }
        
        logDebug("Email configuration loaded successfully", [
            'recipient_email' => $emailConfig['recipient_email'] ?? 'N/A',
            'smtp_host' => $emailConfig['smtp_host'] ?? 'N/A',
            'smtp_username' => $emailConfig['smtp_username'] ?? 'N/A',
            'has_smtp_password' => !empty($emailConfig['smtp_password']),
            'recaptcha_enabled' => $emailConfig['recaptcha_enabled'] ?? 'false',
            'has_recaptcha_secret' => !empty($emailConfig['recaptcha_secret_key'])
        ]);
        
        return $emailConfig;
        
    } catch (Exception $e) {
        logDebug("Error getting contact form settings: " . $e->getMessage());
        throw $e;
    }
}

// reCAPTCHA verification function with debug
function verifyRecaptcha($recaptchaResponse, $secretKey) {
    try {
        logDebug("Starting reCAPTCHA verification", [
            'has_response' => !empty($recaptchaResponse),
            'response_length' => strlen($recaptchaResponse ?? ''),
            'has_secret' => !empty($secretKey)
        ]);
        
        if (empty($recaptchaResponse)) {
            throw new Exception("Please complete the reCAPTCHA verification.");
        }
        
        if (empty($secretKey)) {
            throw new Exception("reCAPTCHA secret key not configured.");
        }
        
        // Prepare verification data
        $verifyData = [
            'secret' => $secretKey,
            'response' => $recaptchaResponse,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        
        logDebug("Sending reCAPTCHA verification request", [
            'remote_ip' => $verifyData['remoteip']
        ]);
        
        // Create context for the HTTP request
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($verifyData),
                'timeout' => 10
            ]
        ]);
        
        // Send verification request to Google
        $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
        $response = file_get_contents($verifyUrl, false, $context);
        
        if ($response === false) {
            logDebug("reCAPTCHA verification request failed - no response from Google");
            throw new Exception("Failed to verify reCAPTCHA. Please try again.");
        }
        
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            logDebug("reCAPTCHA response JSON decode error", [
                'json_error' => json_last_error_msg(),
                'raw_response' => $response
            ]);
            throw new Exception("Invalid reCAPTCHA verification response.");
        }
        
        logDebug("reCAPTCHA verification response received", [
            'success' => $responseData['success'] ?? false,
            'error_codes' => $responseData['error-codes'] ?? [],
            'hostname' => $responseData['hostname'] ?? '',
            'challenge_ts' => $responseData['challenge_ts'] ?? ''
        ]);
        
        if (!isset($responseData['success']) || $responseData['success'] !== true) {
            $errorCodes = $responseData['error-codes'] ?? [];
            $errorMessage = "reCAPTCHA verification failed.";
            
            // Provide more specific error messages
            if (in_array('missing-input-secret', $errorCodes)) {
                $errorMessage = "reCAPTCHA configuration error: missing secret key.";
            } elseif (in_array('invalid-input-secret', $errorCodes)) {
                $errorMessage = "reCAPTCHA configuration error: invalid secret key.";
            } elseif (in_array('missing-input-response', $errorCodes)) {
                $errorMessage = "Please complete the reCAPTCHA verification.";
            } elseif (in_array('invalid-input-response', $errorCodes)) {
                $errorMessage = "reCAPTCHA verification failed. Please try again.";
            } elseif (in_array('timeout-or-duplicate', $errorCodes)) {
                $errorMessage = "reCAPTCHA has expired. Please try again.";
            }
            
            throw new Exception($errorMessage);
        }
        
        logDebug("reCAPTCHA verification successful");
        return true;
        
    } catch (Exception $e) {
        logDebug("reCAPTCHA verification error: " . $e->getMessage());
        throw $e;
    }
}

// Enhanced password decryption with better debugging
function decryptSMTPPassword($encryptedPassword) {
    $key = 'your-secure-password-key-2024';
    $salt = 'salt';
    $iterations = 100000;
    
    try {
        logDebug("Starting password decryption", [
            'encrypted_length' => strlen($encryptedPassword),
            'first_20_chars' => substr($encryptedPassword, 0, 20) . '...'
        ]);
        
        // Check if password is already plain text
        if (strlen($encryptedPassword) < 40 && !preg_match('/^[A-Za-z0-9+\/]+=*$/', $encryptedPassword)) {
            logDebug("Password appears to be plain text");
            return $encryptedPassword;
        }
        
        // Decode base64
        $data = base64_decode($encryptedPassword, true);
        if ($data === false) {
            logDebug("Base64 decode failed, trying as plain text");
            return $encryptedPassword;
        }
        
        logDebug("Base64 decode successful", ['decoded_length' => strlen($data)]);
        
        // Validate minimum length for AES-GCM
        if (strlen($data) < 28) {
            logDebug("Data too short for AES-GCM, treating as plain text");
            return $encryptedPassword;
        }
        
        // Extract components
        $iv = substr($data, 0, 12);
        $tag = substr($data, -16);
        $encrypted = substr($data, 12, -16);
        
        logDebug("Extracted encryption components", [
            'iv_length' => strlen($iv),
            'tag_length' => strlen($tag),
            'encrypted_length' => strlen($encrypted)
        ]);
        
        // Derive key using PBKDF2
        $derivedKey = hash_pbkdf2('sha256', $key, $salt, $iterations, 32, true);
        logDebug("Key derivation completed");
        
        // Decrypt using AES-256-GCM
        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-gcm',
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($decrypted === false) {
            $opensslError = openssl_error_string() ?: 'Unknown OpenSSL error';
            logDebug("OpenSSL decryption failed", ['error' => $opensslError]);
            
            // Try treating as plain text as fallback
            logDebug("Decryption failed, treating as plain text password");
            return $encryptedPassword;
        }
        
        logDebug("Password decryption successful", ['decrypted_length' => strlen($decrypted)]);
        return $decrypted;
        
    } catch (Exception $e) {
        logDebug("Password decryption error: " . $e->getMessage());
        // Return original password as fallback
        logDebug("Using original password as fallback");
        return $encryptedPassword;
    }
}

// SMTP email sending function with password decryption
function sendViaSMTP($emailConfig, $to, $subject, $htmlBody, $replyTo = null) {
    $host = $emailConfig['smtp_host'];
    $port = intval($emailConfig['smtp_port'] ?? 587);
    $username = $emailConfig['smtp_username'];
    $encryptedPassword = $emailConfig['smtp_password'];
    
    logDebug("Raw SMTP password from database", [
        'password_length' => strlen($encryptedPassword),
        'password_preview' => substr($encryptedPassword, 0, 20) . '...'
    ]);
    
    // Always try to decrypt the password
    $password = decryptSMTPPassword($encryptedPassword);
    
    logDebug("Using decrypted password", [
        'decrypted_length' => strlen($password),
        'original_length' => strlen($encryptedPassword),
        'passwords_match' => $password === $encryptedPassword
    ]);
    
    logDebug("Attempting SMTP connection", [
        'host' => $host,
        'port' => $port,
        'username' => $username,
        'encryption' => $emailConfig['smtp_encryption'] ?? 'tls'
    ]);
    
    // Create proper connection based on port
    $socketAddress = $host . ':' . $port;
    
    // Port 465 uses SSL from the start, port 587 uses STARTTLS
    if ($port == 465) {
        $socketAddress = 'ssl://' . $socketAddress;
    }
    
    // Create context with SSL options
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'ciphers' => 'HIGH:!SSLv2:!SSLv3'
        ]
    ]);
    
    logDebug("Connecting to: " . $socketAddress);
    
    // Create socket connection
    $socket = stream_socket_client(
        $socketAddress,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );
    
    if (!$socket) {
        throw new Exception("SMTP connection failed: $errstr ($errno)");
    }
    
    stream_set_timeout($socket, 30);
    
    // Helper functions
    $getResponse = function() use ($socket) {
        $response = '';
        $startTime = time();
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
            if (time() - $startTime > 30) {
                throw new Exception("SMTP response timeout");
            }
        }
        logDebug("SMTP Response: " . trim($response));
        return $response;
    };
    
    $sendCommand = function($command, $hideInLog = false) use ($socket, $getResponse) {
        if (!$hideInLog) {
            logDebug("SMTP Command: " . $command);
        } else {
            logDebug("SMTP Command: [HIDDEN - PASSWORD COMMAND]");
        }
        fwrite($socket, "$command\r\n");
        fflush($socket);
        return $getResponse();
    };
    
    try {
        // Read welcome message
        $response = $getResponse();
        if (strpos($response, '220') !== 0) {
            throw new Exception("SMTP server not ready: $response");
        }
        
        // EHLO
        $response = $sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        if (strpos($response, '250') !== 0) {
            throw new Exception("EHLO failed: $response");
        }
        
        // STARTTLS only for port 587 (465 is already SSL)
        if ($port == 587) {
            $response = $sendCommand("STARTTLS");
            if (strpos($response, '220') !== 0) {
                throw new Exception("STARTTLS failed: $response");
            }
            
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Failed to enable TLS encryption");
            }
            
            // Send EHLO again after TLS
            $response = $sendCommand("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            if (strpos($response, '250') !== 0) {
                throw new Exception("EHLO after TLS failed: $response");
            }
        }
        
        // Authentication
        $response = $sendCommand("AUTH LOGIN");
        if (strpos($response, '334') !== 0) {
            throw new Exception("AUTH LOGIN not supported: $response");
        }
        
        // Send username
        logDebug("Sending username for authentication");
        $response = $sendCommand(base64_encode($username));
        if (strpos($response, '334') !== 0) {
            throw new Exception("Username authentication failed: $response");
        }
        
        // Send password
        logDebug("Sending decrypted password for authentication");
        $response = $sendCommand(base64_encode($password), true);
        if (strpos($response, '235') !== 0) {
            logDebug("Password authentication failed", [
                'response' => $response,
                'password_used_length' => strlen($password)
            ]);
            throw new Exception("Password authentication failed: $response");
        }
        
        logDebug("SMTP authentication successful");
        
        // Send email
        $response = $sendCommand("MAIL FROM: <$username>");
        if (strpos($response, '250') !== 0) {
            throw new Exception("MAIL FROM failed: $response");
        }
        
        $response = $sendCommand("RCPT TO: <$to>");
        if (strpos($response, '250') !== 0) {
            throw new Exception("RCPT TO failed: $response");
        }
        
        $response = $sendCommand("DATA");
        if (strpos($response, '354') !== 0) {
            throw new Exception("DATA command failed: $response");
        }
        
        // Build email
        $fromEmail = $username;
        $fromName = "Website Contact Form";
        
        $emailData = "From: $fromName <$fromEmail>\r\n";
        $emailData .= "To: <$to>\r\n";
        if ($replyTo) {
            $emailData .= "Reply-To: <$replyTo>\r\n";
        }
        $emailData .= "Subject: $subject\r\n";
        $emailData .= "MIME-Version: 1.0\r\n";
        $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
        $emailData .= "Date: " . date('r') . "\r\n";
        $emailData .= "\r\n";
        $emailData .= $htmlBody . "\r\n";
        $emailData .= ".";
        
        $response = $sendCommand($emailData);
        if (strpos($response, '250') !== 0) {
            throw new Exception("Email sending failed: $response");
        }
        
        $sendCommand("QUIT");
        fclose($socket);
        
        logDebug("SMTP email sent successfully");
        return true;
        
    } catch (Exception $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        throw $e;
    }
}

// Email sending with SMTP support
function sendEmailWithConfig($emailConfig, $to, $subject, $body, $replyTo = null) {
    logDebug("Attempting to send email with configuration", [
        'to' => $to,
        'subject' => $subject,
        'has_smtp_config' => !empty($emailConfig['smtp_host'])
    ]);
    
    // Try SMTP if configured
    if (!empty($emailConfig['smtp_host']) && 
        !empty($emailConfig['smtp_username']) && 
        !empty($emailConfig['smtp_password'])) {
        
        try {
            logDebug("Trying SMTP method");
            return sendViaSMTP($emailConfig, $to, $subject, $body, $replyTo);
        } catch (Exception $e) {
            logDebug("SMTP method failed: " . $e->getMessage());
            throw $e; // Don't fall back to mail() in debug mode
        }
    } else {
        throw new Exception("SMTP configuration incomplete");
    }
}

// Function to get contact submissions for admin review
function getContactSubmissions($pdo, $limit = 50, $offset = 0, $status = null) {
    try {
        $whereClause = '';
        $params = [];
        
        if ($status) {
            $whereClause = 'WHERE status = ?';
            $params[] = $status;
        }
        
        $stmt = $pdo->prepare("
            SELECT id, name, email, subject, message, ip_address, user_agent, 
                   section_id, submitted_at, status, email_sent, email_sent_at, email_error
            FROM contact_submissions 
            $whereClause
            ORDER BY submitted_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        logDebug("Error fetching contact submissions: " . $e->getMessage());
        return [];
    }
}

// Function to get submission statistics
function getSubmissionStats($pdo) {
    try {
        $stats = [];
        
        // Total submissions
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM contact_submissions");
        $stats['total'] = $stmt->fetchColumn();
        
        // Submissions by status
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count 
            FROM contact_submissions 
            GROUP BY status
        ");
        $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($statusCounts as $row) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // Recent submissions (last 24 hours)
        $stmt = $pdo->query("
            SELECT COUNT(*) as recent 
            FROM contact_submissions 
            WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['recent_24h'] = $stmt->fetchColumn();
        
        // Failed email submissions
        $stmt = $pdo->query("SELECT COUNT(*) as failed FROM contact_submissions WHERE email_sent = FALSE");
        $stats['email_failed'] = $stmt->fetchColumn();
        
        return $stats;
        
    } catch (Exception $e) {
        logDebug("Error fetching submission stats: " . $e->getMessage());
        return [];
    }
}

// Main form processing function with enhanced debugging and database saving
function processContactForm() {
    global $pdo, $isAjax;
    
    $submissionId = null;
    
    try {
        logDebug("=== CONTACT FORM SUBMISSION STARTED ===", [
            'is_ajax' => $isAjax,
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'post_data_keys' => array_keys($_POST)
        ]);
        
        // Test database connection first
        if (!testDatabaseConnection()) {
            throw new Exception("Database connection failed. Please check your configuration.");
        }
        
        // Create contact submissions table if it doesn't exist
        createContactSubmissionsTable($pdo);
        
        // Get and sanitize form data
        $formData = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'message' => trim($_POST['message'] ?? ''),
            'section_id' => intval($_POST['section_id'] ?? 0),
            'honeypot' => $_POST['honeypot'] ?? '',
            'recaptcha_enabled' => ($_POST['recaptcha_enabled'] ?? '0') === '1',
            'recaptcha_response' => $_POST['g-recaptcha-response'] ?? ''
        ];
        
        logDebug("Form data received and sanitized", [
            'name' => $formData['name'],
            'email' => $formData['email'],
            'subject' => $formData['subject'],
            'section_id' => $formData['section_id'],
            'message_length' => strlen($formData['message']),
            'has_honeypot' => !empty($formData['honeypot']),
            'recaptcha_enabled' => $formData['recaptcha_enabled'],
            'has_recaptcha_response' => !empty($formData['recaptcha_response'])
        ]);
        
        // Basic validation
        if (empty($formData['name']) || strlen($formData['name']) < 2) {
            throw new Exception("Please enter a valid name (at least 2 characters).");
        }
        
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }
        
        if (empty($formData['subject']) || strlen($formData['subject']) < 3) {
            throw new Exception("Please enter a valid subject (at least 3 characters).");
        }
        
        if (empty($formData['message']) || strlen($formData['message']) < 10) {
            throw new Exception("Please enter a message (at least 10 characters).");
        }
        
        // Honeypot spam check
        if (!empty($formData['honeypot'])) {
            logDebug("Spam detected: honeypot filled");
            throw new Exception("Spam submission detected.");
        }
        
        logDebug("Basic validation passed");
        
        // Save submission to database first (before email attempt)
        $submissionId = saveContactSubmission($pdo, $formData, 'pending');
        
        // Get email configuration for this section
        $emailConfig = getContactFormSettings($pdo, $formData['section_id']);
        
        // reCAPTCHA verification if enabled
        $recaptchaEnabled = !empty($emailConfig['recaptcha_enabled']) && $emailConfig['recaptcha_enabled'] === 'true';
        
        if ($recaptchaEnabled) {
            logDebug("reCAPTCHA is enabled, verifying...");
            
            if (empty($emailConfig['recaptcha_secret_key'])) {
                throw new Exception("reCAPTCHA is enabled but secret key is not configured.");
            }
            
            // Verify reCAPTCHA
            verifyRecaptcha($formData['recaptcha_response'], $emailConfig['recaptcha_secret_key']);
        } else {
            logDebug("reCAPTCHA is disabled, skipping verification");
        }
        
        // Try to send email
        $recipientEmail = $emailConfig['recipient_email'];
        $emailSubject = 'Website Contact: ' . $formData['subject'];
        $emailBody = "<h2>New Contact Form Submission</h2>";
        $emailBody .= "<p><strong>Name:</strong> " . htmlspecialchars($formData['name']) . "</p>";
        $emailBody .= "<p><strong>Email:</strong> " . htmlspecialchars($formData['email']) . "</p>";
        $emailBody .= "<p><strong>Subject:</strong> " . htmlspecialchars($formData['subject']) . "</p>";
        $emailBody .= "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($formData['message'])) . "</p>";
        $emailBody .= "<p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $emailBody .= "<p><strong>Submission ID:</strong> " . $submissionId . "</p>";
        
        // Send email using SMTP configuration
        try {
            sendEmailWithConfig($emailConfig, $recipientEmail, $emailSubject, $emailBody, $formData['email']);
            
            // Update submission status to 'sent'
            updateSubmissionStatus($pdo, $submissionId, true);
            
            $successMessage = "Thank you, " . htmlspecialchars($formData['name']) . 
                "! Your message has been sent successfully. We'll get back to you soon.";
            
            logDebug("=== CONTACT FORM SUBMISSION COMPLETED SUCCESSFULLY ===", [
                'submission_id' => $submissionId
            ]);
            
        } catch (Exception $emailError) {
            // Update submission status to 'failed' but don't throw error
            updateSubmissionStatus($pdo, $submissionId, false, $emailError->getMessage());
            
            logDebug("Email sending failed but submission was saved", [
                'submission_id' => $submissionId,
                'email_error' => $emailError->getMessage()
            ]);
            
            // For admin notification - still show success to user but log the email failure
            $successMessage = "Thank you, " . htmlspecialchars($formData['name']) . 
                "! Your message has been received and saved. We'll get back to you soon.";
        }
        
        // Handle response based on request type
        if ($isAjax) {
            sendAjaxResponse(true, $successMessage, ['submission_id' => $submissionId]);
        } else {
            $_SESSION['contact_success'] = $successMessage;
            return true;
        }
        
    } catch (Exception $e) {
        logDebug("Contact form error occurred", [
            'submission_id' => $submissionId,
            'error_message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // If we have a submission ID, update it to failed status
        if ($submissionId) {
            updateSubmissionStatus($pdo, $submissionId, false, $e->getMessage());
        }
        
        // Handle error response based on request type
        if ($isAjax) {
            sendAjaxResponse(false, $e->getMessage());
        } else {
            $_SESSION['contact_error'] = $e->getMessage();
            // Store form data for repopulation
            $_SESSION['form_data'] = [
                'name' => $formData['name'] ?? '',
                'email' => $formData['email'] ?? '',
                'subject' => $formData['subject'] ?? '',
                'message' => $formData['message'] ?? ''
            ];
            return false;
        }
    }
}

// Debug endpoint with enhanced information
if (isset($_GET['debug']) && $_GET['debug'] === 'test') {
    echo "<!DOCTYPE html><html><head><title>Contact Form Debug Test</title></head><body>";
    echo "<h1>Contact Form Debug Test</h1>";
    
    // Test database
    try {
        echo "<p style='color: green;'>✅ Database connection: " . (testDatabaseConnection() ? 'OK' : 'FAILED') . "</p>";
        
        // Test table creation
        createContactSubmissionsTable($pdo);
        echo "<p style='color: green;'>✅ Contact submissions table created/verified</p>";
        
        // Show submission stats
        $stats = getSubmissionStats($pdo);
        if (!empty($stats)) {
            echo "<h2>Submission Statistics</h2>";
            echo "<p><strong>Total Submissions:</strong> " . ($stats['total'] ?? 0) . "</p>";
            echo "<p><strong>Recent (24h):</strong> " . ($stats['recent_24h'] ?? 0) . "</p>";
            echo "<p><strong>Email Failures:</strong> " . ($stats['email_failed'] ?? 0) . "</p>";
            
            if (!empty($stats['by_status'])) {
                echo "<p><strong>By Status:</strong></p><ul>";
                foreach ($stats['by_status'] as $status => $count) {
                    echo "<li>" . ucfirst($status) . ": $count</li>";
                }
                echo "</ul>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Test email configuration
    try {
        $config = getContactFormSettings($pdo, $_GET['section_id'] ?? null);
        echo "<p style='color: green;'>✅ Email configuration loaded</p>";
        echo "<pre>" . htmlspecialchars(print_r(array_keys($config), true)) . "</pre>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Configuration error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Show recent submissions
    try {
        $recentSubmissions = getContactSubmissions($pdo, 10);
        if (!empty($recentSubmissions)) {
            echo "<h2>Recent Submissions (Last 10)</h2>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Subject</th><th>Status</th><th>Submitted</th><th>Email Sent</th></tr>";
            foreach ($recentSubmissions as $submission) {
                $statusColor = match($submission['status']) {
                    'sent' => 'green',
                    'failed' => 'red',
                    'pending' => 'orange',
                    default => 'black'
                };
                echo "<tr>";
                echo "<td>" . $submission['id'] . "</td>";
                echo "<td>" . htmlspecialchars($submission['name']) . "</td>";
                echo "<td>" . htmlspecialchars($submission['email']) . "</td>";
                echo "<td>" . htmlspecialchars(substr($submission['subject'], 0, 30)) . "...</td>";
                echo "<td style='color: $statusColor; font-weight: bold;'>" . ucfirst($submission['status']) . "</td>";
                echo "<td>" . $submission['submitted_at'] . "</td>";
                echo "<td>" . ($submission['email_sent'] ? '✅' : '❌') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No submissions found.</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error fetching submissions: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Show server info
    echo "<h2>Server Information</h2>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    echo "<p><strong>Mail Function:</strong> " . (function_exists('mail') ? '✅ Available' : '❌ Not Available') . "</p>";
    echo "<p><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? '✅ Available' : '❌ Not Available') . "</p>";
    echo "<p><strong>PDO:</strong> " . (extension_loaded('pdo') ? '✅ Available' : '❌ Not Available') . "</p>";
    echo "<p><strong>Error Reporting:</strong> " . error_reporting() . "</p>";
    echo "<p><strong>Display Errors:</strong> " . ini_get('display_errors') . "</p>";
    echo "<p><strong>Log File:</strong> contact_form_debug.log</p>";
    
    // Test form submission endpoint
    echo "<h2>Test Form Submission</h2>";
    echo "<form method='POST' action=''>";
    echo "<input type='hidden' name='section_id' value='1'>";
    echo "<p><label>Name: <input type='text' name='name' value='Test User' required></label></p>";
    echo "<p><label>Email: <input type='email' name='email' value='test@example.com' required></label></p>";
    echo "<p><label>Subject: <input type='text' name='subject' value='Test Submission' required></label></p>";
    echo "<p><label>Message: <textarea name='message' required>This is a test message to verify the contact form is working correctly.</textarea></label></p>";
    echo "<p><input type='submit' value='Test Submit'></p>";
    echo "</form>";
    
    echo "</body></html>";
    exit;
}

// Admin endpoint to view submissions
if (isset($_GET['admin']) && $_GET['admin'] === 'submissions') {
    // Basic authentication (you should implement proper admin authentication)
    if (!isset($_GET['auth']) || $_GET['auth'] !== 'admin123') {
        http_response_code(401);
        echo "Unauthorized - Add ?auth=admin123 to access";
        exit;
    }
    
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    $status = $_GET['status'] ?? null;
    
    echo "<!DOCTYPE html><html><head><title>Contact Submissions Admin</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .stats { background: #f0f0f0; padding: 15px; margin: 15px 0; border-radius: 5px; }
        .nav { margin: 10px 0; }
        .nav a { margin-right: 15px; text-decoration: none; color: #007cba; }
        .nav a:hover { text-decoration: underline; }
        .message { max-width: 300px; word-wrap: break-word; }
    </style>";
    echo "</head><body>";
    
    echo "<h1>Contact Form Submissions</h1>";
    
    // Navigation
    echo "<div class='nav'>";
    echo "<a href='?admin=submissions&auth=admin123'>All</a>";
    echo "<a href='?admin=submissions&auth=admin123&status=sent'>Sent</a>";
    echo "<a href='?admin=submissions&auth=admin123&status=failed'>Failed</a>";
    echo "<a href='?admin=submissions&auth=admin123&status=pending'>Pending</a>";
    echo "<a href='?debug=test'>Debug Test</a>";
    echo "</div>";
    
    try {
        $stats = getSubmissionStats($pdo);
        echo "<div class='stats'>";
        echo "<strong>Statistics:</strong> ";
        echo "Total: " . ($stats['total'] ?? 0) . " | ";
        echo "Recent (24h): " . ($stats['recent_24h'] ?? 0) . " | ";
        echo "Email Failures: " . ($stats['email_failed'] ?? 0);
        
        if (!empty($stats['by_status'])) {
            echo " | Status: ";
            foreach ($stats['by_status'] as $statusName => $count) {
                echo ucfirst($statusName) . ": $count ";
            }
        }
        echo "</div>";
        
        $submissions = getContactSubmissions($pdo, $limit, $offset, $status);
        
        if (!empty($submissions)) {
            echo "<h2>Submissions" . ($status ? " (" . ucfirst($status) . ")" : "") . "</h2>";
            echo "<table>";
            echo "<tr>";
            echo "<th>ID</th><th>Name</th><th>Email</th><th>Subject</th><th>Message</th>";
            echo "<th>IP</th><th>Status</th><th>Email Sent</th><th>Submitted</th><th>Error</th>";
            echo "</tr>";
            
            foreach ($submissions as $submission) {
                $statusColor = match($submission['status']) {
                    'sent' => 'green',
                    'failed' => 'red',
                    'pending' => 'orange',
                    default => 'black'
                };
                
                echo "<tr>";
                echo "<td>" . $submission['id'] . "</td>";
                echo "<td>" . htmlspecialchars($submission['name']) . "</td>";
                echo "<td>" . htmlspecialchars($submission['email']) . "</td>";
                echo "<td>" . htmlspecialchars(substr($submission['subject'], 0, 30)) . "...</td>";
                echo "<td class='message'>" . htmlspecialchars(substr($submission['message'], 0, 100)) . "...</td>";
                echo "<td>" . htmlspecialchars($submission['ip_address']) . "</td>";
                echo "<td style='color: $statusColor; font-weight: bold;'>" . ucfirst($submission['status']) . "</td>";
                echo "<td>" . ($submission['email_sent'] ? '✅' : '❌') . "</td>";
                echo "<td>" . $submission['submitted_at'] . "</td>";
                echo "<td>" . htmlspecialchars(substr($submission['email_error'] ?? '', 0, 50)) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Pagination
            echo "<div class='nav'>";
            if ($offset > 0) {
                $prevOffset = max(0, $offset - $limit);
                $prevUrl = "?admin=submissions&auth=admin123&offset=$prevOffset&limit=$limit";
                if ($status) $prevUrl .= "&status=$status";
                echo "<a href='$prevUrl'>← Previous Page</a>";
            }
            if (count($submissions) == $limit) {
                $nextOffset = $offset + $limit;
                $nextUrl = "?admin=submissions&auth=admin123&offset=$nextOffset&limit=$limit";
                if ($status) $nextUrl .= "&status=$status";
                echo "<a href='$nextUrl'>Next Page →</a>";
            }
            echo "</div>";
            
        } else {
            echo "<p>No submissions found" . ($status ? " with status: " . ucfirst($status) : "") . ".</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</body></html>";
    exit;
}

// Export submissions to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Basic authentication
    if (!isset($_GET['auth']) || $_GET['auth'] !== 'admin123') {
        http_response_code(401);
        echo "Unauthorized";
        exit;
    }
    
    try {
        $status = $_GET['status'] ?? null;
        $submissions = getContactSubmissions($pdo, 10000, 0, $status); // Get up to 10k records
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="contact_submissions_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'ID', 'Name', 'Email', 'Subject', 'Message', 'IP Address', 
            'User Agent', 'Section ID', 'Submitted At', 'Status', 
            'Email Sent', 'Email Sent At', 'Email Error'
        ]);
        
        // CSV Data
        foreach ($submissions as $submission) {
            fputcsv($output, [
                $submission['id'],
                $submission['name'],
                $submission['email'],
                $submission['subject'],
                $submission['message'],
                $submission['ip_address'],
                $submission['user_agent'],
                $submission['section_id'],
                $submission['submitted_at'],
                $submission['status'],
                $submission['email_sent'] ? 'Yes' : 'No',
                $submission['email_sent_at'],
                $submission['email_error']
            ]);
        }
        
        fclose($output);
        
    } catch (Exception $e) {
        echo "Export error: " . $e->getMessage();
    }
    exit;
}

// Main execution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logDebug("Processing POST request");
    $result = processContactForm();
    
    // Only redirect for non-AJAX requests
    if (!$isAjax) {
        // Redirect back to the referring page
        $redirectUrl = $_SERVER['HTTP_REFERER'] ?? '/';
        logDebug("Redirecting to: " . $redirectUrl);
        header("Location: $redirectUrl");
        exit;
    }
    
} else {
    logDebug("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    if ($isAjax) {
        sendAjaxResponse(false, "Invalid request method");
    } else {
        // Show simple usage information for GET requests
        echo "<!DOCTYPE html><html><head><title>Contact Form Handler</title></head><body>";
        echo "<h1>Contact Form Handler</h1>";
        echo "<p>This script handles contact form submissions via POST requests.</p>";
        echo "<h2>Available Endpoints:</h2>";
        echo "<ul>";
        echo "<li><a href='?debug=test'>Debug Test</a> - Test database and configuration</li>";
        echo "<li><a href='?admin=submissions&auth=admin123'>Admin Panel</a> - View submissions</li>";
        echo "<li><a href='?export=csv&auth=admin123'>Export CSV</a> - Download submissions as CSV</li>";
        echo "</ul>";
        echo "<p><strong>Note:</strong> Replace 'admin123' with your actual admin password.</p>";
        echo "</body></html>";
        exit;
    }
}
?>