<?php
/**
 * Direct API Test
 * Access at: http://your-server/adinfo/api-test.php?hostname=COMPUTER01
 */

require_once 'config.php';
require_once 'includes/DNSResolver.php';
require_once 'includes/Logger.php';

$logger = new Logger();
$dnsResolver = new DNSResolver($logger);

header('Content-Type: application/json');

$hostname = isset($_GET['hostname']) ? trim($_GET['hostname']) : null;

if (!$hostname) {
    http_response_code(400);
    echo json_encode([
        'error' => 'hostname parameter required',
        'success' => false
    ]);
    exit;
}

try {
    $logger->info('API Test: Resolving ' . $hostname);
    $ips = $dnsResolver->resolve($hostname);
    
    $response = [
        'hostname' => $hostname,
        'ips' => $ips,
        'display' => !empty($ips) ? implode(', ', $ips) : 'Not Available',
        'count' => count($ips),
        'success' => true
    ];
    
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'hostname' => $hostname,
        'error' => $e->getMessage(),
        'success' => false
    ], JSON_PRETTY_PRINT);
}
