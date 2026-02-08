<?php

namespace NativeCLI;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use NativeCLI\Support\ProcessFactory;
use Throwable;
use z4kn4fein\SemVer\Version as SemanticVersion;

class Composer extends \Illuminate\Support\Composer
{
    public function findGlobalComposerHomeDirectory(): string
    {
        $globalDirectory = null;
        $process = ProcessFactory::make(['composer', '-n', 'config', '--global', 'home']);
        // Get response from process to variable
        $process->run(function ($type, $line) use (&$globalDirectory) {
            if ($type === Process::ERR) {
                return;
            }

            $globalDirectory = trim($line);
        });

        if ($globalDirectory === null) {
            throw new RuntimeException('Unable to determine global composer home directory.');
        }

        return rtrim($globalDirectory, "\n");
    }

    public function findGlobalComposerFile(string $file = 'composer.json'): ?string
    {
        $filePath = "{$this->findGlobalComposerHomeDirectory()}/$file";

        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Global composer file not found at [$filePath].");
        }

        return $filePath;
    }

    public function isComposerFilePresent(): bool
    {
        try {
            $this->findComposerFile();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function getPackageVersions(array $packages, bool $throwOnError = true, ?string $composerLockFile = null): array
    {
        $composerLockFile ??= $this->findComposerLockFile();
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

        return $this->getProcess($command, ['COMPOSER_MEMORY_LIMIT' => '-1'])
            ->setTty($tty)
            ->run(
                $output instanceof OutputInterface
                    ? function ($type, $line) use ($output) {
                        $output->write('    ' . $line);
                    } : $output
            ) === 0;
    }

    protected function getProcess(array $command, array $env = []): Process
    {
        return ProcessFactory::make($command, false, $this->workingPath, $env);
    }
}
