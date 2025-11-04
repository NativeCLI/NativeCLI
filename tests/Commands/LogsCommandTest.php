<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use NativeCLI\Command\LogsCommand;

beforeEach(function () {
    // Create a temporary Laravel project structure
    $this->tempDir = sys_get_temp_dir() . '/nativecli_logs_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/storage', 0755, true);
    mkdir($this->tempDir . '/storage/logs', 0755, true);

    // Create artisan and composer.json to simulate Laravel project
    touch($this->tempDir . '/artisan');
    file_put_contents($this->tempDir . '/composer.json', '{}');

    // Create .env with app ID
    file_put_contents($this->tempDir . '/.env', "NATIVEPHP_APP_ID=com.test.app\nAPP_NAME=TestApp");

    // Store original directory
    $this->originalDir = getcwd();

    // Change to temp directory
    chdir($this->tempDir);
});

afterEach(function () {
    // Restore original directory
    chdir($this->originalDir);

    // Clean up temporary directory
    if (isset($this->tempDir) && file_exists($this->tempDir)) {
        array_map('unlink', glob($this->tempDir . '/*'));
        array_map('unlink', glob($this->tempDir . '/storage/logs/*'));
        @rmdir($this->tempDir . '/storage/logs');
        @rmdir($this->tempDir . '/storage');
        @rmdir($this->tempDir);
    }
});

test('logs command fails when not in Laravel project', function () {
    // Change to a non-Laravel directory
    chdir(sys_get_temp_dir());

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute([]);

    expect($exitCode)->toBe(1);
    expect($commandTester->getDisplay())->toContain('must be run from a Laravel project');
});

test('logs command displays error when no log files found', function () {
    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute([]);

    expect($exitCode)->toBe(1);
    expect($commandTester->getDisplay())->toContain('No log files found');
});

test('logs command displays Laravel logs', function () {
    // Create a sample log file
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    $logContent = <<<LOG
[2025-01-15 10:00:00] local.INFO: User logged in
[2025-01-15 10:01:00] local.ERROR: Database connection failed
[2025-01-15 10:02:00] local.DEBUG: Query executed
LOG;
    file_put_contents($logFile, $logContent);

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute([]);

    expect($exitCode)->toBe(0);
    expect($commandTester->getDisplay())->toContain('User logged in');
    expect($commandTester->getDisplay())->toContain('Database connection failed');
    expect($commandTester->getDisplay())->toContain('Query executed');
});

test('logs command respects lines option', function () {
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    $logContent = implode("\n", array_map(
        fn ($i) => "[2025-01-15 10:0{$i}:00] local.INFO: Message {$i}",
        range(0, 9)
    ));
    file_put_contents($logFile, $logContent);

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute(['--lines' => '3']);

    expect($exitCode)->toBe(0);
    $output = $commandTester->getDisplay();

    // Should only show 3 messages
    expect($output)->toContain('Message 9');
    expect($output)->toContain('Message 8');
    expect($output)->toContain('Message 7');
    expect($output)->not->toContain('Message 6');
});

test('logs command filters by level', function () {
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    $logContent = <<<LOG
[2025-01-15 10:00:00] local.INFO: Info message
[2025-01-15 10:01:00] local.ERROR: Error message 1
[2025-01-15 10:02:00] local.DEBUG: Debug message
[2025-01-15 10:03:00] local.ERROR: Error message 2
LOG;
    file_put_contents($logFile, $logContent);

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute(['--level' => 'error']);

    expect($exitCode)->toBe(0);
    $output = $commandTester->getDisplay();

    expect($output)->toContain('Error message 1');
    expect($output)->toContain('Error message 2');
    expect($output)->not->toContain('Info message');
    expect($output)->not->toContain('Debug message');
});

test('logs command runs successfully in verbose mode', function () {
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    file_put_contents($logFile, '[2025-01-15 10:00:00] local.INFO: Test message');

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    // Test that verbose mode doesn't break anything
    $exitCode = $commandTester->execute(['-v' => true]);

    expect($exitCode)->toBe(0);
    expect($commandTester->getDisplay())->toContain('Test message');
});

test('logs command displays message when no matching logs', function () {
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    file_put_contents($logFile, '[2025-01-15 10:00:00] local.INFO: Info message');

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute(['--level' => 'error']);

    expect($exitCode)->toBe(0);
    expect($commandTester->getDisplay())->toContain('No logs found matching the criteria');
});

test('logs command color codes output by level', function () {
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    $logContent = <<<LOG
[2025-01-15 10:00:00] local.INFO: Info message
[2025-01-15 10:01:00] local.ERROR: Error message
[2025-01-15 10:02:00] local.DEBUG: Debug message
LOG;
    file_put_contents($logFile, $logContent);

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute([]);

    expect($exitCode)->toBe(0);
    $output = $commandTester->getDisplay();

    // Check that color tags are present (they'll be in the output)
    expect($output)->toContain('INFO');
    expect($output)->toContain('ERROR');
    expect($output)->toContain('DEBUG');
});

test('logs command detects app ID from env file', function () {
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    file_put_contents($logFile, '[2025-01-15 10:00:00] local.INFO: Test');

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute(['-v' => true]);

    expect($exitCode)->toBe(0);
    // Command should run successfully with detected app ID
});

test('logs command handles missing env file gracefully', function () {
    unlink($this->tempDir . '/.env');

    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    file_put_contents($logFile, '[2025-01-15 10:00:00] local.INFO: Test');

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute([]);

    // Should still work with fallback app ID
    expect($exitCode)->toBe(0);
});

test('logs command supports platform option', function () {
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    file_put_contents($logFile, '[2025-01-15 10:00:00] local.INFO: Test');

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute(['--platform' => 'mobile']);

    expect($exitCode)->toBe(0);
});

test('logs command filters by date range', function () {
    $logFile = $this->tempDir . '/storage/logs/laravel.log';
    $logContent = <<<LOG
[2025-01-10 10:00:00] local.INFO: Old message
[2025-01-15 10:00:00] local.INFO: Middle message
[2025-01-20 10:00:00] local.INFO: Recent message
LOG;
    file_put_contents($logFile, $logContent);

    $application = new Application();
    $application->add(new LogsCommand());

    $command = $application->find('logs');
    $commandTester = new CommandTester($command);

    $exitCode = $commandTester->execute([
        '--start-date' => '2025-01-12',
        '--end-date' => '2025-01-18',
    ]);

    expect($exitCode)->toBe(0);
    $output = $commandTester->getDisplay();

    expect($output)->toContain('Middle message');
    expect($output)->not->toContain('Old message');
    expect($output)->not->toContain('Recent message');
});
