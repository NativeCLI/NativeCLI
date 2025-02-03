<?php

namespace NativeCLI;

use NativeCLI\Traits\PackageVersionRetrieverTrait;
use z4kn4fein\SemVer\Version as SemanticVersion;

class Version
{
    use PackageVersionRetrieverTrait;

    public const VERSION = '1.0.0-rc.1';

    public static function get(): ?SemanticVersion
    {
        return SemanticVersion::parseOrNull(self::VERSION);
    }

    /**
     * @throws Exception
     */
    public static function getLatestVersion(): ?SemanticVersion
    {
        return self::getVersionForPackage('petebishwhip/native-cli');
    }


}