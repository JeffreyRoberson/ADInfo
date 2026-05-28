<?php
/**
 * Ping Helper Class
 * Determines if a host is online or offline
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
