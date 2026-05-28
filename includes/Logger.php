<?php
/**
 * Simple Logger Class
 */

class Logger {
    private $logDir;
    private $logFile;
    private const LOG_LEVELS = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    private $minLevel = 0;

    public function __construct() {
        $this->logDir = __DIR__ . '/../logs';
        $this->logFile = $this->logDir . '/app.log';

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
    }

    /**
     * Log a message
     */
    private function log($level, $message) {
        if (self::LOG_LEVELS[$level] < $this->minLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message\n";

        @error_log($logEntry, 3, $this->logFile);
    }

    /**
     * Log debug message
     */
    public function debug($message) {
        $this->log('DEBUG', $message);
    }

    /**
     * Log info message
     */
    public function info($message) {
        $this->log('INFO', $message);
    }

    /**
     * Log warning message
     */
    public function warning($message) {
        $this->log('WARNING', $message);
    }

    /**
     * Log error message
     */
    public function error($message) {
        $this->log('ERROR', $message);
    }
}
?>
