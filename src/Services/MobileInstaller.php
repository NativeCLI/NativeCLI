<?php

namespace NativeCLI\Services;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Exception\CommandFailed;
use NativeCLI\Support\ProcessFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class MobileInstaller
{
    /**
     * @throws CommandFailed
     */
    public function install(OutputInterface $output, string $workingPath, bool $runNativeInstall = true): void
    {
        $output->writeln('Installing NativePHP for Mobile package...');

        $composer = new Composer(new Filesystem(), $workingPath);
        $composerInstallSuccessful = $composer->requirePackages(
            packages: ['nativephp/mobile'],
            output: $output,
            tty: Process::isTtySupported()
        );

        if (!$composerInstallSuccessful) {
            throw new CommandFailed('Failed to install NativePHP for iOS.');
        }

        if (!$runNativeInstall) {
            return;
        }

        $output->writeln('Installing NativePHP for Mobile...');

        $php = trim(ProcessFactory::shell('which php', false)->mustRun()->getOutput());

        ProcessFactory::make([$php, 'artisan', 'native:install', '--no-interaction'], false, $workingPath)
            ->mustRun(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

        $output->writeln('<info>NativePHP for iOS installed successfully.</info>');
    }
}
