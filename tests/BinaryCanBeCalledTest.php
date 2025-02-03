<?php

namespace NativeCLI\Tests;

use PHPUnit\Framework\TestCase;

class BinaryCanBeCalledTest extends TestCase
{
    public function testBinaryCanBeCalled(): void
    {
        $output = shell_exec('php ' . __DIR__ . '/../bin/nativecli --version');

        $this->assertStringContainsString('NativePHP CLI Tool', $output);
    }
}