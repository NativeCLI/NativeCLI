<?php

use NativeCLI\Command\Make\MakeMenuCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    // Create a temporary test project directory
    $this->testDir = sys_get_temp_dir() . '/test_project_' . uniqid();
    mkdir($this->testDir);
    mkdir($this->testDir . '/app');
    mkdir($this->testDir . '/app/Providers');
    mkdir($this->testDir . '/app/Listeners');

    // Create a minimal artisan file
    file_put_contents($this->testDir . '/artisan', '<?php // artisan');

    // Create a minimal composer.json with NativePHP
    $composerJson = [
        'require' => [
            'nativephp/desktop' => '^1.0',
        ],
    ];
    file_put_contents($this->testDir . '/composer.json', json_encode($composerJson));

    // Create a minimal NativeAppServiceProvider
    $provider = <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class NativeAppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Boot code
    }
}
PHP;
    file_put_contents($this->testDir . '/app/Providers/NativeAppServiceProvider.php', $provider);

    // Store original working directory and change to test directory
    $this->originalCwd = getcwd();
    chdir($this->testDir);
});

afterEach(function () {
    // Change back to original directory
    chdir($this->originalCwd);

    // Clean up test directory
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->testDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    rmdir($this->testDir);
});

it('generates app menu type', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'MyApp',
        '--type' => 'app',
        '--no-listener' => true,
    ]);

    expect($commandTester->getStatusCode())->toBe(0);

    $providerContent = file_get_contents($this->testDir . '/app/Providers/NativeAppServiceProvider.php');

    expect($providerContent)
        ->toContain('use Native\Laravel\Facades\Menu;')
        ->toContain("Menu::new()")
        ->toContain("->label('MyApp')")
        ->toContain("->item('About {APP_NAME}'")
        ->toContain("->item('Preferences'")
        ->toContain("->item('Quit {APP_NAME}'")
        ->toContain("->register();");
});

it('generates file menu type', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
        '--no-listener' => true,
    ]);

    expect($commandTester->getStatusCode())->toBe(0);

    $providerContent = file_get_contents($this->testDir . '/app/Providers/NativeAppServiceProvider.php');

    expect($providerContent)
        ->toContain("->label('File')")
        ->toContain("->item('New'")
        ->toContain("->item('Open'")
        ->toContain("->item('Save'")
        ->toContain("->item('Close'");
});

it('generates edit menu type', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'Edit',
        '--type' => 'edit',
        '--no-listener' => true,
    ]);

    expect($commandTester->getStatusCode())->toBe(0);

    $providerContent = file_get_contents($this->testDir . '/app/Providers/NativeAppServiceProvider.php');

    expect($providerContent)
        ->toContain("->label('Edit')")
        ->toContain("->item('Undo'")
        ->toContain("->item('Redo'")
        ->toContain("->item('Cut'")
        ->toContain("->item('Copy'")
        ->toContain("->item('Paste'");
});

it('generates view menu type', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'View',
        '--type' => 'view',
        '--no-listener' => true,
    ]);

    expect($commandTester->getStatusCode())->toBe(0);

    $providerContent = file_get_contents($this->testDir . '/app/Providers/NativeAppServiceProvider.php');

    expect($providerContent)
        ->toContain("->label('View')")
        ->toContain("->item('Reload'")
        ->toContain("->item('Zoom In'")
        ->toContain("->item('Toggle Fullscreen'")
        ->toContain("->item('Toggle Developer Tools'");
});

it('generates window menu type', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'Window',
        '--type' => 'window',
        '--no-listener' => true,
    ]);

    expect($commandTester->getStatusCode())->toBe(0);

    $providerContent = file_get_contents($this->testDir . '/app/Providers/NativeAppServiceProvider.php');

    expect($providerContent)
        ->toContain("->label('Window')")
        ->toContain("->item('Minimize'")
        ->toContain("->item('Close'")
        ->toContain("->item('Bring All to Front'");
});

it('creates backup of provider file', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
        '--no-listener' => true,
    ]);

    $backupFile = $this->testDir . '/app/Providers/NativeAppServiceProvider.php.backup';
    expect($backupFile)->toBeFile();

    $backupContent = file_get_contents($backupFile);
    expect($backupContent)->not->toContain("->label('File')");
});

it('generates event listener when not skipped', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->setInputs(['no']); // Answer "no" to overwrite question

    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
    ]);

    $listenerFile = $this->testDir . '/app/Listeners/FileMenuListener.php';
    expect($listenerFile)->toBeFile();

    $listenerContent = file_get_contents($listenerFile);
    expect($listenerContent)
        ->toContain('namespace App\Listeners;')
        ->toContain('class FileMenuListener')
        ->toContain('public function handle(MenuItemClicked $event): void');
});

it('skips listener generation with --no-listener flag', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
        '--no-listener' => true,
    ]);

    $listenerFile = $this->testDir . '/app/Listeners/FileMenuListener.php';
    expect($listenerFile)->not->toBeFile();
});

it('fails when not in Laravel project', function () {
    // Remove artisan file
    unlink($this->testDir . '/artisan');

    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
    ]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Laravel project');
});

it('fails when NativePHP not installed', function () {
    // Update composer.json to not have NativePHP
    $composerJson = [
        'require' => [
            'laravel/framework' => '^10.0',
        ],
    ];
    file_put_contents($this->testDir . '/composer.json', json_encode($composerJson));

    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
    ]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('NativePHP does not appear to be installed');
});

it('fails when provider file not found', function () {
    // Remove the provider file
    unlink($this->testDir . '/app/Providers/NativeAppServiceProvider.php');

    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
    ]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Could not find NativeAppServiceProvider');
});

it('uses menubar indentation with --menubar flag', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
        '--menubar' => true,
        '--no-listener' => true,
    ]);

    expect($commandTester->getStatusCode())->toBe(0);

    $providerContent = file_get_contents($this->testDir . '/app/Providers/NativeAppServiceProvider.php');

    // Menu bar menus should have 12 spaces indentation (not 8 like regular menus)
    expect($providerContent)->toMatch('/^\s{12}Menu::new\(\)/m');
});

it('preserves existing code in provider', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    // Add first menu
    $commandTester->execute([
        'name' => 'File',
        '--type' => 'file',
        '--no-listener' => true,
    ]);

    // Add second menu
    $commandTester->execute([
        'name' => 'Edit',
        '--type' => 'edit',
        '--no-listener' => true,
    ]);

    $providerContent = file_get_contents($this->testDir . '/app/Providers/NativeAppServiceProvider.php');

    // Both menus should be present
    expect($providerContent)
        ->toContain("->label('File')")
        ->toContain("->label('Edit')");
});

it('rejects invalid menu type', function () {
    $application = new Application();
    $application->add(new MakeMenuCommand());

    $command = $application->find('make:menu');
    $commandTester = new CommandTester($command);

    $commandTester->execute([
        'name' => 'Test',
        '--type' => 'invalid_type',
    ]);

    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('Invalid menu type');
});
