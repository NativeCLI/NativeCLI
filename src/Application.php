<?php

namespace NativeCLI;

use NativeCLI\Command\CheckNativePHPUpdatesCommand;
use NativeCLI\Command\ClearCacheCommand;
use NativeCLI\Command\UpdateNativePHPCommand;

final class Application extends \Symfony\Component\Console\Application
{
    public function __construct()
    {
        parent::__construct('NativePHP CLI Tool', Version::get());

        $this->addCommands($this->getCommands());
    }

    public static function create(): Application
    {
        return new Application();
    }

    public function getCommands(): array
    {
       return [
           new Command\NewCommand(),
           new UpdateNativePHPCommand(),
           new CheckNativePHPUpdatesCommand(),
           new ClearCacheCommand(),
       ];
    }
}