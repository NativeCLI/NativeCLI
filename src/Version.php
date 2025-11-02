<?php

namespace NativeCLI;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use NativeCLI\Traits\PackageVersionRetrieverTrait;
use z4kn4fein\SemVer\Version as SemanticVersion;

class Version
{
    use PackageVersionRetrieverTrait;

    public static function get(): ?SemanticVersion
    {
        $composer = new Composer(new Filesystem);

        return $composer->getPackageVersions(
            packages: ['nativecli/nativecli'],
            throwOnError: false,
            composerLockFile: $composer->findGlobalComposerFile('composer.lock')
        )['nativecli/nativecli'] ?? null;
    }

    /**
     * @throws Exception
     */
    public static function getLatestVersion(): ?SemanticVersion
    {
        return self::getVersionForPackage('nativecli/nativecli');
    }

    /**
     * @throws Exception
     */
    public static function getAvailableVersions(): Collection
    {
        return self::getAllAvailableVersions('nativecli/nativecli');
    }

    public static function isCurrentVersion(SemanticVersion $version): bool
    {
        return $version->isEqual(self::get());
    }
}
