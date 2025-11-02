<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Exception;
use NativeCLI\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

        $this->addOption(
            'check',
            null,
            InputOption::VALUE_NONE,
            'Check for updates only',
        )->addOption(
            'format',
            null,
            InputOption::VALUE_OPTIONAL,
            'The format to output the update information in',
            'text',
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');

        // Get users home directory
        $home = $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'] ?? null;

        if ($home === null) {
            $output->writeln(
                $format === 'json'
                ? json_encode(['error' => 'Failed to determine home directory'])
                : '<error>Failed to determine home directory</error>'
            );

            return Command::FAILURE;
        }

        $version = trim($input->getArgument('version'));

        if ($version === 'latest') {
            $version = Version::getLatestVersion();

            if ($version === null) {
                $output->writeln($format === 'json'
                    ? json_encode(['error' => 'Failed to retrieve latest version'])
                    : '<error>Failed to retrieve latest version</error>');

                return Command::FAILURE;
            }
        } else {
            $availableVersions = Version::getAvailableVersions();

            if (! $availableVersions->contains($version)) {
                $output->writeln($format === 'json'
                    ? json_encode(['error' => 'Version '.$version.' is not available'])
                    : '<error>Version '.$version.' is not available</error>');

                return Command::FAILURE;
            }

            /** @var SemanticVersion|null $version */
            $version = $availableVersions->first(fn (SemanticVersion $v) => $v->isEqual(SemanticVersion::parse($version)));

            if ($version === null) {
                $output->writeln(
                    $format === 'json'
                        ? json_encode(['error' => 'Failed to retrieve version '.$version])
                        : '<error>Failed to retrieve version '.$version.'</error>'
                );

                return Command::FAILURE;
            }
        }

        if (Version::isCurrentVersion($version)) {
            $output->writeln(
                $format === 'json'
                ? json_encode(['update_available' => false])
                : '<info>Already up to date</info>'
            );

            return Command::SUCCESS;
        }

        if ($input->getOption('check')) {
            $output->writeln(
                $format === 'json'
                ? json_encode(['update_available' => true, 'version' => (string) $version])
                : '<info>Update available: '.$version.'</info>'
            );

            return Command::SUCCESS;
        }

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Are you sure you want to update to version '.$version.'? [Y/n]',
            true
        );

        if (! $questionHelper->ask($input, $output, $question)) {
            $output->writeln(
                $format === 'json'
                ? json_encode(['error' => 'Update cancelled by user'])
                : '<error>Update cancelled by user</error>'
            );

            return Command::FAILURE;
        }

        $output->writeln(
            $format === 'json'
            ? json_encode(['update_available' => true, 'version' => (string) $version])
            : '<info>Updating to version '.$version.'</info>'
        );

        $composer = new Composer(new Filesystem, getcwd());
        $process = new Process([...$composer->findComposer(), 'global', 'require', 'nativecli/nativecli:'.$version]);
        $status = $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($status !== Command::SUCCESS) {
            $output->writeln(
                $format === 'json'
                ? json_encode(['error' => 'Failed to update to version '.$version])
                : '<error>Failed to update to version '.$version.'</error>'
            );

            return $status;
        }

        $output->writeln(
            $format === 'json'
            ? json_encode(['success' => 'Successfully updated to version '.$version])
            : '<info>Successfully updated to version '.$version.'</info>'
        );

        return Command::SUCCESS;
    }
}
