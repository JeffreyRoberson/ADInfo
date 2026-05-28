<?php
/**
 * Diagnostic Tool for Troubleshooting
 * Access at: http://your-server/adinfo/diagnostic.php
 */

require_once 'config.php';
require_once 'includes/Logger.php';
require_once 'includes/DNSResolver.php';

$logger = new Logger();
$dnsResolver = new DNSResolver($logger);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AD Monitoring - Diagnostics</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2 {
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        pre {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        input, button {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        button {
            background: #3498db;
            color: white;
            cursor: pointer;
        }
        button:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <h1>🔧 AD Monitoring Diagnostics</h1>

    <div class="section">
        <h2>1. PHP Extensions Check</h2>
        <?php
        $extensions = ['ldap', 'json', 'sockets'];
        foreach ($extensions as $ext) {
            $status = extension_loaded($ext) ? 'success' : 'error';
            $text = extension_loaded($ext) ? '✓ Available' : '✗ Missing';
            echo "<p><span class='$status'>$ext:</span> $text</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>2. DNS Functions Check</h2>
        <?php
        $functions = ['dns_get_record', 'gethostbyname', 'gethostbyaddr', 'getaddrinfo'];
        foreach ($functions as $func) {
            $status = function_exists($func) ? 'success' : 'error';
            $text = function_exists($func) ? '✓ Available' : '✗ Missing';
            echo "<p><span class='$status'>$func():</span> $text</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>3. Configuration Check</h2>
        <p>AD_SERVER: <code><?php echo AD_SERVER; ?></code></p>
        <p>AD_PORT: <code><?php echo AD_PORT; ?></code></p>
        <p>AD_USE_SSL: <code><?php echo AD_USE_SSL ? 'Yes' : 'No'; ?></code></p>
        <p>AD_SEARCH_BASE: <code><?php echo AD_SEARCH_BASE; ?></code></p>
        <p>DNS_DOMAIN: <code><?php echo DNS_DOMAIN; ?></code></p>
    </div>

    <div class="section">
        <h2>4. Manual DNS Test</h2>
        <form method="POST">
            <input type="text" name="hostname" placeholder="Enter computer name (e.g., COMPUTER01)" value="<?php echo isset($_POST['hostname']) ? htmlspecialchars($_POST['hostname']) : ''; ?>">
            <button type="submit">Test DNS Resolution</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['hostname'])) {
            $testHostname = trim($_POST['hostname']);
            echo "<h3>Testing: " . htmlspecialchars($testHostname) . "</h3>";
            
            // Test 1: Direct gethostbyname
            echo "<h4>Test 1: gethostbyname()</h4>";
            $result = @gethostbyname($testHostname);
            if ($result && $result !== $testHostname) {
                echo "<p class='success'>✓ Result: " . htmlspecialchars($result) . "</p>";
            } else {
                echo "<p class='error'>✗ Failed or returned hostname</p>";
            }

            // Test 2: With domain appended
            echo "<h4>Test 2: gethostbyname() with domain</h4>";
            $fqdn = $testHostname . '.' . DNS_DOMAIN;
            $result = @gethostbyname($fqdn);
            if ($result && $result !== $fqdn) {
                echo "<p class='success'>✓ Result for " . htmlspecialchars($fqdn) . ": " . htmlspecialchars($result) . "</p>";
            } else {
                echo "<p class='error'>✗ Failed for " . htmlspecialchars($fqdn) . "</p>";
            }

            // Test 3: dns_get_record
            if (function_exists('dns_get_record')) {
                echo "<h4>Test 3: dns_get_record() with domain</h4>";
                $records = @dns_get_record($fqdn, DNS_A);
                if ($records && is_array($records)) {
                    echo "<p class='success'>✓ Found " . count($records) . " A record(s):</p>";
                    echo "<pre>";
                    foreach ($records as $record) {
                        if (isset($record['ip'])) {
                            echo htmlspecialchars($record['ip']) . "\n";
                        }
                    }
                    echo "</pre>";
                } else {
                    echo "<p class='error'>✗ No A records found</p>";
                }
            } else {
                echo "<p class='warning'>⚠ dns_get_record() not available</p>";
            }

            // Test 4: DNSResolver class
            echo "<h4>Test 4: DNSResolver class</h4>";
            $ips = $dnsResolver->resolve($testHostname);
            if (!empty($ips)) {
                echo "<p class='success'>✓ Resolved to: " . implode(', ', $ips) . "</p>";
            } else {
                echo "<p class='error'>✗ DNSResolver returned no results</p>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>5. Log File Check</h2>
        <?php
        $logFile = __DIR__ . '/logs/app.log';
        if (file_exists($logFile)) {
            $size = filesize($logFile);
            echo "<p class='success'>�� Log file exists (" . number_format($size) . " bytes)</p>";
            
            // Show last 20 lines
            $lines = array_slice(file($logFile), -20);
            echo "<h3>Last 20 log entries:</h3>";
            echo "<pre>";
            foreach ($lines as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>";
        } else {
            echo "<p class='error'>✗ Log file not found at: " . $logFile . "</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>6. API Test</h2>
        <p>Test the API endpoint directly:</p>
        <button onclick="testAPI()">Test /api/resolve-ip?hostname=COMPUTER01</button>
        <pre id="apiResult"></pre>
    </div>

    <script>
        function testAPI() {
            const hostname = 'COMPUTER01';
            const resultDiv = document.getElementById('apiResult');
            resultDiv.textContent = 'Testing...';

            fetch(`/api/resolve-ip?hostname=${encodeURIComponent(hostname)}`)
                .then(response => {
                    resultDiv.textContent += '\nHTTP Status: ' + response.status + '\n\n';
                    return response.json();
                })
                .then(data => {
                    resultDiv.textContent += 'Response:\n' + JSON.stringify(data, null, 2);
                })
                .catch(error => {
                    resultDiv.textContent = 'Error: ' + error.message;
                });
        }
    </script>
</body>
</html>
