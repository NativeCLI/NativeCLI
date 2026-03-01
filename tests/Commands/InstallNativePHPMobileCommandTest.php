<?php

use NativeCLI\Application;

test('mobile install command is registered', function () {
    $app = new Application();

    $command = $app->find('mobile:install');

    expect($command)->not->toBeNull()
        ->and($command->getName())->toBe('mobile:install');
});

test('mobile install command does not use a private composer repository', function () {
    $commandSource = file_get_contents(__DIR__ . '/../../src/Command/InstallNativePHPMobileCommand.php');
    $serviceSource = file_get_contents(__DIR__ . '/../../src/Services/MobileInstaller.php');

    expect($commandSource . $serviceSource)->not->toContain('nativephp.composer.sh');
});
