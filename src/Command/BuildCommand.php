<?php

namespace NativeCLI\Command;

use NativeCLI\Exception\CommandFailed;
use NativeCLI\Support\ProcessFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand(
    name: 'build',
    description: 'Build and run a NativePHP application',
)]
class BuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'build',
                null,
                InputOption::VALUE_OPTIONAL,
                'Build type (debug or release)',
                'debug'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->isLaravelProject()) {
            $output->writeln('<error>This command must be run from a Laravel project directory.</error>');

            return Command::FAILURE;
        }

        $build = $this->normalizeBuildOption((string) $input->getOption('build'));
        if ($build === null) {
            $output->writeln('<error>Invalid build type. Use "debug" or "release".</error>');

            return Command::FAILURE;
        }

        try {
            $php = trim(ProcessFactory::shell('which php', false)->mustRun()->getOutput());
            if ($php === '') {
                throw new CommandFailed('Unable to locate PHP binary.');
            }

            $output->writeln('<info>Building NativePHP application...</info>');

            $process = ProcessFactory::make($this->buildNativeRunCommand($php, $build));
            $process->mustRun(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        } catch (CommandFailed $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function buildNativeRunCommand(string $php, string $build): array
    {
        return [$php, 'artisan', 'native:run', '--build=' . $build];
    }

    protected function normalizeBuildOption(string $build): ?string
    {
        $build = strtolower(trim($build));

        return in_array($build, ['debug', 'release'], true) ? $build : null;
    }

    private function isLaravelProject(): bool
    {
        return file_exists(getcwd() . '/artisan') && file_exists(getcwd() . '/composer.json');
    }
}
