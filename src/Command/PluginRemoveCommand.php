<?php

namespace NativeCLI\Command;

use NativeCLI\Exception\CommandFailed;
use NativeCLI\Support\ProcessFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'plugin:remove',
    description: 'Uninstall a NativePHP plugin',
)]
class PluginRemoveCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::REQUIRED, 'Composer package name for the plugin')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force uninstall even if plugin has unmet requirements')
            ->addOption('keep-files', null, InputOption::VALUE_NONE, 'Keep plugin files on disk');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isLaravelProject()) {
            $output->writeln('<error>This command must be run from a Laravel project directory.</error>');

            return Command::FAILURE;
        }

        $package = (string) $input->getArgument('package');

        try {
            $php = $this->resolvePhpBinary();
            $command = $this->buildPluginUninstallCommand(
                $php,
                $package,
                (bool) $input->getOption('force'),
                (bool) $input->getOption('keep-files'),
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

    protected function buildPluginUninstallCommand(string $php, string $package, bool $force, bool $keepFiles): array
    {
        $command = [$php, 'artisan', 'native:plugin:uninstall', $package];
        if ($force) {
            $command[] = '--force';
        }
        if ($keepFiles) {
            $command[] = '--keep-files';
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
