<?php

namespace NativeCLI;

use NativeCLI\Command\CheckNativePHPUpdatesCommand;
use NativeCLI\Command\ClearCacheCommand;
use NativeCLI\Command\ConfigurationCommand;
use NativeCLI\Command\NewCommand;
use NativeCLI\Command\SelfUpdateCommand;
use NativeCLI\Command\UpdateNativePHPCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;

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

    /**
     * @throws \Exception
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        try {
            $input ??= new ArgvInput();
            $output ??= new ConsoleOutput();

            $config = Configuration::compiled();

            if ($input->getFirstArgument() != 'self-update' && $config->get('updates.check')) {
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
                        '<info>There is a new version of NativePHP available. Run `nativecli self-update` to update.</info>'
                    );

                    if ($config->get('updates.auto')) {
                        $output->writeln('<info>Updating NativeCLI...</info>');
                        $updateCode = $this->find('self-update')->run(new ArgvInput(['self-update']), new NullOutput());
                        if ($updateCode === 0) {
                            $output->writeln('<info>NativePHP has been updated.</info>');

                            $process = (new Process([
                                'sh $([ -f sail ] && echo sail || echo vendor/bin/sail)',
                                ...$input->getRawTokens()
                            ]))->mustRun(function ($type, $buffer) use ($output) {
                                if ($type === Process::ERR) {
                                    $output->write('<error>' . $buffer . '</error>');
                                } else {
                                    $output->write($buffer);
                                }
                            });

                            return $process->getExitCode();
                        }
                    }
                }
            }
        } catch (Throwable) {
            // Continue silently. Allow the command requested to run.
        }

        return parent::run($input, $output);
    }

    public function getCommands(): array
    {
        return [
            new CheckNativePHPUpdatesCommand(),
            new ClearCacheCommand(),
            new ConfigurationCommand(),
            new NewCommand(),
            new SelfUpdateCommand(),
            new UpdateNativePHPCommand(),
        ];
    }
}
