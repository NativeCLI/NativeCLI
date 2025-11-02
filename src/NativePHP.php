<?php

namespace NativeCLI;

use NativeCLI\Traits\PackageVersionRetrieverTrait;

class NativePHP
{
    use PackageVersionRetrieverTrait;

    public const NATIVEPHP_PACKAGES = [
        'nativephp/electron',
        'nativephp/laravel',
        'nativephp/ios',
    ];

    /**
     * @throws Exception
     */
    public static function getLatestVersions(): array
    {
        return [
            'nativephp/electron' => self::getVersionForPackage('nativephp/electron'),
            'nativephp/laravel' => self::getVersionForPackage('nativephp/laravel'),
        ];
    }
}
