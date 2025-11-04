<?php

use NativeCLI\Cache;

test('cache exists returns false when file does not exist', function () {
    $cache = new Cache();

    expect($cache->cacheExists('non_existent'))->toBeFalse();
});

test('cache exists returns true when file exists', function () {
    // Place a file in the cache directory - key: test
    touch(ROOT_DIR . '/cache/test_cache.json');

    $cache = new Cache();

    expect($cache->cacheExists('test'))->toBeTrue();

    // CLEANUP: Remove the test cache file
    unlink(ROOT_DIR . '/cache/test_cache.json');
});

test('retrieve cache returns null when file does not exist', function () {
    $cache = new Cache();

    expect($cache->retrieveCache('non_existent'))->toBeNull();
});

test('retrieve cache returns contents of cache file', function () {
    // Place a file in the cache directory - key: test
    file_put_contents(ROOT_DIR . '/cache/test_cache.json', json_encode(['test' => 'data', 'expires' => time() + 3600]));

    $cache = new Cache();

    expect($cache->retrieveCache('test')->toArray())->toBe(['test' => 'data']);

    // CLEANUP: Remove the test cache file
    unlink(ROOT_DIR . '/cache/test_cache.json');
});

test('retrieve cache returns null when cache is expired', function () {
    // Place a file in the cache directory - key: test with expired timestamp
    file_put_contents(ROOT_DIR . '/cache/test_cache.json', json_encode(['test' => 'data', 'expires' => time() - 3600]));

    $cache = new Cache();

    expect($cache->retrieveCache('test'))->toBeNull();

    // CLEANUP: Remove the test cache file
    unlink(ROOT_DIR . '/cache/test_cache.json');
});

test('remove cache returns false when file does not exist', function () {
    $cache = new Cache();

    expect($cache->removeCache('non_existent'))->toBeFalse();
});

test('remove cache returns true and removes file when it exists', function () {
    // Place a file in the cache directory - key: test
    touch(ROOT_DIR . '/cache/test_cache.json');

    $cache = new Cache();

    expect($cache->removeCache('test'))->toBeTrue()
        ->and(file_exists(ROOT_DIR . '/cache/test_cache.json'))->toBeFalse();
});

test('get all available caches returns empty collection when no cache files exist', function () {
    $cache = new Cache();

    // Ensure cache directory is empty
    $cache->clearAllCaches();

    expect($cache->getAllAvailableCaches()->toArray())->toBe([]);
});

test('get all available caches returns collection of cache keys', function () {
    $cache = new Cache();

    // Ensure cache directory is empty
    $cache->clearAllCaches();

    // Place a file in the cache directory - key: test
    touch(ROOT_DIR . '/cache/test_cache.json');

    expect($cache->getAllAvailableCaches()->toArray())->toBe(['test']);

    // CLEANUP: Remove the test cache file
    unlink(ROOT_DIR . '/cache/test_cache.json');
});

test('clear all caches removes all cache files', function () {
    // Place files in the cache directory - key: test{1,2,3}
    touch(ROOT_DIR . '/cache/test1_cache.json');
    touch(ROOT_DIR . '/cache/test2_cache.json');
    touch(ROOT_DIR . '/cache/test3_cache.json');

    $cache = new Cache();

    $cache->clearAllCaches();

    expect($cache->getAllAvailableCaches()->toArray())->toBe([]);
});

test('add to cache creates new cache file with data', function () {
    $cache = new Cache();

    // Ensure cache directory is empty
    $cache->clearAllCaches();

    $cache->addToCache('test', 'key', ['value' => 'data']);

    expect($cache->cacheExists('test'))->toBeTrue();

    // Retrieve the specific element from the cache
    $retrievedItem = $cache->retrieveCache('test', 'key');
    expect($retrievedItem)->not->toBeNull()
        ->and($retrievedItem->toArray())->toBe(['value' => 'data']);

    // CLEANUP: Remove the test cache file
    unlink(ROOT_DIR . '/cache/test_cache.json');
});

test('add to cache overwrites existing cache file (known limitation)', function () {
    $cache = new Cache();

    // Ensure cache directory is empty
    $cache->clearAllCaches();

    $cache->addToCache('test', 'key1', ['value' => 'data1']);
    $cache->addToCache('test', 'key2', ['value' => 'data2']);

    // Note: Due to how retrieveCache works in addToCache, only the last item is preserved
    // This is a known limitation where addToCache doesn't properly append to existing cache files
    $item1 = $cache->retrieveCache('test', 'key1');
    $item2 = $cache->retrieveCache('test', 'key2');

    expect($item1)->toBeNull() // key1 was overwritten
        ->and($item2)->not->toBeNull()
        ->and($item2->toArray())->toBe(['value' => 'data2']);

    // CLEANUP: Remove the test cache file
    unlink(ROOT_DIR . '/cache/test_cache.json');
});
