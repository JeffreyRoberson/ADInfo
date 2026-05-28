<?php
/**
 * Setup script to initialize cache directories and verify configuration
 * Run this once after installation
 */

require_once 'config.php';

echo "==================================================\n";
echo "Active Directory Monitoring - Setup Script\n";
echo "==================================================\n\n";

// Check PHP version
echo "[1/5] Checking PHP version... ";
if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    echo "✓ PHP " . PHP_VERSION . "\n\n";
} else {
    echo "✗ PHP 7.4+ required (Current: " . PHP_VERSION . ")\n\n";
    exit(1);
}

// Check required extensions
echo "[2/5] Checking required extensions...\n";
$extensions = ['ldap', 'json'];
$missingExtensions = [];

foreach ($extensions as $ext) {
    echo "      - $ext: ";
    if (extension_loaded($ext)) {
        echo "✓\n";
    } else {
        echo "✗ NOT INSTALLED\n";
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "\n✗ Missing extensions: " . implode(', ', $missingExtensions) . "\n";
    echo "   Install with: sudo apt-get install php-" . implode(' php-', $missingExtensions) . "\n\n";
    exit(1);
}
echo "\n";

// Create cache directory
echo "[3/5] Creating cache directory...\n";
$cacheDir = CACHE_DIR;
if (!is_dir($cacheDir)) {
    if (@mkdir($cacheDir, 0755, true)) {
        echo "      ✓ Created: $cacheDir\n";
    } else {
        echo "      ✗ Failed to create: $cacheDir\n";
        echo "      Please create manually: mkdir -p $cacheDir\n";
        exit(1);
    }
} else {
    echo "      ✓ Already exists: $cacheDir\n";
}

// Check directory permissions
echo "\n[4/5] Checking directory permissions...\n";
$cacheDir = CACHE_DIR;

if (is_dir($cacheDir)) {
    if (is_writable($cacheDir)) {
        echo "      ✓ Cache directory is writable\n";
        $perms = substr(sprintf('%o', fileperms($cacheDir)), -4);
        echo "      Permissions: $perms\n";
    } else {
        echo "      ✗ Cache directory is NOT writable\n";
        echo "      Run: chmod 755 $cacheDir\n";
        echo "      Or: sudo chown www-data:www-data $cacheDir\n";
        exit(1);
    }
} else {
    echo "      ✗ Cache directory does not exist\n";
    exit(1);
}

// Test Active Directory connection
echo "\n[5/5] Testing Active Directory connection...\n";
echo "      AD_SERVER: " . AD_SERVER . "\n";
echo "      AD_PORT: " . AD_PORT . "\n";
echo "      AD_USE_SSL: " . (AD_USE_SSL ? 'Yes' : 'No') . "\n";
echo "      AD_SEARCH_BASE: " . AD_SEARCH_BASE . "\n\n";

try {
    $protocol = AD_USE_SSL ? 'ldaps://' : 'ldap://';
    $server = $protocol . AD_SERVER . ':' . AD_PORT;
    
    $connection = ldap_connect($server);
    
    if (!$connection) {
        echo "      ✗ Failed to connect to AD server\n";
        echo "      Check AD_SERVER and AD_PORT in config.php\n";
        exit(1);
    }
    
    ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, 5);
    
    $bind = @ldap_bind($connection, AD_USER, AD_PASSWORD);
    
    if (!$bind) {
        $error = ldap_error($connection);
        echo "      ✗ Failed to bind to AD: $error\n";
        echo "      Check AD_USER and AD_PASSWORD in config.php\n";
        exit(1);
    }
    
    echo "      ✓ Successfully connected and bound to AD\n";
    
    // Test a query
    $search = @ldap_search($connection, AD_SEARCH_BASE, '(objectClass=computer)', ['cn'], 0, 1);
    
    if ($search) {
        echo "      ✓ Successfully searched AD\n";
    } else {
        echo "      ⚠ Search failed (check AD_SEARCH_BASE): " . ldap_error($connection) . "\n";
    }
    
    ldap_close($connection);
    
} catch (Exception $e) {
    echo "      ✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n==================================================\n";
echo "✓ Setup completed successfully!\n";
echo "==================================================\n";
echo "\nNext steps:\n";
echo "1. Access the application at: http://localhost\n";
echo "2. Check logs/app.log for any issues\n";
echo "3. Monitor cache/ip_cache.json for cached IPs\n\n";
?>
