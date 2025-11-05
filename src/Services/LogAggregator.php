<?php

namespace NativeCLI\Services;

use DateTime;
use RuntimeException;

class LogAggregator
{
    private const LARAVEL_LOG_PATTERN = '/^\[(?<timestamp>\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\.\d{6})?(?:[+-]\d{2}:\d{2})?)\](?:\s+(?<env>\w+)\.)?(?<level>\w+):\s+(?<message>.+)$/';

    private array $logSources = [];
    private array $filters = [];

    public function addLogSource(string $name, string $path): self
    {
        if (!file_exists($path)) {
            return $this;
        }

        $this->logSources[$name] = $path;

        return $this;
    }

    public function filterByLevel(string $level): self
    {
        $this->filters['level'] = strtolower($level);

        return $this;
    }

    public function filterBySource(string $source): self
    {
        $this->filters['source'] = $source;

        return $this;
    }

    public function filterByDate(?string $startDate = null, ?string $endDate = null): self
    {
        if ($startDate) {
            $this->filters['start_date'] = new DateTime($startDate);
        }

        if ($endDate) {
            $this->filters['end_date'] = new DateTime($endDate);
        }

        return $this;
    }

    /**
     * Get logs with optional limit and offset
     */
    public function getLogs(int $lines = 50, int $offset = 0): array
    {
        $allLogs = [];

        foreach ($this->logSources as $sourceName => $path) {
            // Apply source filter
            if (isset($this->filters['source']) && $this->filters['source'] !== 'all' && $this->filters['source'] !== $sourceName) {
                continue;
            }

            try {
                $logs = $this->parseLogFile($path, $sourceName);
                $allLogs = array_merge($allLogs, $logs);
            } catch (RuntimeException $e) {
                // Skip unreadable log files silently or handle as needed
                continue;
            }
        }

        // Sort by timestamp (newest first)
        usort($allLogs, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        // Apply filters
        $allLogs = $this->applyFilters($allLogs);

        // Apply pagination
        return array_slice($allLogs, $offset, $lines);
    }

    /**
     * Get log count
     */
    public function getLogCount(): int
    {
        return count($this->getLogs(PHP_INT_MAX));
    }

    /**
     * Tail logs (last N lines)
     */
    public function tail(int $lines = 50): array
    {
        $logs = $this->getLogs(PHP_INT_MAX);

        return array_slice($logs, 0, $lines);
    }

    /**
     * Follow logs (for real-time monitoring)
     * Returns a callback that yields new log entries
     *
     * @param callable $callback Callback to execute for each log entry
     * @param callable|null $shouldStopCallback Optional callback that returns true when following should stop
     */
    public function follow(callable $callback, ?callable $shouldStopCallback = null): void
    {
        $filePointers = [];
        $lastPositions = [];
        $internalShouldStop = false;

        // Set up signal handler for CTRL+C (SIGINT) if no external stop callback provided
        if ($shouldStopCallback === null && function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () use (&$internalShouldStop) {
                $internalShouldStop = true;
            });
        }

        // Open file pointers and seek to end
        foreach ($this->logSources as $sourceName => $path) {
            if (isset($this->filters['source']) && $this->filters['source'] !== 'all' && $this->filters['source'] !== $sourceName) {
                continue;
            }

            $fp = fopen($path, 'r');
            if ($fp) {
                fseek($fp, 0, SEEK_END);
                $filePointers[$sourceName] = $fp;
                $lastPositions[$sourceName] = ftell($fp);
            }
        }

        // Poll for changes (loop until stop condition is met)
        while (true) {
            // Check stop conditions
            if ($shouldStopCallback !== null && $shouldStopCallback()) {
                break;
            }
            if ($shouldStopCallback === null && $internalShouldStop) {
                break;
            }

            $hasNewContent = false;

            foreach ($filePointers as $sourceName => $fp) {
                clearstatcache(true, $this->logSources[$sourceName]);
                $currentSize = filesize($this->logSources[$sourceName]);

                if ($currentSize > $lastPositions[$sourceName]) {
                    $hasNewContent = true;
                    fseek($fp, $lastPositions[$sourceName]);

                    while (($line = fgets($fp)) !== false) {
                        $logEntry = $this->parseLogLine($line, $sourceName);
                        if ($logEntry && $this->passesFilters($logEntry)) {
                            $callback($logEntry);
                        }
                    }

                    $lastPositions[$sourceName] = ftell($fp);
                }
            }

            if (!$hasNewContent) {
                usleep(100000); // 100ms
            }
        }

        // Clean up file pointers
        foreach ($filePointers as $fp) {
            fclose($fp);
        }
    }

    /**
     * Parse a log file
     */
    private function parseLogFile(string $path, string $sourceName): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException("Unable to read log file: {$path}");
        }

        $logs = [];
        $handle = fopen($path, 'r');

        if (!$handle) {
            throw new RuntimeException("Unable to open log file: {$path}");
        }

        while (($line = fgets($handle)) !== false) {
            $logEntry = $this->parseLogLine($line, $sourceName);
            if ($logEntry) {
                $logs[] = $logEntry;
            }
        }

        fclose($handle);

        return $logs;
    }

    /**
     * Parse a single log line
     */
    private function parseLogLine(string $line, string $sourceName): ?array
    {
        $line = trim($line);

        if (empty($line)) {
            return null;
        }

        // Try Laravel log format
        if (preg_match(self::LARAVEL_LOG_PATTERN, $line, $matches)) {
            return [
                'timestamp' => new DateTime($matches['timestamp']),
                'level' => strtolower($matches['level']),
                'message' => trim($matches['message']),
                'source' => $sourceName,
                'raw' => $line,
            ];
        }

        // Fallback for unstructured logs
        return [
            'timestamp' => new DateTime(),
            'level' => 'info',
            'message' => $line,
            'source' => $sourceName,
            'raw' => $line,
        ];
    }

    /**
     * Apply filters to log entries
     */
    private function applyFilters(array $logs): array
    {
        return array_values(array_filter($logs, fn ($log) => $this->passesFilters($log)));
    }

    /**
     * Check if a log entry passes all filters
     */
    private function passesFilters(array $log): bool
    {
        // Level filter
        if (isset($this->filters['level']) && $log['level'] !== $this->filters['level']) {
            return false;
        }

        // Date range filter
        if (isset($this->filters['start_date']) && $log['timestamp'] < $this->filters['start_date']) {
            return false;
        }

        if (isset($this->filters['end_date']) && $log['timestamp'] > $this->filters['end_date']) {
            return false;
        }

        return true;
    }

    /**
     * Detect platform-specific log locations
     */
    public static function detectLogLocations(string $appId, string $platform = 'desktop'): array
    {
        $locations = [];

        // Development logs (always check storage/logs)
        $devLog = getcwd() . '/storage/logs/laravel.log';
        if (file_exists($devLog)) {
            $locations['laravel'] = $devLog;
        }

        if ($platform === 'desktop') {
            $locations = array_merge($locations, self::detectDesktopProductionLogs($appId));
        }

        return $locations;
    }

    /**
     * Detect desktop production log locations
     */
    private static function detectDesktopProductionLogs(string $appId): array
    {
        $locations = [];
        $os = PHP_OS_FAMILY;

        switch ($os) {
            case 'Darwin': // macOS
                $path = getenv('HOME') . "/Library/Application Support/{$appId}/storage/logs/laravel.log";
                if (file_exists($path)) {
                    $locations['native-production'] = $path;
                }
                break;

            case 'Linux':
                $xdgConfig = getenv('XDG_CONFIG_HOME') ?: getenv('HOME') . '/.config';
                $path = "{$xdgConfig}/{$appId}/storage/logs/laravel.log";
                if (file_exists($path)) {
                    $locations['native-production'] = $path;
                }
                break;

            case 'Windows':
                $appData = getenv('APPDATA');
                $path = "{$appData}\\{$appId}\\storage\\logs\\laravel.log";
                if (file_exists($path)) {
                    $locations['native-production'] = $path;
                }
                break;
        }

        return $locations;
    }

    /**
     * Get available log sources
     */
    public function getLogSources(): array
    {
        return $this->logSources;
    }
}
