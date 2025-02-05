<?php

namespace NativeCLI\Traits;

use Illuminate\Support\Collection;
use Petebishwhip\NativePhpCli\Cache;
use NativeCLI\Exception;
use NativeCLI\Exception\RateLimitedException;
use RuntimeException;
use z4kn4fein\SemVer\Version as SemanticVersion;

trait PackageVersionRetrieverTrait
{
    /**
     * @throws Exception
     * @throws RateLimitedException
     */
    protected static function getVersionForPackage(string $package): ?SemanticVersion
    {
        if (self::checkCache($package) !== null) {
            return SemanticVersion::parseOrNull(self::checkCache($package));
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/releases/latest',
            $package
        );

        $response = self::makeRequest($url);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode latest version');
        }

        if (empty($data['tag_name'])) {
            if (str_contains($response, 'API rate limit exceeded')) {
                throw Exception\RateLimitedException::for($url);
            }

            throw new RuntimeException('Failed to retrieve version for ' . $package);
        }

        $latestVersion = $data['tag_name'];

        self::cacheVersion($package, $latestVersion);

        return SemanticVersion::parseOrNull($latestVersion);
    }

    protected static function getAllAvailableVersions(string $package): Collection
    {
        $cache = new Cache();
        $cacheKey = 'available_versions';
        $sort = function (SemanticVersion $a, SemanticVersion $b) {
            // Sort by semantic version
            return $a->isGreaterThan($b) ? -1 : 1;
        };

        if ($cache->cacheExists($cacheKey)) {
            if (($storedInfo = $cache->retrieveCache($cacheKey, $package)) !== null) {
                return $storedInfo->mapWithKeys(function ($item) {
                        return [$item => SemanticVersion::parseOrNull($item)];
                    })
                    ->filter()
                    ->sort($sort);
            }
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/releases',
            $package
        );

        $response = self::makeRequest($url);

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode latest version');
        }

        $cache->addToCache($cacheKey, $package, array_column($data, 'tag_name'));

        return collect($data)
            ->mapWithKeys(function ($release) {
                return [$release['tag_name'] => SemanticVersion::parseOrNull($release['tag_name'])];
            })
            ->filter()
            ->sort($sort);
    }

    private static function makeRequest(string $url): ?string
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: NativeCLI/Updater'
        ]);

        $response = curl_exec($ch);

        if ($response === false || !is_string($response)) {
            return null;
        }

        curl_close($ch);

        return $response;
    }

    private static function checkCache(string $package)
    {
        $cacheData = self::getCacheData();

        $packageData = $cacheData->where('package', $package)->first();

        if (empty($packageData)) {
            return null;
        }

        if (time() - $packageData['timestamp'] > 3600) {
            return null;
        }

        return $packageData['version'];
    }

    private static function getCacheFileLocation(): string
    {
        return __DIR__ . '/../../cache/version_cache.json';
    }

    private static function cacheVersion(string $package, string $version): void
    {
        if (!file_exists(dirname(self::getCacheFileLocation()))) {
            mkdir(dirname(self::getCacheFileLocation()), 0777, true);
        }

        $cacheData = self::getCacheData();

        $packageInfo = $cacheData->where('package', $package)->first();

        if ($packageInfo !== null) {
            $cacheData = $cacheData->reject(function ($item) use ($package) {
                return $item['package'] === $package;
            });
        }

        $cacheData->push([
            'package' => $package,
            'version' => $version,
            'timestamp' => time(),
        ]);

        file_put_contents(self::getCacheFileLocation(), json_encode($cacheData->toArray()));
    }

    private static function getCacheData(): ?Collection
    {
        if (!file_exists(self::getCacheFileLocation())) {
            return collect();
        }

        $info = json_decode(file_get_contents(self::getCacheFileLocation()), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return collect();
        }

        return collect($info);
    }
}