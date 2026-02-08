<?php

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;

beforeEach(function () {
    $this->filesystem = new Filesystem();
    $this->composer = new Composer($this->filesystem);
});

test('can determine global composer home directory', function () {
    $homeDir = $this->composer->findGlobalComposerHomeDirectory();

    expect($homeDir)->toBeString()
        ->and($homeDir)->not->toBeEmpty()
        ->and(is_dir($homeDir))->toBeTrue();
});

test('can find global composer json file', function () {
    $composerJson = $this->composer->findGlobalComposerFile('composer.json');

    expect($composerJson)->toBeString()
        ->and(file_exists($composerJson))->toBeTrue()
        ->and($composerJson)->toContain('composer.json');
});

test('can find global composer lock file', function () {
    $composerLock = $this->composer->findGlobalComposerFile('composer.lock');

    expect($composerLock)->toBeString()
        ->and(file_exists($composerLock))->toBeTrue()
        ->and($composerLock)->toContain('composer.lock');
});

test('throws exception when global composer file does not exist', function () {
    expect(fn () => $this->composer->findGlobalComposerFile('nonexistent.json'))
        ->toThrow(InvalidArgumentException::class, 'Global composer file not found');
});

test('can check if composer file is present in working path', function () {
    $workingPath = ROOT_DIR;
    $composer = new Composer($this->filesystem, $workingPath);

    expect($composer->isComposerFilePresent())->toBeTrue();
});

test('returns false when composer file is not present', function () {
    $tempDir = sys_get_temp_dir() . '/test-no-composer-' . uniqid();
    mkdir($tempDir);

    $composer = new Composer($this->filesystem, $tempDir);

    expect($composer->isComposerFilePresent())->toBeFalse();

    // Cleanup
    rmdir($tempDir);
});

test('can get composer file path', function () {
    $workingPath = ROOT_DIR;
    $composer = new Composer($this->filesystem, $workingPath);

    $composerFile = $composer->getComposerFile();

    expect($composerFile)->toBeString()
        ->and($composerFile)->toContain('composer.json')
        ->and(file_exists($composerFile))->toBeTrue();
});

test('can get package versions from composer lock', function () {
    $workingPath = ROOT_DIR;
    $composer = new Composer($this->filesystem, $workingPath);

    $versions = $composer->getPackageVersions(['guzzlehttp/guzzle']);

    expect($versions)->toBeArray()
        ->and($versions)->toHaveKey('guzzlehttp/guzzle')
        ->and($versions['guzzlehttp/guzzle'])->toBeInstanceOf(z4kn4fein\SemVer\Version::class);
});

test('throws exception when package not found in composer lock', function () {
    $workingPath = ROOT_DIR;
    $composer = new Composer($this->filesystem, $workingPath);

    expect(fn () => $composer->getPackageVersions(['nonexistent/package']))
        ->toThrow(RuntimeException::class, 'Package [nonexistent/package] is not installed');
});

test('returns empty array when package not found and throwOnError is false', function () {
    $workingPath = ROOT_DIR;
    $composer = new Composer($this->filesystem, $workingPath);

    $versions = $composer->getPackageVersions(['nonexistent/package'], throwOnError: false);

    expect($versions)->toBeArray()
        ->and($versions)->not->toHaveKey('nonexistent/package');
});

test('can check if package exists in composer file', function () {
    $workingPath = ROOT_DIR;
    $composer = new Composer($this->filesystem, $workingPath);

    expect($composer->packageExistsInComposerFile('guzzlehttp/guzzle'))->toBeTrue()
        ->and($composer->packageExistsInComposerFile('nonexistent/package'))->toBeFalse();
});

test('composer processes disable timeouts', function () {
    $composer = new class(new Illuminate\Filesystem\Filesystem()) extends NativeCLI\Composer {
        public function exposeProcess(): Symfony\Component\Process\Process
        {
            return $this->getProcess(['echo', 'hi']);
        }
    };

    $process = $composer->exposeProcess();

    expect($process->getTimeout())->toBeNull()
        ->and($process->getIdleTimeout())->toBeNull();
});
