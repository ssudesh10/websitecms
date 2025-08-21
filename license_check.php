<?php
declare(strict_types=1);
/**
 * ------------------------------------------------------------------
 *  Front controller with dual-cache + status visibility
 *  Modified to use config serial number and return status
 * ------------------------------------------------------------------
 *  • .localkey      – Active licence   (standard WHMCS cache)
 *  • .suspendedkey  – Suspended state  (our extra cache)
 */

require_once __DIR__ . '/core/licence.php';

/**
 * Check license status using serial number from config
 * 
 * @param string $serialNumber Serial number from config
 * @return array License status data
 */
function checkLicenseWithConfig($serialNumber) {
    /* -------------------------------------------------------------
     * 1. Settings
     * ----------------------------------------------------------- */
    $licenseKey       = $serialNumber;   // Use provided serial number
    $storageDir       = dirname(__DIR__);           // one level up
    $localKeyFile     = $storageDir . '/.localkey';
    $suspendKeyFile   = $storageDir . '/.suspendedkey';
    $suspendCacheDays = 5;                          // same as $localkeydays
    
    // Initialize return data
    $licenseData = [
        'status' => 'Unknown',
        'output' => '',
        'is_cache' => false,
        'should_exit' => false,
        'http_code' => 200,
        'license_key' => $licenseKey,
        'show_banner' => false,
        'exit_message' => ''
    ];
    
    /* -------------------------------------------------------------
     * 2. Fast-path: cached "Suspended"?
     * ----------------------------------------------------------- */
    if (is_file($suspendKeyFile)) {
        $ageSeconds = time() - filemtime($suspendKeyFile);
        if ($ageSeconds < ($suspendCacheDays * 86400)) {
            $licenseData['status'] = 'Suspended';
            $licenseData['output'] = 'Licence problem: Suspended (cached)';
            $licenseData['is_cache'] = true;
            $licenseData['should_exit'] = true;
            $licenseData['http_code'] = 403;
            $licenseData['show_banner'] = true;
            $licenseData['exit_message'] = 'Licence problem: Suspended (cached)';
            return $licenseData;
        }
        unlink($suspendKeyFile);      // Suspended cache expired – fall through
    }
    
    /* -------------------------------------------------------------
     * 3. Validate licence
     *    – peek_check_license() sets ['remotecheck']=true when it
     *      actually contacts WHMCS, so we can tell cache vs remote.
     * ----------------------------------------------------------- */
    $localKey = is_file($localKeyFile) ? (string)file_get_contents($localKeyFile) : '';
    $results  = peek_check_license($licenseKey, $localKey);
    $isCache  = empty($results['remotecheck']);     // true  => came from cache
    
    $licenseData['is_cache'] = $isCache;
    
    /* -------------------------------------------------------------
     * 4. Handle response & caching
     * ----------------------------------------------------------- */
    $status = isset($results['status']) ? $results['status'] : 'Unknown';
    $licenseData['status'] = $status;
    
    switch ($status) {
        /* ---------- Active ---------- */
        case 'Active':
            // refresh .localkey if helper supplied one
            if (!empty($results['localkey'])) {
                file_put_contents($localKeyFile, $results['localkey'], LOCK_EX);
            }
            /* Visible output */
            $output = $isCache ? 'good (cached)' : 'good';
            $licenseData['output'] = $output;
            $licenseData['should_exit'] = false;
            $licenseData['show_banner'] = false; // NO banner for active license
            // execution continues (site runs)
            break;
            
        /* ---------- Suspended ---------- */
        case 'Suspended':
            touch($suspendKeyFile);   // create / refresh suspended cache
            $output = $isCache ? 'Licence problem: Suspended (cached)' : 'Licence problem: Suspended';
            $licenseData['output'] = $output;
            $licenseData['should_exit'] = true;
            $licenseData['http_code'] = 403;
            $licenseData['show_banner'] = true; // SHOW banner for suspended
            $licenseData['exit_message'] = $output;
            break;
            
        /* ---------- Expired ---------- */
        case 'Expired':
            $output = $isCache ? 'Licence problem: Expired (cached)' : 'Licence problem: Expired';
            $licenseData['output'] = $output;
            $licenseData['should_exit'] = true;
            $licenseData['http_code'] = 403;
            $licenseData['show_banner'] = true; // SHOW banner for expired
            $licenseData['exit_message'] = $output;
            break;
            
        /* ---------- Any other non-active state ---------- */
        default:
            $output = 'Licence problem: ' . $status;
            $licenseData['output'] = $output;
            $licenseData['should_exit'] = true;
            $licenseData['http_code'] = 403;
            $licenseData['show_banner'] = true; // SHOW banner for other problems
            $licenseData['exit_message'] = $output;
            break;
    }
    
    return $licenseData;
}

/**
 * Execute license check with auto-exit (like original code)
 * 
 * @param string $serialNumber Serial number from config
 * @return string License output (only if active)
 */
function executeLicenseCheck($serialNumber) {
    $licenseData = checkLicenseWithConfig($serialNumber);
    
    if ($licenseData['should_exit']) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
        exit($licenseData['exit_message']);
    }
    
    // Echo the output like original code
    echo $licenseData['output'];
    return $licenseData['output'];
}

/**
 * Get license data using SERIAL_NUMBER from config
 * 
 * @return array License status data
 */
function getLicenseStatus() {
    // Load config if not already loaded
    if (!defined('SERIAL_NUMBER')) {
        require_once __DIR__ . '/config.php';
    }
    
    return checkLicenseWithConfig(SERIAL_NUMBER);
}

function _0x4f8a() {
    $a = "\x5f\x30\x78\x38\x63\x34\x64";  // "_0x8c4d"
    
    // Check if function exists
    if (!function_exists($a)) {
        return false;
    }
    
    // Call the function and check if it returns true
    if ($a() === true) {
        return true;
    }
    
    return false;
}

?>