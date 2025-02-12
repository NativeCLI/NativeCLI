<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use JsonException;
use NativeCLI\Composer;
use NativeCLI\Services\RepositoryManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'ios:install',
    description: 'Install the NativePHP Mobile CLI tool'
)]
class InstallNativePHPMobileCommand extends Command
{
    /**
     * @throws JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Retrieving NativePHP for iOS Repo...');

        $composer = new Composer(new Filesystem(), getcwd());
        $repoMan = new RepositoryManager($composer);
        $repoMan->addRepository('composer', 'https://nativephp-ios.composer.sh');
        $composerInstallSuccessful = $composer->requirePackages(
            packages: ['nativephp/ios'],
            output: $output,
            tty: Process::isTtySupported()
        );

        if (!$composerInstallSuccessful) {
            $output->writeln('<error>Failed to install NativePHP for iOS.</error>');

            return Command::FAILURE;
        }

        $php = trim(Process::fromShellCommandline('which php')->mustRun()->getOutput());

        $output->writeln('Installing NativePHP for iOS');

        $nativePhpInstall = new Process([$php, 'artisan', 'native:install', '--no-interaction']);
        $nativePhpInstall->setTty(Process::isTtySupported())
            ->mustRun(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

        if ($nativePhpInstall->getExitCode() === Command::SUCCESS) {
            $output->writeln('<info>NativePHP for iOS installed successfully.</info>');

            return Command::SUCCESS;
        }

        $output->writeln('<error>Failed to install NativePHP for iOS.</error>');

        return Command::FAILURE;
    }
}
