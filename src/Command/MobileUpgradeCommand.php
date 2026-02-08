<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Exception\CommandFailed;
use NativeCLI\Support\ProcessFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function Laravel\Prompts\confirm;

#[AsCommand(
    name: 'mobile:upgrade',
    description: 'Upgrade a NativePHP Mobile project to the latest structure',
)]
class MobileUpgradeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('skip-install', null, InputOption::VALUE_NONE, 'Skip running native:install')
            ->addOption('run', null, InputOption::VALUE_NONE, 'Run native:run after upgrade')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force native:install if supported');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isLaravelProject()) {
            $output->writeln('<error>This command must be run from a Laravel project directory.</error>');

            return Command::FAILURE;
        }

        try {
            $composer = new Composer(new Filesystem(), getcwd());
            if (!$composer->packageExistsInComposerFile('nativephp/mobile')) {
                $output->writeln('<error>nativephp/mobile is not installed in this project.</error>');

                return Command::FAILURE;
            }

            $php = $this->resolvePhpBinary();

            $this->ensureNativeServiceProvider($php, $output);

            $output->writeln('<info>Checking installed plugins...</info>');
            $this->runPluginList($php, $output);

            if (!$input->getOption('skip-install')) {
                $output->writeln('<info>Running NativePHP install...</info>');
                $installCommand = $this->buildInstallCommand($php, (bool) $input->getOption('force'));
                ProcessFactory::make($installCommand)
                    ->mustRun(function ($type, $buffer) use ($output) {
                        $output->write($buffer);
                    });
            }

            if ($this->shouldRun($input)) {
                $output->writeln('<info>Launching NativePHP...</info>');
                ProcessFactory::make([$php, 'artisan', 'native:run'])
                    ->mustRun(function ($type, $buffer) use ($output) {
                        $output->write($buffer);
                    });
            } else {
                $output->writeln('<comment>Run `nativecli build` when you are ready to rebuild.</comment>');
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

    protected function buildInstallCommand(string $php, bool $force): array
    {
        $command = [$php, 'artisan', 'native:install', '--no-interaction'];
        if ($force) {
            $command[] = '--force';
        }

        return $command;
    }

    protected function buildPluginListCommand(string $php): array
    {
        return [$php, 'artisan', 'native:plugin:list', '--all'];
    }

    private function runPluginList(string $php, OutputInterface $output): void
    {
        try {
            ProcessFactory::make($this->buildPluginListCommand($php))
                ->mustRun(function ($type, $buffer) use ($output) {
                    $output->write($buffer);
                });
        } catch (Throwable) {
            $output->writeln('<comment>Unable to list plugins. Continue with upgrade.</comment>');
        }
    }

    private function shouldRun(InputInterface $input): bool
    {
        if ($input->getOption('run')) {
            return true;
        }

        if (!$input->isInteractive()) {
            return false;
        }

        return confirm('Start NativePHP now?', false);
    }

    private function ensureNativeServiceProvider(string $php, OutputInterface $output): void
    {
        $providerPath = getcwd() . '/app/Providers/NativeServiceProvider.php';
        if (file_exists($providerPath)) {
            return;
        }

        $output->writeln('<info>Publishing NativePHP plugin provider...</info>');
        ProcessFactory::make([$php, 'artisan', 'vendor:publish', '--tag=nativephp-plugins-provider'])
            ->mustRun(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
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
