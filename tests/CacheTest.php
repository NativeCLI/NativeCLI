<?php

namespace NativeCLI\Tests;

use NativeCLI\Cache;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    public function testCacheExistsCommand()
    {
        // Place a file in the cache directory - key: test
        touch(ROOT_DIR . '/cache/test_cache.json');

        $cache = new Cache();

        // TEST: cacheExists returns false when a file does not exist
        $this->assertFalse($cache->cacheExists('non_existent'));

        // TEST: cacheExists returns true when a file exists
        $this->assertTrue($cache->cacheExists('test'));

        // CLEANUP: Remove the test cache file
        unlink(ROOT_DIR . '/cache/test_cache.json');
    }

    public function testRetrieveCacheCommand()
    {
        // Place a file in the cache directory - key: test
        file_put_contents(ROOT_DIR . '/cache/test_cache.json', json_encode(['test' => 'data']));

        $cache = new Cache();

        // TEST: retrieveCache returns null when a file does not exist
        $this->assertNull($cache->retrieveCache('non_existent'));

        // TEST: retrieveCache returns the contents of the cache file
        $this->assertEquals(['test' => 'data'], $cache->retrieveCache('test')->toArray());

        // CLEANUP: Remove the test cache file
        unlink(ROOT_DIR . '/cache/test_cache.json');
    }

    public function testRemoveCacheCommand()
    {
        // Place a file in the cache directory - key: test
        touch(ROOT_DIR . '/cache/test_cache.json');

        $cache = new Cache();

        // TEST: removeCache returns false when a file does not exist
        $this->assertFalse($cache->removeCache('non_existent'));

        // TEST: removeCache returns true when a file exists
        $this->assertTrue($cache->removeCache('test'));

        // TEST: The cache file has been removed
        $this->assertFalse(file_exists(ROOT_DIR . '/cache/test_cache.json'));
    }

    public function testGetAllAvailableCachesCommand()
    {
        $cache = new Cache();

        // TEST: getAllAvailableCaches returns an empty collection when no cache files exist
        $this->assertEquals([], $cache->getAllAvailableCaches()->toArray());

        // Place a file in the cache directory - key: test
        touch(ROOT_DIR . '/cache/test_cache.json');

        // TEST: getAllAvailableCaches returns a collection of cache keys
        $this->assertEquals(['test'], $cache->getAllAvailableCaches()->toArray());

        // CLEANUP: Remove the test cache file
        unlink(ROOT_DIR . '/cache/test_cache.json');
    }

    /**
     * @return void
     */
    #[Depends('testGetAllAvailableCachesCommand')]
    public function testClearAllCachesCommand()
    {
        // Place a file in the cache directory - key: test{1,2,3}
        touch(ROOT_DIR . '/cache/test1_cache.json');
        touch(ROOT_DIR . '/cache/test2_cache.json');
        touch(ROOT_DIR . '/cache/test3_cache.json');

        $cache = new Cache();

        // TEST: clearAllCaches removes all cache files
        $cache->clearAllCaches();

        // TEST: The cache directory is empty
        $this->assertEquals([], $cache->getAllAvailableCaches()->toArray());
    }
}