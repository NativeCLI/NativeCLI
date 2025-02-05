<?php

namespace NativeCLI;

use Illuminate\Support\Collection;

class Cache
{
    public const CACHE_DIR = __DIR__ . '/../cache';
    public const CACHE_FILENAME_FORMAT = '%s_cache.json';

    public function cacheExists(string $cache): bool
    {
        return file_exists(self::CACHE_DIR . '/' . $this->getCacheFileName($cache));
    }

    private function getCacheFileName(string $key): string
    {
        return sprintf(self::CACHE_FILENAME_FORMAT, $key);
    }

    public function retrieveCache(string $key, ?string $element = null): ?Collection
    {
        $cacheFile = self::CACHE_DIR . '/' . $this->getCacheFileName($key);

        if (!$this->cacheExists($key)) {
            return null;
        }

        $cacheData = collect(json_decode(file_get_contents($cacheFile), true));

        if ($element !== null) {
            $cacheData = $cacheData->get($element);
        }

        if ($cacheData === null) {
            return null;
        }

        if (!isset($cacheData['expires']) || time() > $cacheData['expires']) {
            return null;
        }

        return collect($cacheData)->except('expires');
    }

    public function removeCache(string $key): bool
    {
        $cacheFile = self::CACHE_DIR . '/' . $this->getCacheFileName($key);

        if ($this->cacheExists($key)) {
            return unlink($cacheFile);
        }

        return false;
    }

    public function getAllAvailableCaches(): Collection
    {
        $cacheFiles = scandir(self::CACHE_DIR);

        return collect($cacheFiles)
            ->filter(function ($file) {
                return $file !== '.' && $file !== '..' && $file !== '.gitkeep';
            })
            ->map(function ($file) {
                return str_replace('_cache.json', '', $file);
            })->values();
    }

    public function clearAllCaches(): void
    {
        $caches = $this->getAllAvailableCaches();

        $caches->each(function ($cache) {
            $this->removeCache($cache);
        });
    }

    public function addToCache(string $cacheKey, string $key, array $value): void
    {
        $cacheFile = self::CACHE_DIR . '/' . $this->getCacheFileName($cacheKey);

        $cacheData = $this->retrieveCache($cacheKey);

        if ($cacheData === null) {
            $cacheData = collect();
        }

        $cacheData->put($key, array_merge(['expires' => time() + 3600], $value));

        file_put_contents(
            $cacheFile,
            json_encode($cacheData->toArray())
        );
    }
}