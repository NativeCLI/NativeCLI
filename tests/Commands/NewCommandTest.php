<?php

use NativeCLI\Application;

test('new command is registered', function () {
    $app = new Application();

    $command = $app->find('new');

    expect($command)->not->toBeNull()
        ->and($command->getName())->toBe('new');
});

test('new command delegates mobile installation to mobile installer service', function () {
    $commandSource = file_get_contents(__DIR__ . '/../../src/Command/NewCommand.php');

    expect($commandSource)->toContain('MobileInstaller')
        ->and($commandSource)->not->toContain("packages: ['nativephp/mobile']");
});
