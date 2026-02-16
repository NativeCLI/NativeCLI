<?php

namespace NativeCLI;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use NativeCLI\Support\ProcessFactory;
use Throwable;
use z4kn4fein\SemVer\Version as SemanticVersion;

class Composer extends \Illuminate\Support\Composer
{
    public function findGlobalComposerHomeDirectory(): string
    {
        $candidates = [
            $this->composerHomeFromEnvironment(),
            $this->composerHomeFromCommand(),
            $this->composerHomeFromDefaultLocations(),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && is_dir($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to determine global composer home directory.');
    }

    private function composerHomeFromEnvironment(): ?string
    {
        $composerHome = getenv('COMPOSER_HOME');

        if ($composerHome === false || trim($composerHome) === '') {
            return null;
        }

        return rtrim(trim($composerHome), DIRECTORY_SEPARATOR);
    }

    private function composerHomeFromCommand(): ?string
    {
        $composer = (new ExecutableFinder())->find('composer');

        if ($composer === null) {
            return null;
        }

        $process = ProcessFactory::make([$composer, '-n', 'config', '--global', 'home'], tty: false);
        $process->run();

        $globalDirectory = $this->extractPathFromOutput($process->getOutput())
            ?? $this->extractPathFromOutput($process->getErrorOutput());

        if ($globalDirectory === null) {
            return null;
        }

        return rtrim($globalDirectory, DIRECTORY_SEPARATOR);
    }

    private function composerHomeFromDefaultLocations(): ?string
    {
        $home = getenv('HOME') ?: null;
        $homePath = $home === false ? null : $home;
        $xdgConfigHome = getenv('XDG_CONFIG_HOME') ?: null;
        $appData = getenv('APPDATA') ?: null;
        $candidates = [
            $xdgConfigHome ? $xdgConfigHome . DIRECTORY_SEPARATOR . 'composer' : null,
            $homePath ? $homePath . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'composer' : null,
            $homePath ? $homePath . DIRECTORY_SEPARATOR . '.composer' : null,
            $appData ? $appData . DIRECTORY_SEPARATOR . 'Composer' : null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && is_dir($candidate)) {
                return rtrim($candidate, DIRECTORY_SEPARATOR);
            }
        }

        return null;
    }

    private function extractPathFromOutput(string $output): ?string
    {
        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];

        foreach (array_reverse($lines) as $line) {
            $candidate = trim($line, " \t\n\r\0\x0B\"'");

            if ($candidate === '') {
                continue;
            }

            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
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
