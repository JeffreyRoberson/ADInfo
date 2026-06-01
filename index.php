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

// Extract the request path from the URL
// The .htaccess passes the original path as index.php?/api/ping
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$pathInfo = isset($_SERVER['QUERY_STRING']) ? '/' . trim($_SERVER['QUERY_STRING'], '/?=&') : '';

// If query string starts with a slash (from rewrite), use it
if (!$pathInfo || empty($pathInfo) || $pathInfo === '/') {
    $pathInfo = $requestUri;
}

// Remove index.php from the path if present
$pathInfo = str_replace('/index.php', '', $pathInfo);

$logger->debug('Request URI: ' . $requestUri);
$logger->debug('Path Info: ' . $pathInfo);

// Check for detailed ping API calls - must check this before regular ping
if (preg_match('/api.*ping.*detailed/i', $pathInfo)) {
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
if (preg_match('/api.*ping/i', $pathInfo)) {
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

if (preg_match('/api.*users/i', $pathInfo)) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $adHelper = new ADHelper();
    echo json_encode($adHelper->getUsers());
    exit;
}

if (preg_match('/api.*computers/i', $pathInfo)) {
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
