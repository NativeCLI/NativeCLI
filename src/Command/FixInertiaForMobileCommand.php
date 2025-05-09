<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use NativeCLI\Composer;
use NativeCLI\Exception\CommandFailed;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use Throwable;

#[AsCommand(
    name: 'inertia:fix',
    description: 'Provides a quick and easy fix to allow Inertia to work in NativePHP, pending a PR release.'
)]
class FixInertiaForMobileCommand extends Command
{
    protected function configure(): void
    {
        //
    }

    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Checking for updates...</info>', $this->getOutputVerbosityLevel($input));

        $packageJsonPath = getcwd() . '/package.json';

        if (!file_exists($packageJsonPath)) {
            $output->writeln('<error>package.json not found in the current directory.</error>');
            return Command::FAILURE;
        }

        $packageJson = json_decode(file_get_contents($packageJsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln('<error>Failed to parse package.json.</error>');
            return Command::FAILURE;
        }

        $packagesToCheck = [
            '@inertiajs/vue3',
            '@inertiajs/react',
            '@inertiajs/svelte',
        ];

        $foundPackages = array_filter($packagesToCheck, fn ($pkg) => isset($packageJson['dependencies'][$pkg]));

        if (empty($foundPackages)) {
            $output->writeln('<info>No Inertia.js packages found in package.json.</info>');
            return Command::SUCCESS;
        }

        foreach ($foundPackages as $package) {
            $output->writeln("<info>Found $package. Reinstalling from GitHub...</info>");

            try {
                $this->runCommand(['npm', 'uninstall', $package], $output);
                $this->runCommand(['npm', 'install', "$package@mpociot/inertia#patch-1"], $output);
            } catch (Throwable $e) {
                $output->writeln("<error>Failed to reinstall $package: {$e->getMessage()}</error>");
                return Command::FAILURE;
            }
        }

        $output->writeln('<info>All packages have been updated successfully.</info>');
        return Command::SUCCESS;
    }

    private function runCommand(array $command, OutputInterface $output): void
    {
        $process = new Process($command);
        $process->setTty(Process::isTtySupported());
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Command failed: ' . implode(' ', $command));
        }
    }

    protected function getOutputVerbosityLevel(InputInterface $input): int
    {
        return $input->getOption('no-interaction')
            ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL;
    }
}
