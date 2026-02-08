<?php

use NativeCLI\Application;
use NativeCLI\Command\MobileUpgradeCommand;

test('mobile upgrade command is registered', function () {
    $app = new Application();

    $command = $app->find('mobile:upgrade');

    expect($command)->not->toBeNull()
        ->and($command->getName())->toBe('mobile:upgrade');
});

test('mobile upgrade builds install args', function () {
    $command = new class () extends MobileUpgradeCommand {
        public function exposeInstall(string $php, bool $force): array
        {
            return $this->buildInstallCommand($php, $force);
        }
    };

    expect($command->exposeInstall('/usr/bin/php', false))
        ->toBe(['/usr/bin/php', 'artisan', 'native:install', '--no-interaction'])
        ->and($command->exposeInstall('/usr/bin/php', true))
        ->toBe(['/usr/bin/php', 'artisan', 'native:install', '--no-interaction', '--force']);
});

test('mobile upgrade builds plugin list args', function () {
    $command = new class () extends MobileUpgradeCommand {
        public function exposeList(string $php): array
        {
            return $this->buildPluginListCommand($php);
        }
    };

    expect($command->exposeList('/usr/bin/php'))
        ->toBe(['/usr/bin/php', 'artisan', 'native:plugin:list', '--all']);
});
