<?php

$pharFile = 'nativecli.phar';

// clean up
if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile);

// start buffering. Mandatory to modify stub to add shebang
$phar->startBuffering();

$phar->buildFromDirectory(__DIR__);

// Create default stub from entry point
$defaultStub = $phar->createDefaultStub('bin/nativecli');

// Add the rest of the stub
$stub = "#!/usr/bin/env php \n" . $defaultStub;

// Add the stub
$phar->setStub($stub);

$phar->stopBuffering();

// Make the file executable
chmod($pharFile, 0770);

echo "$pharFile successfully created" . PHP_EOL;
