<?php

use NativeCLI\Application;
use NativeCLI\Command\JumpCommand;

test('jump command is registered', function () {
    $app = new Application();

    $command = $app->find('jump');

    expect($command)->not->toBeNull()
        ->and($command->getName())->toBe('jump');
});

test('jump command builds args', function () {
    $command = new class () extends JumpCommand {
        public function exposeArgs(string $php): array
        {
            return $this->buildJumpCommand($php);
        }
    };

    expect($command->exposeArgs('/usr/bin/php'))
        ->toBe(['/usr/bin/php', 'artisan', 'native:jump']);
});
