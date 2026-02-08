<?php

namespace NativeCLI\Command;

use NativeCLI\Exception\CommandFailed;
use NativeCLI\Support\ProcessFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'plugin:provider',
    description: 'Publish the NativePHP plugin service provider',
)]
class PluginProviderCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isLaravelProject()) {
            $output->writeln('<error>This command must be run from a Laravel project directory.</error>');

            return Command::FAILURE;
        }

        try {
            $php = $this->resolvePhpBinary();
            ProcessFactory::make([$php, 'artisan', 'vendor:publish', '--tag=nativephp-plugins-provider'])
                ->mustRun(function ($type, $buffer) use ($output) {
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
