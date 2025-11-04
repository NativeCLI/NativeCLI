<?php

use NativeCLI\Application;

test('new command is registered', function () {
    $app = new Application();

    $command = $app->find('new');

    expect($command)->not->toBeNull()
        ->and($command->getName())->toBe('new');
});
