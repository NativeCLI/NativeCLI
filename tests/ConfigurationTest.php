<?php

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Configuration;

beforeEach(function () {
    $this->filesystem = new Filesystem;
    $this->testDir = sys_get_temp_dir().'/nativecli-test-'.uniqid();
    mkdir($this->testDir);
});

afterEach(function () {
    // Clean up test directory
    if (file_exists($this->testDir.'/.nativecli.json')) {
        unlink($this->testDir.'/.nativecli.json');
    }
    if (is_dir($this->testDir)) {
        rmdir($this->testDir);
    }
});

test('can create configuration instance with empty config when file does not exist', function () {
    $config = new Configuration($this->filesystem, $this->testDir);

    expect($config->get())->toBe([]);
});

test('can initialize configuration file with default values', function () {
    $config = new Configuration($this->filesystem, $this->testDir);
    $config->init();

    expect(file_exists($this->testDir.'/.nativecli.json'))->toBeTrue();

    $fileContents = json_decode(file_get_contents($this->testDir.'/.nativecli.json'), true);

    expect($fileContents)->toBeArray()
        ->and($fileContents)->toHaveKey('updates')
        ->and($fileContents['updates'])->toHaveKey('check')
        ->and($fileContents['updates']['check'])->toBeTrue()
        ->and($fileContents['updates'])->toHaveKey('auto')
        ->and($fileContents['updates']['auto'])->toBeFalse()
        ->and($fileContents)->toHaveKey('append');
});

test('throws exception when initializing if config file already exists', function () {
    $config = new Configuration($this->filesystem, $this->testDir);
    $config->init();

    expect(fn () => $config->init())
        ->toThrow(RuntimeException::class, 'Configuration file already exists');
});

test('can get configuration value by key', function () {
    $config = new Configuration($this->filesystem, $this->testDir);
    $config->init();

    // Reload config after init
    $config = new Configuration($this->filesystem, $this->testDir);

    expect($config->get('updates.check'))->toBeTrue()
        ->and($config->get('updates.auto'))->toBeFalse();
});

test('can get entire configuration', function () {
    $config = new Configuration($this->filesystem, $this->testDir);
    $config->init();

    // Reload config after init
    $config = new Configuration($this->filesystem, $this->testDir);

    $allConfig = $config->get();

    expect($allConfig)->toBeArray()
        ->and($allConfig)->toHaveKey('updates')
        ->and($allConfig)->toHaveKey('append');
});

test('returns default value when key does not exist', function () {
    $config = new Configuration($this->filesystem, $this->testDir);

    expect($config->get('nonexistent.key', 'default'))->toBe('default');
});

test('can set configuration value', function () {
    $config = new Configuration($this->filesystem, $this->testDir);
    $config->set('test.key', 'value');

    expect($config->get('test.key'))->toBe('value');
});

test('can set and save configuration value', function () {
    $config = new Configuration($this->filesystem, $this->testDir);
    $config->set('test.key', 'value');
    $config->save();

    // Reload config from file
    $newConfig = new Configuration($this->filesystem, $this->testDir);

    expect($newConfig->get('test.key'))->toBe('value');
});

test('converts string true to boolean true when setting value', function () {
    $config = new Configuration($this->filesystem, $this->testDir);
    $config->set('test.bool', 'true');

    expect($config->get('test.bool'))->toBe(true)
        ->and($config->get('test.bool'))->toBeTrue();
});

test('converts string false to boolean false when setting value', function () {
    $config = new Configuration($this->filesystem, $this->testDir);
    $config->set('test.bool', 'false');

    expect($config->get('test.bool'))->toBe(false)
        ->and($config->get('test.bool'))->toBeFalse();
});

test('can create local configuration instance', function () {
    $config = Configuration::local();

    expect($config)->toBeInstanceOf(Configuration::class);
});

test('can create global configuration instance', function () {
    $config = Configuration::global();

    expect($config)->toBeInstanceOf(Configuration::class);
});
