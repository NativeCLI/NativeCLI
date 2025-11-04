<?php

use NativeCLI\NativePHP;
use z4kn4fein\SemVer\Version as SemanticVersion;

test('has correct list of nativephp packages', function () {
    expect(NativePHP::NATIVEPHP_PACKAGES)->toBeArray()
        ->and(NativePHP::NATIVEPHP_PACKAGES)->toContain('nativephp/desktop')
        ->and(NativePHP::NATIVEPHP_PACKAGES)->toContain('nativephp/mobile');
});

test('can get latest versions of nativephp packages', function () {
    $versions = NativePHP::getLatestVersions();

    expect($versions)->toBeArray()
        ->and($versions)->toHaveKey('nativephp/desktop')
        ->and($versions['nativephp/desktop'])->toBeInstanceOf(SemanticVersion::class);
});
