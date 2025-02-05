<?php

namespace NativeCLI;

use RuntimeException;
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

    public function getPackageVersions(array $packages): array
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

            if (!$found) {
                throw new RuntimeException("Package [$package] is not installed.");
            }
        }

        return $versions;
    }

    protected function findComposerLockFile(): string
    {
        $composerLockFile = "$this->workingPath/composer.lock";

        if (!file_exists($composerLockFile)) {
            throw new RuntimeException("Unable to locate `composer.lock` file at [$this->workingPath].");
        }

        return $composerLockFile;
    }
}