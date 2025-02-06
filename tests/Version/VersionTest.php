<?php

namespace NativeCLI\Tests\Version;

use NativeCLI\Exception;
use NativeCLI\NativePHP;
use NativeCLI\Version;
use PHPUnit\Framework\TestCase;
use z4kn4fein\SemVer\Version as SemanticVersion;

class VersionTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testVersionNotLessThatLatestRelease()
    {
        $latestVersion = Version::getLatestVersion();
        $currentVersion = Version::get();

        $this->assertTrue($latestVersion instanceof SemanticVersion);
        $this->assertTrue($currentVersion instanceof SemanticVersion);

        $this->assertTrue(
            $currentVersion->isGreaterThanOrEqual($latestVersion),
            'Current version is less than latest release.'
        );
    }

    public function testNativePHPRecommendedVersionsCanBeRetrieved()
    {
        $packages = NativePHP::getPackagesForComposer();

        $this->assertIsArray($packages);
        $this->assertNotEmpty($packages);

        // Validate 2 keys
        $this->assertCount(2, $packages);

        // Get packages
        $retrievedPackages = array_map(function ($package) {
            return explode(':', $package)[0];
        }, $packages);

        // Validate packages
        $this->assertContains('nativephp/electron', $retrievedPackages);
        $this->assertContains('nativephp/laravel', $retrievedPackages);
    }
}
