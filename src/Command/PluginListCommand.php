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
    name: 'plugin:list',
    description: 'List registered NativePHP plugins',
)]
class PluginListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Include unregistered plugins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isLaravelProject()) {
            $output->writeln('<error>This command must be run from a Laravel project directory.</error>');

            return Command::FAILURE;
        }

        try {
            $php = $this->resolvePhpBinary();
            $command = $this->buildPluginListCommand(
                $php,
                (bool) $input->getOption('json'),
                (bool) $input->getOption('all'),
            );

            ProcessFactory::make($command)->mustRun(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
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
