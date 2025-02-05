<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use z4kn4fein\SemVer\Version as SemanticVersion;

#[AsCommand(
    name: 'self-update',
    description: 'Update the NativePHP CLI tool'
)]
class SelfUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
            'version',
            InputArgument::OPTIONAL,
            'The version to update to',
            'latest',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get users home directory
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

        if ($home === null) {
            $output->writeln('<error>Failed to determine home directory</error>');

            return Command::FAILURE;
        }

        $version = trim($input->getArgument('version'));

        if ($version === 'latest') {
            $version = Version::getLatestVersion();

            if ($version === null) {
                $output->writeln('<error>Failed to retrieve latest version</error>');

                return Command::FAILURE;
            }
        } else {
            if (!str_contains($version, '-')) {
                // Assumes a release version
                // @TODO Implement release version checking
            }

            $availableVersions = Version::getAvailableVersions();

            if (!$availableVersions->contains($version)) {
                $output->writeln('<error>Version ' . $version . ' is not available</error>');

                return Command::FAILURE;
            }

            /** @var SemanticVersion $version */
            $version = $availableVersions->first(fn(SemanticVersion $v) => $v->isEqual(SemanticVersion::parse($version)));

            if ($version === null) {
                $output->writeln('<error>Failed to retrieve version ' . $version . '</error>');

                return Command::FAILURE;
            }
        }

        if (Version::isCurrentVersion($version)) {
            $output->writeln('<info>Already up to date</info>');

            return Command::SUCCESS;
        }

        $questionHelper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Are you sure you want to update to version ' . $version . '? [Y/n]',
            true
        );

        if (!$questionHelper->ask($input, $output, $question)) {
            $output->writeln('<error>Update cancelled by user</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Updating to version ' . $version . '</info>');

        $composer = new Composer(new Filesystem(), getcwd());
        $process = new Process([...$composer->findComposer(), 'global', 'require', 'petebishwhip/native-cli:' . $version]);
        $status = $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($status !== Command::SUCCESS) {
            $output->writeln('<error>Failed to update to version ' . $version . '</error>');

            return $status;
        }

        $output->writeln('<info>Successfully updated to version ' . $version . '</info>');

        return Command::SUCCESS;
    }
}