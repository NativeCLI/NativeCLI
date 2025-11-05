<?php

namespace NativeCLI\Generators;

class MenuGenerator
{
    protected array $menuTypes = [
        'app' => [
            'description' => 'Standard app menu (About, Preferences, Quit)',
            'items' => [
                ['label' => 'About {APP_NAME}', 'action' => 'about'],
                ['separator' => true],
                ['label' => 'Preferences', 'action' => 'preferences', 'shortcut' => 'CmdOrCtrl+,'],
                ['separator' => true],
                ['label' => 'Quit {APP_NAME}', 'action' => 'quit', 'shortcut' => 'CmdOrCtrl+Q'],
            ],
        ],
        'file' => [
            'description' => 'File operations (New, Open, Save, Close)',
            'items' => [
                ['label' => 'New', 'action' => 'file.new', 'shortcut' => 'CmdOrCtrl+N'],
                ['label' => 'Open', 'action' => 'file.open', 'shortcut' => 'CmdOrCtrl+O'],
                ['separator' => true],
                ['label' => 'Save', 'action' => 'file.save', 'shortcut' => 'CmdOrCtrl+S'],
                ['label' => 'Save As...', 'action' => 'file.save-as', 'shortcut' => 'CmdOrCtrl+Shift+S'],
                ['separator' => true],
                ['label' => 'Close', 'action' => 'file.close', 'shortcut' => 'CmdOrCtrl+W'],
            ],
        ],
        'edit' => [
            'description' => 'Edit operations (Undo, Redo, Cut, Copy, Paste)',
            'items' => [
                ['label' => 'Undo', 'action' => 'edit.undo', 'shortcut' => 'CmdOrCtrl+Z'],
                ['label' => 'Redo', 'action' => 'edit.redo', 'shortcut' => 'CmdOrCtrl+Shift+Z'],
                ['separator' => true],
                ['label' => 'Cut', 'action' => 'edit.cut', 'shortcut' => 'CmdOrCtrl+X'],
                ['label' => 'Copy', 'action' => 'edit.copy', 'shortcut' => 'CmdOrCtrl+C'],
                ['label' => 'Paste', 'action' => 'edit.paste', 'shortcut' => 'CmdOrCtrl+V'],
                ['separator' => true],
                ['label' => 'Select All', 'action' => 'edit.select-all', 'shortcut' => 'CmdOrCtrl+A'],
            ],
        ],
        'view' => [
            'description' => 'View controls (Zoom, Fullscreen, Dev Tools)',
            'items' => [
                ['label' => 'Reload', 'action' => 'view.reload', 'shortcut' => 'CmdOrCtrl+R'],
                ['label' => 'Force Reload', 'action' => 'view.force-reload', 'shortcut' => 'CmdOrCtrl+Shift+R'],
                ['separator' => true],
                ['label' => 'Actual Size', 'action' => 'view.reset-zoom', 'shortcut' => 'CmdOrCtrl+0'],
                ['label' => 'Zoom In', 'action' => 'view.zoom-in', 'shortcut' => 'CmdOrCtrl+Plus'],
                ['label' => 'Zoom Out', 'action' => 'view.zoom-out', 'shortcut' => 'CmdOrCtrl+-'],
                ['separator' => true],
                ['label' => 'Toggle Fullscreen', 'action' => 'view.fullscreen', 'shortcut' => 'F11'],
                ['separator' => true],
                ['label' => 'Toggle Developer Tools', 'action' => 'view.dev-tools', 'shortcut' => 'CmdOrCtrl+Shift+I'],
            ],
        ],
        'window' => [
            'description' => 'Window management (Minimize, Close, Bring All to Front)',
            'items' => [
                ['label' => 'Minimize', 'action' => 'window.minimize', 'shortcut' => 'CmdOrCtrl+M'],
                ['label' => 'Close', 'action' => 'window.close', 'shortcut' => 'CmdOrCtrl+W'],
                ['separator' => true],
                ['label' => 'Bring All to Front', 'action' => 'window.bring-all-to-front'],
            ],
        ],
        'custom' => [
            'description' => 'Empty menu with interactive item builder',
            'items' => [],
        ],
    ];

    public function getMenuTypes(): array
    {
        return array_keys($this->menuTypes);
    }

    public function getMenuTypeDescription(string $type): string
    {
        return $this->menuTypes[$type]['description'] ?? '';
    }

    public function generateMenuCode(string $label, string $type, array $items = [], bool $isMenuBar = false): string
    {
        $menuItems = empty($items) ? ($this->menuTypes[$type]['items'] ?? []) : $items;

        $code = $this->generateMenuBuilderCode($label, $menuItems, $isMenuBar);

        return $code;
    }

    protected function generateMenuBuilderCode(string $label, array $items, bool $isMenuBar): string
    {
        $indent = $isMenuBar ? '            ' : '        ';
        $code = "\n{$indent}Menu::new()\n";
        $code .= "{$indent}    ->label(" . $this->escapeString($label) . ")\n";

        foreach ($items as $item) {
            if (isset($item['separator']) && $item['separator']) {
                $code .= "{$indent}    ->separator()\n";
            } else {
                $label = $item['label'] ?? '';
                $action = $item['action'] ?? '';
                $shortcut = $item['shortcut'] ?? null;

                $code .= "{$indent}    ->item(" . $this->escapeString($label);

                if ($action) {
                    $code .= ', ' . $this->escapeString($action);
                }

                if ($shortcut) {
                    $code .= ', ' . $this->escapeString($shortcut);
                }

                $code .= ")\n";
            }
        }

        $code .= "{$indent}    ->register();";

        return $code;
    }

    protected function escapeString(string $string): string
    {
        return "'" . addslashes($string) . "'";
    }

    public function generateEventListenerCode(string $action, string $className): string
    {
        $template = <<<'PHP'
<?php

namespace App\Listeners;

use Native\Laravel\Events\Menu\MenuItemClicked;

class {CLASS_NAME}
{
    public function handle(MenuItemClicked $event): void
    {
        if ($event->id === '{ACTION}') {
            // Handle {ACTION} action
            // Add your implementation here
        }
    }
}
PHP;

        return str_replace(
            ['{CLASS_NAME}', '{ACTION}'],
            [$className, $action],
            $template
        );
    }

    public function generateRouteCode(string $action): string
    {
        $routeName = str_replace('.', '-', $action);
        $controllerMethod = $this->actionToMethodName($action);

        return "Route::get('/{$routeName}', [MenuController::class, '{$controllerMethod}'])->name('{$action}');";
    }

    protected function actionToMethodName(string $action): string
    {
        $parts = explode('.', $action);
        $method = '';

        foreach ($parts as $part) {
            $method .= ucfirst(str_replace('-', '', $part));
        }

        return lcfirst($method);
    }

    public function getListenerClassName(string $menuName): string
    {
        $name = str_replace([' ', '-'], '', ucwords($menuName, ' -'));

        return "{$name}MenuListener";
    }

    public function generateListenerRegistration(string $className): string
    {
        return "MenuItemClicked::class => [\n            {$className}::class,\n        ],";
    }
}
