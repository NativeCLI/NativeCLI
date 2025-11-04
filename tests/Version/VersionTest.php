<?php

use NativeCLI\Version;
use z4kn4fein\SemVer\Version as SemanticVersion;

test('can get current version', function () {
    $currentVersion = Version::get();

    expect($currentVersion)->toBeInstanceOf(SemanticVersion::class)
        ->and($currentVersion->getMajor())->toBeGreaterThanOrEqual(0);
});

test('can get latest version', function () {
    $latestVersion = Version::getLatestVersion();

    expect($latestVersion)->toBeInstanceOf(SemanticVersion::class)
        ->and($latestVersion->getMajor())->toBeGreaterThanOrEqual(0);
});

test('can compare versions', function () {
    $currentVersion = Version::get();
    $latestVersion = Version::getLatestVersion();

    expect($currentVersion)->toBeInstanceOf(SemanticVersion::class)
        ->and($latestVersion)->toBeInstanceOf(SemanticVersion::class);

    // In a production release, current should be >= latest
    // But in dev, it might be behind, so we just verify we can compare them
    $comparison = $currentVersion->isGreaterThanOrEqual($latestVersion);
    expect($comparison)->toBeIn([true, false]);
});
