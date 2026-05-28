<?php
/**
 * Direct DNS Test Script
 * Access at: http://your-server/adinfo/test-dns.php
 */

require_once 'config.php';
require_once 'includes/Logger.php';
require_once 'includes/DNSResolver.php';

$logger = new Logger();
$dnsResolver = new DNSResolver($logger);

// Test with a real computer name from your environment
$testComputers = ['COMPUTER01', 'SERVER01', 'WORKSTATION01'];

?>
<!DOCTYPE html>
<html>
<head>
    <title>DNS Test</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        .test { background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid #ccc; }
        .success { border-left-color: #27ae60; }
        .error { border-left-color: #e74c3c; }
        .warning { border-left-color: #f39c12; }
        pre { background: #eee; padding: 10px; overflow-x: auto; }
        input { padding: 10px; width: 200px; }
        button { padding: 10px 20px; }
    </style>
</head>
<body>
    <h1>DNS Resolution Tester</h1>
    
    <form method="POST">
        <input type="text" name="hostname" placeholder="Enter computer name" value="<?php echo isset($_POST['hostname']) ? htmlspecialchars($_POST['hostname']) : 'COMPUTER01'; ?>">
        <button type="submit">Test</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['hostname'])) {
        $testHostname = trim($_POST['hostname']);
        
        echo '<div class="test warning">';
        echo '<h2>Testing: ' . htmlspecialchars($testHostname) . '</h2>';
        
        $ips = $dnsResolver->resolve($testHostname);
        
        if (!empty($ips)) {
            echo '<div class="test success">';
            echo '<h3>✓ SUCCESS</h3>';
            echo '<p>Found ' . count($ips) . ' IP address(es):</p>';
            echo '<ul>';
            foreach ($ips as $ip) {
                echo '<li><code>' . htmlspecialchars($ip) . '</code></li>';
            }
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div class="test error">';
            echo '<h3>✗ FAILED</h3>';
            echo '<p>No IP addresses found for: ' . htmlspecialchars($testHostname) . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    ?>

    <div class="test">
        <h3>Pre-configured Test Computers:</h3>
        <p>Click to test (replace with your actual computer names):</p>
        <?php foreach ($testComputers as $computer): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="hostname" value="<?php echo htmlspecialchars($computer); ?>">
                <button type="submit"><?php echo htmlspecialchars($computer); ?></button>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="test">
        <h3>Check Logs:</h3>
        <p>View detailed logs from the last test:</p>
        <pre><?php
            $logFile = __DIR__ . '/logs/app.log';
            if (file_exists($logFile)) {
                $lines = array_slice(file($logFile), -30);
                foreach ($lines as $line) {
                    echo htmlspecialchars($line);
                }
            } else {
                echo 'Log file not found';
            }
        ?></pre>
    </div>
</body>
</html>
