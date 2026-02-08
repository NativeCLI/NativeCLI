<?php

use NativeCLI\Application;
use NativeCLI\Command\PluginAddCommand;
use NativeCLI\Command\PluginListCommand;
use NativeCLI\Command\PluginPermissionsCommand;
use NativeCLI\Command\PluginRemoveCommand;

test('plugin commands are registered', function () {
    $app = new Application();

    expect($app->find('plugin:add'))->not->toBeNull();
    expect($app->find('plugin:list'))->not->toBeNull();
    expect($app->find('plugin:remove'))->not->toBeNull();
    expect($app->find('plugin:provider'))->not->toBeNull();
    expect($app->find('plugin:permissions'))->not->toBeNull();
});

test('plugin add command builds register args', function () {
    $command = new class () extends PluginAddCommand {
        public function exposeRegister(string $php, string $package, bool $force): array
        {
            return $this->buildPluginRegisterCommand($php, $package, $force);
        }
    };

    expect($command->exposeRegister('/usr/bin/php', 'vendor/pkg', false))
        ->toBe(['/usr/bin/php', 'artisan', 'native:plugin:register', 'vendor/pkg'])
        ->and($command->exposeRegister('/usr/bin/php', 'vendor/pkg', true))
        ->toBe(['/usr/bin/php', 'artisan', 'native:plugin:register', 'vendor/pkg', '--force']);
});

test('plugin list command builds args', function () {
    $command = new class () extends PluginListCommand {
        public function exposeList(string $php, bool $json, bool $all): array
        {
            return $this->buildPluginListCommand($php, $json, $all);
        }
    };

    expect($command->exposeList('/usr/bin/php', false, false))
        ->toBe(['/usr/bin/php', 'artisan', 'native:plugin:list'])
        ->and($command->exposeList('/usr/bin/php', true, true))
        ->toBe(['/usr/bin/php', 'artisan', 'native:plugin:list', '--json', '--all']);
});

test('plugin remove command builds args', function () {
    $command = new class () extends PluginRemoveCommand {
        public function exposeRemove(string $php, string $package, bool $force, bool $keep): array
        {
            return $this->buildPluginUninstallCommand($php, $package, $force, $keep);
        }
    };

    expect($command->exposeRemove('/usr/bin/php', 'vendor/pkg', false, false))
        ->toBe(['/usr/bin/php', 'artisan', 'native:plugin:uninstall', 'vendor/pkg'])
        ->and($command->exposeRemove('/usr/bin/php', 'vendor/pkg', true, true))
        ->toBe(['/usr/bin/php', 'artisan', 'native:plugin:uninstall', 'vendor/pkg', '--force', '--keep-files']);
});

test('plugin permissions extracts permissions from payload', function () {
    $command = new class () extends PluginPermissionsCommand {
        public function exposeNormalize(array $payload): array
        {
            return $this->normalizePluginPayload($payload);
        }

        public function exposeExtract(array $plugins): array
        {
            return $this->extractPermissions($plugins);
        }
    };

    $payload = [
        'plugins' => [
            ['name' => 'vendor/a', 'permissions' => ['camera', 'location', 'camera']],
            ['package' => 'vendor/b', 'permissions' => []],
            ['name' => 'vendor/c', 'permissions' => ['microphone']],
        ],
    ];

    $normalized = $command->exposeNormalize($payload);
    $permissions = $command->exposeExtract($normalized);

    expect($permissions)->toBe([
        'vendor/a' => ['camera', 'location'],
        'vendor/c' => ['microphone'],
    ]);
});
