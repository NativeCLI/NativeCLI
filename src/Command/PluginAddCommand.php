<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Exception\CommandFailed;
use NativeCLI\Support\ProcessFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;

use function Laravel\Prompts\confirm;

#[AsCommand(
    name: 'plugin:add',
    description: 'Install and register a NativePHP plugin',
)]
class PluginAddCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::REQUIRED, 'Composer package name for the plugin')
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Install as a dev dependency')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force plugin registration if already registered')
            ->addOption('rebuild', null, InputOption::VALUE_NONE, 'Rebuild the native app after registering');
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

            $this->ensureNativeServiceProvider($php, $output);

            $output->writeln("<info>Installing {$package}...</info>");
            $composer = new Composer(new Filesystem(), getcwd());
            $installed = $composer->requirePackages(
                packages: [$package],
                dev: (bool) $input->getOption('dev'),
                output: $output,
                tty: Process::isTtySupported()
            );

            if (!$installed) {
                throw new CommandFailed('Composer install failed.');
            }

            $output->writeln("<info>Registering {$package}...</info>");
            $registerCommand = $this->buildPluginRegisterCommand($php, $package, (bool) $input->getOption('force'));
            ProcessFactory::make($registerCommand)->mustRun(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            $output->writeln('<info>Installed plugins:</info>');
            ProcessFactory::make($this->buildPluginListCommand($php, false, false))
                ->mustRun(function ($type, $buffer) use ($output) {
                    $output->write($buffer);
                });

            if ($this->shouldRebuild($input)) {
                $output->writeln('<info>Rebuilding NativePHP application...</info>');
                ProcessFactory::make([$php, 'artisan', 'native:run'])
                    ->mustRun(function ($type, $buffer) use ($output) {
                        $output->write($buffer);
                    });
            } else {
                $output->writeln('<comment>Rebuild required after plugin changes. Run `nativecli build` when ready.</comment>');
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

    protected function buildPluginRegisterCommand(string $php, string $package, bool $force): array
    {
        $command = [$php, 'artisan', 'native:plugin:register', $package];
        if ($force) {
            $command[] = '--force';
        }

        return $command;
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

    private function shouldRebuild(InputInterface $input): bool
    {
        if ($input->getOption('rebuild')) {
            return true;
        }

        if (!$input->isInteractive()) {
            return false;
        }

        return confirm('Rebuild NativePHP now?', false);
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
