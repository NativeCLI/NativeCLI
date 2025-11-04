<?php

use NativeCLI\Application;

test('check update command is registered', function () {
    $app = new Application();

    $command = $app->find('check-update');

    expect($command)->not->toBeNull()
        ->and($command->getName())->toBe('check-update');
});
