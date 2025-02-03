<?php

namespace NativeCLI;

use NativeCLI\Traits\PackageVersionRetrieverTrait;

class NativePHP
{
    use PackageVersionRetrieverTrait;
    public const NATIVECLI_RECOMMENDED_VERSION_URL = 'https://nativecli.com/resources/latestRecommendedVersion.json';

    /**
     * @throws Exception
     */
    public static function getPackagesForComposer(): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, self::NATIVECLI_RECOMMENDED_VERSION_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: NativeCLI/Updater'
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception('Failed to fetch recommended version');
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode recommended version');
        }

        return array_map(function ($package) {
            return sprintf('%s:%s', $package['packageName'], $package['version']);
        }, $data);
    }

    /**
     * @throws Exception
     */
    public static function getLatestVersions(): array
    {
        return [
            'nativephp/electron' => self::getVersionForPackage('nativephp/electron'),
            'nativephp/laravel' => self::getVersionForPackage('nativephp/laravel')
        ];
    }
}