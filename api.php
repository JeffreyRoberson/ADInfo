<?php
/**
 * Ping API Handler
 * Handles detailed and basic ping requests
 */

require_once 'config.php';
require_once 'includes/PingHelper.php';
require_once 'includes/Logger.php';

$logger = new Logger();

// Get the action from GET parameters
$action = $_GET['action'] ?? 'ping';
$hostname = $_GET['hostname'] ?? null;
$hostname = $hostname ? trim($hostname) : null;

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!$hostname) {
    http_response_code(400);
    echo json_encode(['error' => 'hostname parameter required', 'success' => false]);
    exit;
}

try {
    $pingHelper = new PingHelper($logger);
    
    if ($action === 'ping-detailed' || $action === 'detailed') {
        $logger->info('API PING-DETAILED: hostname=' . $hostname);
        $result = $pingHelper->pingDetailed($hostname);
        echo json_encode($result);
    } else {
        $logger->info('API PING: hostname=' . $hostname);
        $isOnline = $pingHelper->isOnline($hostname);
        echo json_encode([
            'hostname' => $hostname,
            'online' => $isOnline,
            'status' => $isOnline ? 'online' : 'offline',
            'success' => true
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'hostname' => $hostname,
        'error' => $e->getMessage(),
        'success' => false
    ]);
}
?>
