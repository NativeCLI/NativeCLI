<?php

use NativeCLI\Generators\CodeModifier;

beforeEach(function () {
    $this->testFile = sys_get_temp_dir() . '/test_provider_' . uniqid() . '.php';
    $this->backupFile = $this->testFile . '.backup';
});

afterEach(function () {
    if (file_exists($this->testFile)) {
        unlink($this->testFile);
    }
    if (file_exists($this->backupFile)) {
        unlink($this->backupFile);
    }
});

it('throws exception for non-existent file', function () {
    expect(fn () => new CodeModifier('/non/existent/file.php'))
        ->toThrow(InvalidArgumentException::class, 'File not found');
});

it('creates backup of file', function () {
    file_put_contents($this->testFile, '<?php echo "test";');

    $modifier = new CodeModifier($this->testFile);
    $modifier->backup();

    expect($this->backupFile)->toBeFile()
        ->and(file_get_contents($this->backupFile))->toBe('<?php echo "test";');
});

it('detects if method exists', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
    public function boot(): void
    {
        // Method content
    }
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    expect($modifier->hasMethod('boot'))->toBeTrue()
        ->and($modifier->hasMethod('register'))->toBeFalse();
});

it('detects if use statement exists', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Laravel\Facades\Menu;

class TestProvider extends ServiceProvider
{
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    expect($modifier->hasUseStatement('Illuminate\Support\ServiceProvider'))->toBeTrue()
        ->and($modifier->hasUseStatement('Native\Laravel\Facades\Menu'))->toBeTrue()
        ->and($modifier->hasUseStatement('Some\Other\Class'))->toBeFalse();
});

it('adds use statement after last existing use', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TestProvider extends ServiceProvider
{
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    $modifier->addUseStatement('Native\Laravel\Facades\Menu');

    expect($modifier->getContent())
        ->toContain('use Illuminate\Support\ServiceProvider;')
        ->toContain('use Native\Laravel\Facades\Menu;');
});

it('adds use statement after namespace if no other uses', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    $modifier->addUseStatement('Native\Laravel\Facades\Menu');

    expect($modifier->getContent())
        ->toContain('namespace App\Providers;')
        ->toContain('use Native\Laravel\Facades\Menu;');
});

it('does not add duplicate use statement', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

use Native\Laravel\Facades\Menu;

class TestProvider
{
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    $modifier->addUseStatement('Native\Laravel\Facades\Menu');

    $occurences = substr_count($modifier->getContent(), 'use Native\Laravel\Facades\Menu;');
    expect($occurences)->toBe(1);
});

it('inserts code at end of method', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
    public function boot(): void
    {
        // Existing code
    }
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    $modifier->insertIntoMethod('boot', '        $this->newCode();', 'end');

    expect($modifier->getContent())
        ->toContain('// Existing code')
        ->toContain('$this->newCode();');
});

it('inserts code at start of method', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
    public function boot(): void
    {
        // Existing code
    }
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    $modifier->insertIntoMethod('boot', '        $this->newCode();', 'start');

    $contentAfter = $modifier->getContent();
    $newCodePos = strpos($contentAfter, '$this->newCode();');
    $existingCodePos = strpos($contentAfter, '// Existing code');

    expect($newCodePos)->toBeLessThan($existingCodePos);
});

it('throws exception when inserting into non-existent method', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
    public function boot(): void
    {
    }
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    expect(fn () => $modifier->insertIntoMethod('register', 'code'))
        ->toThrow(RuntimeException::class, 'Method register not found');
});

it('detects if method contains string', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
    public function boot(): void
    {
        Menu::new()->label('File');
    }

    public function register(): void
    {
        // Empty
    }
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    expect($modifier->methodContains('boot', 'Menu::new()'))->toBeTrue()
        ->and($modifier->methodContains('boot', "label('File')"))->toBeTrue()
        ->and($modifier->methodContains('register', 'Menu::new()'))->toBeFalse()
        ->and($modifier->methodContains('nonExistent', 'anything'))->toBeFalse();
});

it('saves modified content to file', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    $modifier->addUseStatement('Native\Laravel\Facades\Menu');
    $modifier->save();

    $savedContent = file_get_contents($this->testFile);
    expect($savedContent)->toContain('use Native\Laravel\Facades\Menu;');
});

it('handles methods with parameters', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
    public function boot(string $param1, array $param2): void
    {
        // Content
    }
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    expect($modifier->hasMethod('boot'))->toBeTrue();

    $modifier->insertIntoMethod('boot', '        $this->test();', 'end');

    expect($modifier->getContent())->toContain('$this->test();');
});

it('handles nested braces in method', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

class TestProvider
{
    public function boot(): void
    {
        if (true) {
            $array = ['key' => 'value'];
        }
    }
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    $modifier->insertIntoMethod('boot', '        $this->afterNestedBraces();', 'end');

    expect($modifier->getContent())
        ->toContain("'key' => 'value'")
        ->toContain('$this->afterNestedBraces();');
});

it('adds use statements in alphabetical order', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TestProvider extends ServiceProvider
{
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    // Add statements that should be sorted alphabetically
    $modifier->addUseStatement('Native\Laravel\Facades\Menu');
    $modifier->addUseStatement('App\Services\SomeService');
    $modifier->addUseStatement('Native\Desktop\Facades\Window');

    $content = $modifier->getContent();

    // Check that use statements appear in alphabetical order
    // Note: Native\Desktop comes before Native\Laravel (D before L)
    $appPos = strpos($content, 'use App\Services\SomeService;');
    $illuminatePos = strpos($content, 'use Illuminate\Support\ServiceProvider;');
    $windowPos = strpos($content, 'use Native\Desktop\Facades\Window;');
    $menuPos = strpos($content, 'use Native\Laravel\Facades\Menu;');

    expect($appPos)->toBeLessThan($illuminatePos)
        ->and($illuminatePos)->toBeLessThan($windowPos)
        ->and($windowPos)->toBeLessThan($menuPos);
});

it('inserts use statement in correct alphabetical position', function () {
    $content = <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Laravel\Facades\Menu;

class TestProvider extends ServiceProvider
{
}
PHP;

    file_put_contents($this->testFile, $content);
    $modifier = new CodeModifier($this->testFile);

    // Add a statement that should go between the two existing ones
    // Native\Desktop should come before Native\Laravel alphabetically
    $modifier->addUseStatement('Native\Desktop\Facades\Window');

    $content = $modifier->getContent();

    // Window (Desktop) should come before Menu (Laravel) alphabetically
    $windowPos = strpos($content, 'use Native\Desktop\Facades\Window;');
    $menuPos = strpos($content, 'use Native\Laravel\Facades\Menu;');

    expect($windowPos)->toBeLessThan($menuPos);
});
