<?php

namespace NativeCLI;

use NativeCLI\Command\CheckNativePHPUpdatesCommand;
use NativeCLI\Command\ClearCacheCommand;
use NativeCLI\Command\ConfigurationCommand;
use NativeCLI\Command\InstallNativePHPMobileCommand;
use NativeCLI\Command\LogsCommand;
use NativeCLI\Command\NewCommand;
use NativeCLI\Command\PluginAddCommand;
use NativeCLI\Command\PluginListCommand;
use NativeCLI\Command\PluginPermissionsCommand;
use NativeCLI\Command\PluginProviderCommand;
use NativeCLI\Command\PluginRemoveCommand;
use NativeCLI\Command\SelfUpdateCommand;
use NativeCLI\Command\UpdateNativePHPCommand;
use NativeCLI\Support\ProcessFactory;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class Application extends \Symfony\Component\Console\Application
{
    public function __construct(private readonly ?string $filePath = null)
    {
        parent::__construct('NativePHP CLI Tool', Version::get());

        $this->addCommands($this->getCommands());
    }

    public static function create(?string $file = null): Application
    {
        return new Application($file);
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
                    if ($config->get('updates.auto')) {
                        $output->writeln('<info>Updating NativeCLI...</info>');
                        $tempInput = (new ArgvInput(['self-update']));
                        $tempInput->setInteractive(false);
                        $updateCode = $this->find('self-update')->run($tempInput, new NullOutput());
                        if ($updateCode === 0) {
                            $output->writeln('<info>NativePHP has been updated.</info>');

                            // To appease the QA/CI Bots, lets ensure that we have an ArgvInput
                            if ($input instanceof ArgvInput) {
                                ProcessFactory::shell($this->filePath . ' ' . implode(' ', $input->getRawTokens()), false)
                                    ->run(function ($type, $buffer) use ($output) {
                                        $output->write($buffer);
                                    });
                            } else {
                                $output->writeln('<error>Failed to re-run command. Please go ahead and try again.</error>');
                            }
                        }
                    } else {
                        $output->writeln(
                            '<info>There is a new version of NativePHP available. Run `nativecli self-update` to update.</info>'
                        );
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
            new InstallNativePHPMobileCommand(),
            new LogsCommand(),
            new NewCommand(),
            new PluginAddCommand(),
            new PluginListCommand(),
            new PluginPermissionsCommand(),
            new PluginProviderCommand(),
            new PluginRemoveCommand(),
            new SelfUpdateCommand(),
            new UpdateNativePHPCommand(),
        ];
    }
}
