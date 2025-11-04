<?php

use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

define('TESTS_ROOT', __DIR__);
define('TESTS_DATA_DIR', TESTS_ROOT . '/data');
define('ROOT_DIR', dirname(TESTS_ROOT));
define('SRC_DIR', ROOT_DIR . '/src');
define('TESTS_TEMP_DIR', TESTS_ROOT . '/tmp');

// If defined that we're in a GH action, create a composer file
if (getenv('GITHUB_ACTIONS')) {
    Process::fromShellCommandline('composer global require nativecli/nativecli --no-interaction')
        ->mustRun();
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Safely remove a directory and its contents for tests.
 * Uses Symfony Filesystem::remove() when available and falls back to a robust
 * recursive remover if that fails (permissions/immutable flags, etc.).
 *
 * @param string $dir
 * @return void
 */
function remove_test_dir(string $dir): void
{
    if (!file_exists($dir)) {
        return;
    }

    $fs = new Filesystem();
    try {
        $fs->remove($dir);
        return;
    } catch (\Exception $e) {
        // Fallback: robust recursive removal
    }

    $removeDir = function ($dir) use (&$removeDir) {
        if (!file_exists($dir)) {
            return;
        }

        if (is_file($dir) || is_link($dir)) {
            @chmod($dir, 0777);
            @unlink($dir);
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                @chmod($path, 0777);
                @rmdir($path);
            } else {
                @chmod($path, 0666);
                @unlink($path);
            }
        }

        @chmod($dir, 0777);
        @rmdir($dir);
    };

    $removeDir($dir);
}
