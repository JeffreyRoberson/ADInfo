<?php
/**
 * Ping Helper Class
 * Determines if a host is online or offline and provides detailed ping information
 */

class PingHelper {
    private $logger;
    private $defaultDomain = DNS_DOMAIN;
    private $timeout = 2;

    public function __construct($logger) {
        $this->logger = $logger;
        $this->logger->info('PingHelper initialized (domain: ' . $this->defaultDomain . ')');
    }

    /**
     * Ping a host and determine if it's online
     */
    public function isOnline($hostname) {
        $cleanName = rtrim($hostname, '$');
        
        $this->logger->debug('Pinging: ' . $cleanName);
        
        // Append domain if not already present
        $fqdn = $this->appendDomain($cleanName);
        $this->logger->debug('FQDN: ' . $fqdn);
        
        // Try FQDN first
        if ($this->ping($fqdn)) {
            $this->logger->info('ONLINE: ' . $cleanName);
            return true;
        }
        
        // Try without domain
        $this->logger->debug('FQDN ping failed, trying hostname only: ' . $cleanName);
        if ($this->ping($cleanName)) {
            $this->logger->info('ONLINE: ' . $cleanName);
            return true;
        }
        
        $this->logger->info('OFFLINE: ' . $cleanName);
        return false;
    }

    /**
     * Perform a detailed ping and return comprehensive results
     */
    public function pingDetailed($hostname) {
        $cleanName = rtrim($hostname, '$');
        $this->logger->debug('Detailed ping for: ' . $cleanName);
        
        $fqdn = $this->appendDomain($cleanName);
        $result = $this->executePingDetailed($fqdn);
        
        if (!$result['success']) {
            // Try without domain
            $this->logger->debug('FQDN ping failed, trying hostname only: ' . $cleanName);
            $result = $this->executePingDetailed($cleanName);
        }
        
        return $result;
    }

    /**
     * Execute detailed ping command and parse output
     */
    private function executePingDetailed($hostname) {
        try {
            $result = [
                'success' => false,
                'online' => false,
                'hostname' => $hostname,
                'timestamp' => date('Y-m-d H:i:s'),
                'raw_output' => ''
            ];
            
            // Determine OS and use appropriate ping command
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                $cmd = 'ping -n 1 -w ' . ($this->timeout * 1000) . ' ' . escapeshellarg($hostname);
            } else {
                // Linux/Mac
                $cmd = 'ping -c 1 -W ' . $this->timeout . ' ' . escapeshellarg($hostname);
            }
            
            $this->logger->debug('Executing: ' . $cmd);
            
            $output = array();
            $returnCode = null;
            @exec($cmd, $output, $returnCode);
            
            $rawOutput = implode("\n", $output);
            $result['raw_output'] = $rawOutput;
            
            // Return code 0 = success (host is online)
            if ($returnCode === 0) {
                $result['success'] = true;
                $result['online'] = true;
                
                // Parse response time and other details
                $this->parseWindowsPingOutput($output, $result);
                $this->parseLinuxPingOutput($output, $result);
                
                $this->logger->info('ONLINE (detailed): ' . $hostname);
            } else {
                $result['success'] = true;
                $result['online'] = false;
                $this->logger->info('OFFLINE (detailed): ' . $hostname);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->debug('Detailed ping exception: ' . $e->getMessage());
            return [
                'success' => false,
                'online' => false,
                'hostname' => $hostname,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Parse Windows ping output for response time and TTL
     */
    private function parseWindowsPingOutput($output, &$result) {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            return;
        }
        
        foreach ($output as $line) {
            // Parse "Reply from X.X.X.X: bytes=32 time=XX ms TTL=XXX"
            if (preg_match('/time[=:](\d+)\s*ms.*TTL[=:](\d+)/i', $line, $matches)) {
                $result['response_time'] = intval($matches[1]);
                $result['ttl'] = intval($matches[2]);
            }
            // Parse "Bytes=X"
            if (preg_match('/bytes[=:](\d+)/i', $line, $matches)) {
                $result['bytes'] = intval($matches[1]);
            }
        }
    }

    /**
     * Parse Linux/Mac ping output for response time and TTL
     */
    private function parseLinuxPingOutput($output, &$result) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return;
        }
        
        foreach ($output as $line) {
            // Parse "64 bytes from X.X.X.X: icmp_seq=1 ttl=XX time=X.XX ms"
            if (preg_match('/(\d+)\s+bytes\s+from/', $line, $matches)) {
                $result['bytes'] = intval($matches[1]);
            }
            
            if (preg_match('/ttl[=:](\d+).*time[=:](\d+\.?\d*)\s*ms/i', $line, $matches)) {
                $result['ttl'] = intval($matches[1]);
                $result['response_time'] = floatval($matches[2]);
            } elseif (preg_match('/time[=:](\d+\.?\d*)\s*ms/i', $line, $matches)) {
                $result['response_time'] = floatval($matches[1]);
            }
        }
    }

    /**
     * Append domain to hostname if not already present
     */
    private function appendDomain($hostname) {
        if (strpos($hostname, '.') !== false) {
            return $hostname;
        }
        
        return $hostname . '.' . $this->defaultDomain;
    }

    /**
     * Ping a host using system ping command
     */
    private function ping($hostname) {
        try {
            // Determine OS and use appropriate ping command
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                $cmd = 'ping -n 1 -w ' . ($this->timeout * 1000) . ' ' . escapeshellarg($hostname);
            } else {
                // Linux/Mac
                $cmd = 'ping -c 1 -W ' . $this->timeout . ' ' . escapeshellarg($hostname);
            }
            
            $this->logger->debug('Executing: ' . $cmd);
            
            $output = null;
            $returnCode = null;
            @exec($cmd, $output, $returnCode);
            
            $this->logger->debug('Return code: ' . $returnCode);
            
            // Return code 0 = success (host is online)
            return ($returnCode === 0);
        } catch (Exception $e) {
            $this->logger->debug('Ping exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ping multiple hosts and return their status
     */
    public function checkMultiple(array $hostnames) {
        $results = [];
        
        foreach ($hostnames as $hostname) {
            $results[$hostname] = $this->isOnline($hostname);
        }
        
        return $results;
    }
}
?>
