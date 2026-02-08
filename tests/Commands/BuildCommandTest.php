<?php

use NativeCLI\Application;
use NativeCLI\Command\BuildCommand;

test('build command is registered', function () {
    $app = new Application();

    $command = $app->find('build');

    expect($command)->not->toBeNull()
        ->and($command->getName())->toBe('build');
});

test('build command validates build option', function () {
    $command = new class extends BuildCommand {
        public function exposeNormalize(string $build): ?string
        {
            return $this->normalizeBuildOption($build);
        }
    };

    expect($command->exposeNormalize('debug'))->toBe('debug')
        ->and($command->exposeNormalize('release'))->toBe('release')
        ->and($command->exposeNormalize('DEBUG'))->toBe('debug')
        ->and($command->exposeNormalize('fast'))->toBeNull();
});

test('build command builds native run args', function () {
    $command = new class extends BuildCommand {
        public function exposeArgs(string $php, string $build): array
        {
            return $this->buildNativeRunCommand($php, $build);
        }
    };

    expect($command->exposeArgs('/usr/bin/php', 'debug'))
        ->toBe(['/usr/bin/php', 'artisan', 'native:run', '--build=debug']);
});
