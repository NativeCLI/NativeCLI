<?php

use NativeCLI\Support\ProcessFactory;

test('process factory disables timeouts for array commands', function () {
    $process = ProcessFactory::make(['echo', 'hi'], false);

    expect($process->getTimeout())->toBeNull()
        ->and($process->getIdleTimeout())->toBeNull();
});

test('process factory disables timeouts for shell commands', function () {
    $process = ProcessFactory::shell('echo hi', false);

    expect($process->getTimeout())->toBeNull()
        ->and($process->getIdleTimeout())->toBeNull();
});
