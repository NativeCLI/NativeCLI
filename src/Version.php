<?php

namespace NativeCLI;

use Illuminate\Support\Collection;
use NativeCLI\Traits\PackageVersionRetrieverTrait;
use z4kn4fein\SemVer\Version as SemanticVersion;

class Version
{
    use PackageVersionRetrieverTrait;

    public const VERSION = '1.0.1-release.1';

    public static function get(): ?SemanticVersion
    {
        return SemanticVersion::parseOrNull(self::VERSION);
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
