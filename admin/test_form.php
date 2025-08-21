<?php
// Add this at the VERY TOP of your settings.php (before any HTML)
error_log("=== CHECKING FOR REDIRECTS ===");

// Check if headers are already sent
if (headers_sent($file, $line)) {
    error_log("Headers already sent in file: $file on line: $line");
} else {
    error_log("No headers sent yet - good");
}

// Monitor for any header() calls
register_shutdown_function(function() {
    $headers = headers_list();
    if (!empty($headers)) {
        error_log("Final headers: " . print_r($headers, true));
        foreach ($headers as $header) {
            if (strpos(strtolower($header), 'location:') !== false) {
                error_log("REDIRECT FOUND: " . $header);
            }
        }
    }
});

require_once '../config.php';
require_once '../function.php';
requireLogin();

// Continue with your normal settings.php code...
?>