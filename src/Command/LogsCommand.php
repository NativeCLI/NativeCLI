<?php

namespace NativeCLI\Command;

use NativeCLI\Services\LogAggregator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'logs',
    description: 'Display logs from Laravel and native layers'
)]
class LogsCommand extends Command
{
    private const LEVEL_COLORS = [
        'debug' => 'white',
        'info' => 'green',
        'notice' => 'cyan',
        'warning' => 'yellow',
        'error' => 'red',
        'critical' => 'red',
        'alert' => 'red',
        'emergency' => 'red',
    ];

    protected function configure(): void
    {
        $this
            ->addOption(
                'follow',
                'f',
                InputOption::VALUE_NONE,
                'Follow log output (tail -f style)'
            )
            ->addOption(
                'lines',
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of lines to display',
                '50'
            )
            ->addOption(
                'level',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Filter by log level (debug, info, warning, error, etc.)',
                null
            )
            ->addOption(
                'source',
                's',
                InputOption::VALUE_OPTIONAL,
                'Filter by log source (laravel, native, all)',
                'all'
            )
            ->addOption(
                'platform',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Platform type (desktop, mobile)',
                'desktop'
            )
            ->addOption(
                'start-date',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter logs from this date (Y-m-d H:i:s)',
                null
            )
            ->addOption(
                'end-date',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter logs until this date (Y-m-d H:i:s)',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if we're in a Laravel project
        if (!$this->isLaravelProject()) {
            $output->writeln('<error>This command must be run from a Laravel project directory.</error>');

            return Command::FAILURE;
        }

        $lines = (int) $input->getOption('lines');
        $level = $input->getOption('level');
        $source = $input->getOption('source');
        $platform = $input->getOption('platform');
        $follow = $input->getOption('follow');
        $startDate = $input->getOption('start-date');
        $endDate = $input->getOption('end-date');

        // Detect app ID for production logs
        $appId = $this->detectAppId();

        // Initialize log aggregator
        $aggregator = new LogAggregator();

        // Detect and add log sources
        $logLocations = LogAggregator::detectLogLocations($appId, $platform);

        if (empty($logLocations)) {
            $output->writeln('<error>No log files found.</error>');
            $output->writeln('<info>Searched locations:</info>');
            $output->writeln('  - ' . getcwd() . '/storage/logs/laravel.log');

            if ($platform === 'desktop') {
                $this->displayDesktopLogLocations($output, $appId);
            }

            return Command::FAILURE;
        }

        foreach ($logLocations as $name => $path) {
            $aggregator->addLogSource($name, $path);
        }

        // Apply filters
        if ($level) {
            $aggregator->filterByLevel($level);
        }

        if ($source) {
            $aggregator->filterBySource($source);
        }

        if ($startDate || $endDate) {
            $aggregator->filterByDate($startDate, $endDate);
        }

        // Display available sources
        if ($output->isVerbose()) {
            $output->writeln('<info>Log sources:</info>');
            foreach ($logLocations as $name => $path) {
                $output->writeln("  - {$name}: {$path}");
            }
            $output->writeln('');
        }

        // Follow mode (real-time)
        if ($follow) {
            $output->writeln('<info>Following logs... (Press Ctrl+C to stop)</info>');
            $output->writeln('');

            try {
                $aggregator->follow(function ($logEntry) use ($output) {
                    $this->formatLogEntry($output, $logEntry);
                });
            } catch (\Exception $e) {
                $output->writeln("<error>Error following logs: {$e->getMessage()}</error>");

                return Command::FAILURE;
            }
        }

        // Standard mode (display logs)
        $logs = $aggregator->tail($lines);

        if (empty($logs)) {
            $output->writeln('<info>No logs found matching the criteria.</info>');

            return Command::SUCCESS;
        }

        foreach ($logs as $logEntry) {
            $this->formatLogEntry($output, $logEntry);
        }

        $output->writeln('');
        $output->writeln("<info>Displayed {$lines} most recent log entries</info>");

        return Command::SUCCESS;
    }

    /**
     * Format and output a log entry
     */
    private function formatLogEntry(OutputInterface $output, array $logEntry): void
    {
        $level = $logEntry['level'];
        $color = self::LEVEL_COLORS[$level] ?? 'white';
        $timestamp = $logEntry['timestamp']->format('Y-m-d H:i:s');
        $source = $logEntry['source'];
        $message = $logEntry['message'];

        $levelFormatted = str_pad(strtoupper($level), 9);

        $output->writeln(sprintf(
            '<fg=%s>[%s] %s</> <fg=cyan>[%s]</> %s',
            $color,
            $timestamp,
            $levelFormatted,
            $source,
            $message
        ));
    }

    /**
     * Check if current directory is a Laravel project
     */
    private function isLaravelProject(): bool
    {
        return file_exists(getcwd() . '/artisan') && file_exists(getcwd() . '/composer.json');
    }

    /**
     * Detect app ID from config
     */
    private function detectAppId(): string
    {
        // Try to read from .env
        $envPath = getcwd() . '/.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (preg_match('/NATIVEPHP_APP_ID=([^\s]+)/', $envContent, $matches)) {
                return trim($matches[1], '"\'');
            }
        }

        // Try to read from config
        $configPath = getcwd() . '/config/nativephp.php';
        if (file_exists($configPath)) {
            $configContent = file_get_contents($configPath);
            if (preg_match("/'app_id'\s*=>\s*(?:env\('NATIVEPHP_APP_ID'(?:,\s*)?['\"]([^'\"]+)['\"]\))|['\"]([^'\"]+)['\"]/", $configContent, $matches)) {
                return $matches[1] ?? $matches[2] ?? 'unknown';
            }
        }

        // Fallback to APP_NAME
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (preg_match('/APP_NAME=([^\s]+)/', $envContent, $matches)) {
                return trim($matches[1], '"\'');
            }
        }

        return 'unknown';
    }

    /**
     * Display desktop log locations for debugging
     */
    private function displayDesktopLogLocations(OutputInterface $output, string $appId): void
    {
        $os = PHP_OS_FAMILY;

        $output->writeln('  <info>Desktop production log locations (by OS):</info>');

        switch ($os) {
            case 'Darwin':
                $output->writeln("  - macOS: ~/Library/Application Support/{$appId}/storage/logs/laravel.log");
                break;
            case 'Linux':
                $output->writeln("  - Linux: ~/.config/{$appId}/storage/logs/laravel.log");
                break;
            case 'Windows':
                $output->writeln("  - Windows: %APPDATA%\\{$appId}\\storage\\logs\\laravel.log");
                break;
        }
    }
}
