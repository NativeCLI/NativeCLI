<?php

use NativeCLI\Generators\MenuGenerator;

it('returns all menu types', function () {
    $generator = new MenuGenerator();
    $types = $generator->getMenuTypes();

    expect($types)->toBeArray()
        ->and($types)->toContain('app', 'file', 'edit', 'view', 'window', 'custom');
});

it('returns menu type description', function () {
    $generator = new MenuGenerator();
    $description = $generator->getMenuTypeDescription('app');

    expect($description)->toBeString()
        ->and($description)->toContain('About', 'Preferences', 'Quit');
});

it('generates menu code for app type', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('MyApp', 'app', [], false);

    expect($code)->toBeString()
        ->and($code)->toContain("Menu::new()")
        ->and($code)->toContain("->label('MyApp')")
        ->and($code)->toContain("->item('About {APP_NAME}'")
        ->and($code)->toContain("->item('Preferences'")
        ->and($code)->toContain("->item('Quit {APP_NAME}'")
        ->and($code)->toContain("->separator()")
        ->and($code)->toContain("->register();");
});

it('generates menu code for file type', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('File', 'file', [], false);

    expect($code)->toBeString()
        ->and($code)->toContain("->item('New'")
        ->and($code)->toContain("->item('Open'")
        ->and($code)->toContain("->item('Save'")
        ->and($code)->toContain("->item('Close'");
});

it('generates menu code for edit type', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('Edit', 'edit', [], false);

    expect($code)->toBeString()
        ->and($code)->toContain("->item('Undo'")
        ->and($code)->toContain("->item('Redo'")
        ->and($code)->toContain("->item('Cut'")
        ->and($code)->toContain("->item('Copy'")
        ->and($code)->toContain("->item('Paste'");
});

it('generates menu code for view type', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('View', 'view', [], false);

    expect($code)->toBeString()
        ->and($code)->toContain("->item('Reload'")
        ->and($code)->toContain("->item('Zoom In'")
        ->and($code)->toContain("->item('Zoom Out'")
        ->and($code)->toContain("->item('Toggle Fullscreen'")
        ->and($code)->toContain("->item('Toggle Developer Tools'");
});

it('generates menu code for window type', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('Window', 'window', [], false);

    expect($code)->toBeString()
        ->and($code)->toContain("->item('Minimize'")
        ->and($code)->toContain("->item('Close'")
        ->and($code)->toContain("->item('Bring All to Front'");
});

it('generates menu code with custom items', function () {
    $generator = new MenuGenerator();
    $items = [
        ['label' => 'Custom Item', 'action' => 'custom.action'],
        ['separator' => true],
        ['label' => 'Another Item', 'action' => 'another.action', 'shortcut' => 'CmdOrCtrl+K'],
    ];

    $code = $generator->generateMenuCode('Custom', 'custom', $items, false);

    expect($code)->toBeString()
        ->and($code)->toContain("->label('Custom')")
        ->and($code)->toContain("->item('Custom Item', 'custom.action')")
        ->and($code)->toContain("->separator()")
        ->and($code)->toContain("->item('Another Item', 'another.action', 'CmdOrCtrl+K')");
});

it('generates menu code with proper indentation for menubar', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('File', 'file', [], true);

    // Menu bar menus should have extra indentation (12 spaces instead of 8)
    // Code starts with a newline, so we check for the indentation after it
    expect($code)->toContain("\n            Menu::new()");
});

it('generates menu code with proper indentation for regular menu', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('File', 'file', [], false);

    // Regular menus should have normal indentation (8 spaces)
    // Code starts with a newline, so we check for the indentation after it
    expect($code)->toContain("\n        Menu::new()");
});

it('generates event listener code', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateEventListenerCode('file.open', 'FileMenuListener');

    expect($code)->toBeString()
        ->and($code)->toContain('namespace App\Listeners;')
        ->and($code)->toContain('use Native\Laravel\Events\Menu\MenuItemClicked;')
        ->and($code)->toContain('class FileMenuListener')
        ->and($code)->toContain('public function handle(MenuItemClicked $event): void')
        ->and($code)->toContain("if (\$event->id === 'file.open')");
});

it('generates route code', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateRouteCode('file.open');

    expect($code)->toBeString()
        ->and($code)->toContain("Route::get('/file-open'")
        ->and($code)->toContain("[MenuController::class, 'fileOpen']")
        ->and($code)->toContain("->name('file.open')");
});

it('generates listener class name', function () {
    $generator = new MenuGenerator();

    expect($generator->getListenerClassName('File'))->toBe('FileMenuListener')
        ->and($generator->getListenerClassName('My Custom Menu'))->toBe('MyCustomMenuMenuListener')
        ->and($generator->getListenerClassName('app-menu'))->toBe('AppMenuMenuListener');
});

it('generates listener registration code', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateListenerRegistration('FileMenuListener');

    expect($code)->toBeString()
        ->and($code)->toContain('MenuItemClicked::class')
        ->and($code)->toContain('FileMenuListener::class');
});

it('handles empty custom menu', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('Empty', 'custom', [], false);

    expect($code)->toBeString()
        ->and($code)->toContain("Menu::new()")
        ->and($code)->toContain("->label('Empty')")
        ->and($code)->toContain("->register();");
});

it('escapes single quotes in menu labels', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode("Pete's Menu", 'custom', [], false);

    expect($code)->toBeString()
        ->and($code)->toContain("->label('Pete\\'s Menu')")
        ->and($code)->not->toContain("->label('Pete's Menu')");
});

it('escapes single quotes in menu items', function () {
    $generator = new MenuGenerator();
    $items = [
        ['label' => "It's Working", 'action' => 'test.action'],
    ];
    $code = $generator->generateMenuCode('Test', 'custom', $items, false);

    expect($code)->toBeString()
        ->and($code)->toContain("->item('It\\'s Working'")
        ->and($code)->not->toContain("->item('It's Working'");
});

it('escapes backslashes in menu labels', function () {
    $generator = new MenuGenerator();
    $code = $generator->generateMenuCode('Test\\Menu', 'custom', [], false);

    expect($code)->toBeString()
        ->and($code)->toContain("->label('Test\\\\Menu')");
});
