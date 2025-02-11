<?php

namespace NativeCLI;

use Closure;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use z4kn4fein\SemVer\Version as SemanticVersion;

class Composer extends \Illuminate\Support\Composer
{
    public function isComposerFilePresent(): bool
    {
        try {
            $this->findComposerFile();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function getPackageVersions(array $packages, bool $throwOnError = true): array
    {
        $composerLockFile = $this->findComposerLockFile();
        $composerLockData = json_decode(file_get_contents($composerLockFile), true);

        $versions = [];

        foreach ($packages as $package) {
            $found = false;
            foreach (['packages', 'packages-dev'] as $section) {
                foreach ($composerLockData[$section] as $pkg) {
                    if ($pkg['name'] === $package) {
                        $versions[$package] = SemanticVersion::parseOrNull($pkg['version']);
                        $found = true;
                        break 2;
                    }
                }
            }

            if (!$found && $throwOnError) {
                throw new RuntimeException("Package [$package] is not installed.");
            }
        }

        return $versions;
    }

    public function getComposerFile(): string
    {
        return $this->findComposerFile();
    }

    protected function findComposerLockFile(): string
    {
        $composerLockFile = "$this->workingPath/composer.lock";

        if (!file_exists($composerLockFile)) {
            throw new RuntimeException("Unable to locate `composer.lock` file at [$this->workingPath].");
        }

        return $composerLockFile;
    }

    public function packageExistsInComposerFile(string $package): bool
    {
        return $this->hasPackage($package);
    }

    public function requirePackages(array $packages, bool $dev = false, Closure|OutputInterface|null $output = null, $composerBinary = null, bool $tty = false): bool
    {
        $command = (new Collection([
            ...$this->findComposer($composerBinary),
            'require',
            ...$packages,
        ]))
            ->when($dev, function ($command) {
                $command->push('--dev');
            })->all();

        return 0 === $this->getProcess($command, ['COMPOSER_MEMORY_LIMIT' => '-1'])
                ->setTty($tty)
                ->run(
                    $output instanceof OutputInterface
                        ? function ($type, $line) use ($output) {
                            $output->write('    ' . $line);
                        } : $output
                );
    }
}
