<?php

namespace NativeCLI;

use Illuminate\Console\Command;
use NativeCLI\Command\CheckNativePHPUpdatesCommand;
use NativeCLI\Command\ClearCacheCommand;
use NativeCLI\Command\NewCommand;
use NativeCLI\Command\SelfUpdateCommand;
use NativeCLI\Command\UpdateNativePHPCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

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

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        $input ??= new ArgvInput();
        $output ??= new ConsoleOutput();

        if ($input->getFirstArgument() != 'self-update') {
            $tempInput = new ArgvInput([
                'self-update',
                '--check',
                '--format=json',
            ]);

            $tempOutput = new BufferedOutput();
            $this->find('self-update')->run($tempInput, $tempOutput);

            $jsonOutput = json_decode($tempOutput->fetch(), true);

            if (
                json_last_error() === JSON_ERROR_NONE
                && isset($jsonOutput['update_available'])
                && $jsonOutput['update_available'] === true
            ) {
                $output->writeln(
                    '<info>There is a new version of NativePHP available. Run `nativephp self-update` to update.</info>'
                );
            }
        }

        return parent::run($input, $output);
    }

    public function getCommands(): array
    {
        return [
            new NewCommand(),
            new UpdateNativePHPCommand(),
            new CheckNativePHPUpdatesCommand(),
            new ClearCacheCommand(),
            new SelfUpdateCommand(),
        ];
    }
}
