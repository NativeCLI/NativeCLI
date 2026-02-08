<?php

namespace NativeCLI\Command;

use NativeCLI\Exception\CommandFailed;
use NativeCLI\Support\ProcessFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'plugin:permissions',
    description: 'Show NativePHP plugin permissions',
)]
class PluginPermissionsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Include unregistered plugins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isLaravelProject()) {
            $output->writeln('<error>This command must be run from a Laravel project directory.</error>');

            return Command::FAILURE;
        }

        try {
            $php = $this->resolvePhpBinary();
            $command = $this->buildPluginListCommand($php, true, (bool) $input->getOption('all'));
            $process = ProcessFactory::make($command);
            $process->mustRun();

            $payload = json_decode($process->getOutput(), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new CommandFailed('Unable to parse JSON output from native:plugin:list.');
            }

            $plugins = $this->normalizePluginPayload($payload);
            $permissions = $this->extractPermissions($plugins);

            if (empty($permissions)) {
                $output->writeln('<info>No plugin permissions reported.</info>');

                return Command::SUCCESS;
            }

            foreach ($permissions as $plugin => $items) {
                $output->writeln("<info>{$plugin}</info>");
                foreach ($items as $permission) {
                    $output->writeln('  - ' . $permission);
                }
            }
        } catch (CommandFailed $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function buildPluginListCommand(string $php, bool $json, bool $all): array
    {
        $command = [$php, 'artisan', 'native:plugin:list'];
        if ($json) {
            $command[] = '--json';
        }
        if ($all) {
            $command[] = '--all';
        }

        return $command;
    }

    protected function normalizePluginPayload(array $payload): array
    {
        if (array_key_exists('plugins', $payload) && is_array($payload['plugins'])) {
            return $payload['plugins'];
        }

        return $payload;
    }

    protected function extractPermissions(array $plugins): array
    {
        $result = [];

        foreach ($plugins as $plugin) {
            if (!is_array($plugin)) {
                continue;
            }

            $name = $plugin['name'] ?? $plugin['package'] ?? null;
            $permissions = $plugin['permissions'] ?? null;

            if (!$name || !is_array($permissions) || $permissions === []) {
                continue;
            }

            $result[$name] = array_values(array_unique(array_filter($permissions)));
        }

        ksort($result);

        return $result;
    }

    private function resolvePhpBinary(): string
    {
        $php = trim(ProcessFactory::shell('which php', false)->mustRun()->getOutput());
        if ($php === '') {
            throw new CommandFailed('Unable to locate PHP binary.');
        }

        return $php;
    }

    private function isLaravelProject(): bool
    {
        return file_exists(getcwd() . '/artisan') && file_exists(getcwd() . '/composer.json');
    }
}
