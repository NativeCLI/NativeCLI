<?php

test('binary can be called', function () {
    $output = shell_exec('php ' . __DIR__ . '/../bin/nativecli --version');

    expect($output)->toContain('NativePHP CLI Tool');
});
