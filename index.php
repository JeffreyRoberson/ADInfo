<?php
/**
 * Active Directory Monitoring Dashboard
 * Main entry point
 */

require_once 'config.php';
require_once 'includes/PingHelper.php';
require_once 'includes/ADHelper.php';
require_once 'includes/Logger.php';

$logger = new Logger();

// Get the request URI - handle both direct requests and rewritten requests
$requestUri = $_SERVER['REQUEST_URI'] ?? $_SERVER['PATH_INFO'] ?? '';
if (!$requestUri && isset($_SERVER['QUERY_STRING'])) {
    $requestUri = '/' . trim($_SERVER['QUERY_STRING'], '/');
}

$logger->debug('Request URI: ' . $requestUri);

// Check for detailed ping API calls
if (preg_match('/api[\/\-]?ping[\/\-]?detailed/i', $requestUri)) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $hostname = $_GET['hostname'] ?? null;
    $hostname = $hostname ? trim($hostname) : null;
    
    $logger->info('API PING-DETAILED: hostname=' . ($hostname ?: 'null'));
    
    if (!$hostname) {
        http_response_code(400);
        echo json_encode(['error' => 'hostname parameter required', 'success' => false]);
        exit;
    }
    
    try {
        $pingHelper = new PingHelper($logger);
        $result = $pingHelper->pingDetailed($hostname);
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'hostname' => $hostname,
            'error' => $e->getMessage(),
            'success' => false
        ]);
    }
    exit;
}

// Check for ping API calls
if (preg_match('/api[\/\-]?ping/i', $requestUri)) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $hostname = $_GET['hostname'] ?? null;
    $hostname = $hostname ? trim($hostname) : null;
    
    $logger->info('API PING: hostname=' . ($hostname ?: 'null'));
    
    if (!$hostname) {
        http_response_code(400);
        echo json_encode(['error' => 'hostname parameter required', 'success' => false]);
        exit;
    }
    
    try {
        $pingHelper = new PingHelper($logger);
        $isOnline = $pingHelper->isOnline($hostname);
        
        echo json_encode([
            'hostname' => $hostname,
            'online' => $isOnline,
            'status' => $isOnline ? 'online' : 'offline',
            'success' => true
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'hostname' => $hostname,
            'error' => $e->getMessage(),
            'success' => false
        ]);
    }
    exit;
}

if (preg_match('/api[\/\-]?users/i', $requestUri)) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $adHelper = new ADHelper();
    echo json_encode($adHelper->getUsers());
    exit;
}

if (preg_match('/api[\/\-]?computers/i', $requestUri)) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $adHelper = new ADHelper();
    echo json_encode($adHelper->getComputers());
    exit;
}

// If not an API request, load the page normally
$adHelper = new ADHelper();
$users = $adHelper->getUsers();
$computers = $adHelper->getComputers();
$passwordPolicy = $adHelper->getPasswordPolicy();
$passwordPolicyMessage = $adHelper->getPasswordPolicyMessage();
$lastUpdated = date('Y-m-d H:i:s');

include 'templates/index.html';
?>
