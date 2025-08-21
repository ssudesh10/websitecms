<?php
// Updated SMTP function with proper SSL support for port 465
function sendViaSMTPFixed($emailConfig, $to, $subject, $htmlBody, $replyTo = null) {
    $host = $emailConfig['smtp_host'];
    $port = intval($emailConfig['smtp_port'] ?? 587);
    $username = $emailConfig['smtp_username'];
    $password = $emailConfig['smtp_password'];
    
    // Decrypt password if it appears to be encrypted
    if (strlen($password) > 20) {
        $password = decryptSMTPPassword($password);
    }
    
    logDebug("Attempting SMTP connection", [
        'host' => $host,
        'port' => $port,
        'username' => $username
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
    
    // Create socket connection with shorter timeout
    $socket = stream_socket_client(
        $socketAddress,
        $errno,
        $errstr,
        15, // Reduced timeout to 15 seconds
        STREAM_CLIENT_CONNECT,
        $context
    );
    
    if (!$socket) {
        throw new Exception("SMTP connection failed: $errstr ($errno)");
    }
    
    // Set socket timeout
    stream_set_timeout($socket, 30);
    
    // Helper functions
    $getResponse = function() use ($socket) {
        $response = '';
        $startTime = time();
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
            
            // Prevent infinite loops
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
        
        $response = $sendCommand(base64_encode($username));
        if (strpos($response, '334') !== 0) {
            throw new Exception("Username authentication failed: $response");
        }
        
        $response = $sendCommand(base64_encode($password), true); // Hide password in logs
        if (strpos($response, '235') !== 0) {
            throw new Exception("Password authentication failed: $response");
        }
        
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
        
        // Build email headers and body
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
        $emailData .= "Content-Transfer-Encoding: 8bit\r\n";
        $emailData .= "Date: " . date('r') . "\r\n";
        $emailData .= "Message-ID: <" . time() . "." . uniqid() . "@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">\r\n";
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

// Test function for immediate testing
function testSMTPQuick() {
    // Include config again to ensure $pdo is available
    require_once 'config.php';
    
    try {
        // Get section 68 configuration
        $stmt = $pdo->prepare("SELECT content FROM page_sections WHERE id = 68");
        $stmt->execute();
        $section = $stmt->fetch();
        
        if (!$section) {
            throw new Exception("Section 68 not found");
        }
        
        // Parse configuration
        $emailConfig = [];
        $configLines = explode("\n", trim($section['content']));
        foreach ($configLines as $line) {
            $line = trim($line);
            if (!empty($line) && strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $emailConfig[trim($key)] = trim($value);
            }
        }
        
        echo "Testing SMTP with configuration:<br>";
        echo "Host: " . $emailConfig['smtp_host'] . "<br>";
        echo "Port: " . $emailConfig['smtp_port'] . "<br>";
        echo "Username: " . $emailConfig['smtp_username'] . "<br>";
        echo "<br>";
        
        // Test email
        $testSubject = "SMTP Test - " . date('Y-m-d H:i:s');
        $testBody = "<h2>SMTP Test Email</h2><p>This is a test email to verify SMTP configuration is working.</p>";
        
        if (sendViaSMTPFixed($emailConfig, $emailConfig['recipient_email'], $testSubject, $testBody)) {
            echo "✅ SMTP test successful! Email sent to " . $emailConfig['recipient_email'];
        }
        
    } catch (Exception $e) {
        echo "❌ SMTP test failed: " . $e->getMessage();
    }
}

// Simple password decryption function
function decryptSMTPPassword($encryptedPassword) {
    $key = 'your-secure-password-key-2024'; // Must match your JavaScript key
    
    try {
        $data = base64_decode($encryptedPassword, true);
        if ($data === false || strlen($data) < 28) {
            return $encryptedPassword;
        }
        
        $iv = substr($data, 0, 12);
        $tag = substr($data, -16);
        $encrypted = substr($data, 12, -16);
        
        $derivedKey = hash_pbkdf2('sha256', $key, 'salt', 100000, 32, true);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $derivedKey, OPENSSL_RAW_DATA, $iv, $tag);
        
        return ($decrypted !== false) ? $decrypted : $encryptedPassword;
        
    } catch (Exception $e) {
        return $encryptedPassword;
    }
}

function logDebug($message, $context = []) {
    $contextStr = empty($context) ? '' : ' | ' . json_encode($context);
    echo "<div style='color: blue; font-family: monospace;'>" . date('H:i:s') . " - $message$contextStr</div>";
}

// Run test if requested
if (isset($_GET['test_smtp'])) {
    echo "<h2>🧪 SMTP Test Results</h2>";
    testSMTPQuick();
    exit;
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>SMTP Connection Fix</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .button { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 10px 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 SMTP Connection Fix</h1>
        
        <p>Your SMTP connection is timing out on port 465. Here are the solutions:</p>
        
        <h2>Option 1: Test Fixed SMTP Connection</h2>
        <a href="?test_smtp=1" class="button">🧪 Test SMTP Now</a>
        
        <h2>Option 2: Switch to Port 587</h2>
        <p>Update your database configuration to use port 587 instead of 465:</p>
        <code>
            UPDATE page_sections SET content = 'recipient_email=reminder@peekhosting.net<br>
            smtp_host=mail.peekhosting.net<br>
            smtp_port=587<br>
            smtp_username=reminder@peekhosting.net<br>
            smtp_password=your-encrypted-password' WHERE id = 68;
        </code>
        
        <h2>Option 3: Contact Your Host</h2>
        <p>Contact PeekHosting support and ask:</p>
        <ul>
            <li>"Is SMTP available on mail.peekhosting.net?"</li>
            <li>"Which ports should I use - 587 or 465?"</li>
            <li>"Do I need special SSL settings?"</li>
        </ul>
        
        <h2>Option 4: Use Alternative SMTP</h2>
        <p>Consider using Gmail SMTP as a reliable alternative:</p>
        <code>
            smtp_host=smtp.gmail.com<br>
            smtp_port=587<br>
            smtp_username=youremail@gmail.com<br>
            smtp_password=your-app-password
        </code>
    </div>
</body>
</html>