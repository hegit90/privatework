<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Application Logger
 */
class Logger
{
    private const EMERGENCY = 'emergency';
    private const ALERT = 'alert';
    private const CRITICAL = 'critical';
    private const ERROR = 'error';
    private const WARNING = 'warning';
    private const NOTICE = 'notice';
    private const INFO = 'info';
    private const DEBUG = 'debug';

    private string $logPath;
    private string $level;

    public function __construct()
    {
        $this->logPath = STORAGE_PATH . '/logs';
        $this->level = env('LOG_LEVEL', 'info');
    }

    /**
     * Log emergency message
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Log alert message
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log notice message
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Write log message
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";

        $filename = $this->logPath . '/' . date('Y-m-d') . '.log';

        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }

        file_put_contents($filename, $logMessage, FILE_APPEND);

        // Cleanup old logs
        $this->cleanupOldLogs();
    }

    /**
     * Check if message should be logged
     */
    private function shouldLog(string $level): bool
    {
        $levels = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::NOTICE => 2,
            self::WARNING => 3,
            self::ERROR => 4,
            self::CRITICAL => 5,
            self::ALERT => 6,
            self::EMERGENCY => 7,
        ];

        return $levels[$level] >= ($levels[$this->level] ?? 1);
    }

    /**
     * Cleanup old log files
     */
    private function cleanupOldLogs(): void
    {
        $maxFiles = (int)env('LOG_MAX_FILES', 14);
        $files = glob($this->logPath . '/*.log');

        if (count($files) <= $maxFiles) {
            return;
        }

        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        $filesToDelete = array_slice($files, 0, count($files) - $maxFiles);

        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }
}
