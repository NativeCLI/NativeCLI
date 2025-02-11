<?php

define('TESTS_ROOT', __DIR__);
define('TESTS_DATA_DIR', TESTS_ROOT . '/data');
define('ROOT_DIR', dirname(TESTS_ROOT));
define('SRC_DIR', ROOT_DIR . '/src');

// If defined that we're in a GH action, create a composer file
if (getenv('GITHUB_ACTIONS')) {
    \Symfony\Component\Process\Process::fromShellCommandline('composer global require nativecli/nativecli --no-interaction')->mustRun();
}
