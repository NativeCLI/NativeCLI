<?php

use NativeCLI\Services\LogAggregator;

beforeEach(function () {
    // Create a temporary directory for test log files
    $this->tempDir = sys_get_temp_dir() . '/nativecli_test_' . uniqid();
    mkdir($this->tempDir);
});

afterEach(function () {
    // Clean up temporary directory
    if (isset($this->tempDir) && file_exists($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*'));
        rmdir($this->tempDir);
    }
});

test('can add log source', function () {
    $logFile = $this->tempDir . '/test.log';
    file_put_contents($logFile, '[2025-01-15 10:00:00] local.INFO: Test message');

    $aggregator = new LogAggregator();
    $result = $aggregator->addLogSource('test', $logFile);

    expect($result)->toBeInstanceOf(LogAggregator::class);
    expect($aggregator->getLogSources())->toHaveKey('test');
});

test('ignores non-existent log source', function () {
    $aggregator = new LogAggregator();
    $result = $aggregator->addLogSource('missing', '/non/existent/file.log');

    expect($result)->toBeInstanceOf(LogAggregator::class);
    expect($aggregator->getLogSources())->not->toHaveKey('missing');
});

test('can parse Laravel log format', function () {
    $logFile = $this->tempDir . '/laravel.log';
    $logContent = <<<LOG
[2025-01-15 10:00:00] local.INFO: User logged in
[2025-01-15 10:01:00] local.ERROR: Database connection failed
[2025-01-15 10:02:00] local.DEBUG: Query executed
LOG;
    file_put_contents($logFile, $logContent);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('laravel', $logFile);

    $logs = $aggregator->getLogs(10);

    expect($logs)->toHaveCount(3);
    expect($logs[0]['level'])->toBe('debug');
    expect($logs[1]['level'])->toBe('error');
    expect($logs[2]['level'])->toBe('info');
});

test('can filter logs by level', function () {
    $logFile = $this->tempDir . '/test.log';
    $logContent = <<<LOG
[2025-01-15 10:00:00] local.INFO: Info message
[2025-01-15 10:01:00] local.ERROR: Error message
[2025-01-15 10:02:00] local.DEBUG: Debug message
LOG;
    file_put_contents($logFile, $logContent);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);
    $aggregator->filterByLevel('error');

    $logs = $aggregator->getLogs(10);

    expect($logs)->toHaveCount(1);
    expect($logs[0]['level'])->toBe('error');
    expect($logs[0]['message'])->toContain('Error message');
});

test('can filter logs by source', function () {
    $logFile1 = $this->tempDir . '/laravel.log';
    $logFile2 = $this->tempDir . '/native.log';

    file_put_contents($logFile1, '[2025-01-15 10:00:00] local.INFO: Laravel message');
    file_put_contents($logFile2, '[2025-01-15 10:01:00] local.INFO: Native message');

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('laravel', $logFile1);
    $aggregator->addLogSource('native', $logFile2);
    $aggregator->filterBySource('laravel');

    $logs = $aggregator->getLogs(10);

    expect($logs)->toHaveCount(1);
    expect($logs[0]['source'])->toBe('laravel');
    expect($logs[0]['message'])->toContain('Laravel message');
});

test('can filter logs by date range', function () {
    $logFile = $this->tempDir . '/test.log';
    $logContent = <<<LOG
[2025-01-10 10:00:00] local.INFO: Old message
[2025-01-15 10:00:00] local.INFO: Middle message
[2025-01-20 10:00:00] local.INFO: Recent message
LOG;
    file_put_contents($logFile, $logContent);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);
    $aggregator->filterByDate('2025-01-12', '2025-01-18');

    $logs = $aggregator->getLogs(10);

    expect($logs)->toHaveCount(1);
    expect($logs[0]['message'])->toContain('Middle message');
});

test('sorts logs by timestamp descending', function () {
    $logFile = $this->tempDir . '/test.log';
    $logContent = <<<LOG
[2025-01-15 10:00:00] local.INFO: First message
[2025-01-15 10:02:00] local.INFO: Third message
[2025-01-15 10:01:00] local.INFO: Second message
LOG;
    file_put_contents($logFile, $logContent);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);

    $logs = $aggregator->getLogs(10);

    expect($logs)->toHaveCount(3);
    expect($logs[0]['message'])->toContain('Third message');
    expect($logs[1]['message'])->toContain('Second message');
    expect($logs[2]['message'])->toContain('First message');
});

test('can limit number of log entries', function () {
    $logFile = $this->tempDir . '/test.log';
    $logContent = implode("\n", array_map(
        fn ($i) => "[2025-01-15 10:0{$i}:00] local.INFO: Message {$i}",
        range(0, 9)
    ));
    file_put_contents($logFile, $logContent);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);

    $logs = $aggregator->getLogs(5);

    expect($logs)->toHaveCount(5);
});

test('tail returns most recent logs', function () {
    $logFile = $this->tempDir . '/test.log';
    $logContent = implode("\n", array_map(
        fn ($i) => "[2025-01-15 10:0{$i}:00] local.INFO: Message {$i}",
        range(0, 9)
    ));
    file_put_contents($logFile, $logContent);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);

    $logs = $aggregator->tail(3);

    expect($logs)->toHaveCount(3);
    expect($logs[0]['message'])->toContain('Message 9');
});

test('handles empty log files', function () {
    $logFile = $this->tempDir . '/empty.log';
    touch($logFile);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);

    $logs = $aggregator->getLogs(10);

    expect($logs)->toHaveCount(0);
});

test('handles malformed log lines gracefully', function () {
    $logFile = $this->tempDir . '/malformed.log';
    $logContent = <<<LOG
[2025-01-15 10:00:00] local.INFO: Valid message
This is not a valid log line
[2025-01-15 10:01:00] local.ERROR: Another valid message
Random text without format
LOG;
    file_put_contents($logFile, $logContent);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);

    $logs = $aggregator->getLogs(10);

    // Should still parse valid lines and treat malformed lines as info
    expect($logs)->toHaveCount(4);
});

test('detects development log location', function () {
    // Create a mock Laravel project structure
    $projectDir = $this->tempDir . '/project';
    mkdir($projectDir);
    mkdir($projectDir . '/storage', 0755, true);
    mkdir($projectDir . '/storage/logs', 0755, true);
    $logFile = $projectDir . '/storage/logs/laravel.log';
    touch($logFile);

    // Change to project directory
    $originalDir = getcwd();
    chdir($projectDir);

    $locations = LogAggregator::detectLogLocations('com.example.app', 'desktop');

    // Restore directory
    chdir($originalDir);

    expect($locations)->toHaveKey('laravel');
    expect($locations['laravel'])->toEndWith('/storage/logs/laravel.log');
});

test('handles unreadable log files', function () {
    $logFile = $this->tempDir . '/test.log';
    file_put_contents($logFile, '[2025-01-15 10:00:00] local.INFO: Test message');
    chmod($logFile, 0000); // Make unreadable

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);

    $logs = $aggregator->getLogs(10);

    // Should return empty array instead of throwing exception
    expect($logs)->toHaveCount(0);

    // Cleanup: restore permissions
    chmod($logFile, 0644);
});

test('merges logs from multiple sources chronologically', function () {
    $logFile1 = $this->tempDir . '/source1.log';
    $logFile2 = $this->tempDir . '/source2.log';

    file_put_contents($logFile1, "[2025-01-15 10:00:00] local.INFO: From source 1\n[2025-01-15 10:02:00] local.INFO: From source 1 again");
    file_put_contents($logFile2, "[2025-01-15 10:01:00] local.INFO: From source 2");

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('source1', $logFile1);
    $aggregator->addLogSource('source2', $logFile2);

    $logs = $aggregator->getLogs(10);

    expect($logs)->toHaveCount(3);
    expect($logs[0]['message'])->toContain('source 1 again'); // Most recent
    expect($logs[1]['message'])->toContain('source 2');
    expect($logs[2]['message'])->toContain('From source 1'); // Oldest
});

test('handles various Laravel log timestamp formats', function () {
    $logFile = $this->tempDir . '/test.log';
    $logContent = <<<LOG
[2025-01-15 10:00:00] local.INFO: Format 1
[2025-01-15T10:01:00.123456+00:00] local.ERROR: Format 2
[2025-01-15 10:02:00.654321-05:00] local.DEBUG: Format 3
LOG;
    file_put_contents($logFile, $logContent);

    $aggregator = new LogAggregator();
    $aggregator->addLogSource('test', $logFile);

    $logs = $aggregator->getLogs(10);

    expect($logs)->toHaveCount(3);
    foreach ($logs as $log) {
        expect($log['timestamp'])->toBeInstanceOf(DateTime::class);
    }
});
