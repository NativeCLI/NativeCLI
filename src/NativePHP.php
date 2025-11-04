<?php

namespace NativeCLI;

use NativeCLI\Traits\PackageVersionRetrieverTrait;

class NativePHP
{
    use PackageVersionRetrieverTrait;

    public const NATIVEPHP_PACKAGES = [
        'nativephp/desktop',
        'nativephp/mobile',
    ];

    /**
     * @throws Exception
     */
    public static function getLatestVersions(): array
    {
        return [
            'nativephp/desktop' => self::getVersionForPackage('nativephp/desktop'),
        ];
    }
}
