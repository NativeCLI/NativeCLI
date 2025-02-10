<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Exception\CommandFailed;
use NativeCLI\NativePHP;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use z4kn4fein\SemVer\Version as SemanticVersion;

#[AsCommand(
    name: 'check-update',
    description: 'Check for updates to NativePHP'
)]
class CheckNativePHPUpdatesCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'format',
            'f',
            InputOption::VALUE_OPTIONAL,
            'The output format: text or json',
            'text'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $returnJson = $input->getOption('format') === 'json';

        $composer = new Composer(new Filesystem(), getcwd());

        if ($composer->packageExistsInComposerFile('nativephp/ios')) {
            $output->writeln(
                $returnJson
                    ? json_encode(['error' => 'This command is not available for NativePHP for iOS yet.'])
                    : '<error>This command is not available for NativePHP for iOS yet.</error>'
            );

            return Command::FAILURE;
        }

        try {
            // Throws if composer.json is not found.
            if (!$composer->isComposerFilePresent()) {
                throw new CommandFailed('composer.json not found in the current directory.');
            }

            $output->writeln('Checking for updates to NativePHP...', OutputInterface::VERBOSITY_VERBOSE);

            $currentVersions = $composer->getPackageVersions(['nativephp/electron', 'nativephp/laravel'], false);
            $latestVersions = NativePHP::getLatestVersions();
            $result = [];

            /**
             * @var string $package
             * @var SemanticVersion $latestVersion
             */
            foreach ($latestVersions as $package => $latestVersion) {
                /** @var SemanticVersion|null $currentVersion */
                $currentVersion = $currentVersions[$package] ?? null;

                if ($currentVersion === null) {
                    $output->writeln(
                        $returnJson
                            ? json_encode(['error' => "Package [$package] is not installed."])
                            : "<error>Package [$package] is not installed.</error>"
                    );
                    continue;
                }

                $isOutdated = $latestVersion->isGreaterThan($currentVersion);

                if ($returnJson) {
                    $result[$package] = [
                        'current' => (string) $currentVersion,
                        'latest' => (string) $latestVersion,
                        'isOutdated' => $isOutdated,
                    ];
                } else {
                    $output->writeln(
                        $isOutdated
                            ? "<info>Package [$package] is outdated. Current: $currentVersion, Latest: $latestVersion</info>"
                            : "<info>Package [$package] is up to date.</info>"
                    );
                }
            }

            if ($returnJson) {
                if (!empty($result)) {
                    $output->writeln(json_encode($result));
                } else {
                    $output->writeln(json_encode(['error' => 'Could not check packages.']));
                }
            }
        } catch (Throwable $e) {
            $output->writeln(
                $returnJson
                    ? json_encode(['error' => $e->getMessage()])
                    : "<error>{$e->getMessage()}</error>"
            );

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
