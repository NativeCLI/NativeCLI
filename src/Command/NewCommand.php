<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Exception\CommandFailed;
use NativeCLI\NativePHP;
use NativeCLI\Services\RepositoryManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;

#[AsCommand(
    name: 'new',
    description: 'Create a new Laravel project with NativePHP',
)]
class NewCommand extends Command
{
    private OutputInterface $output;

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
            ->addOption('git', null, InputOption::VALUE_NONE, 'Initialize a Git repository')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'The branch that should be created for a new repository', $this->defaultBranch())
            ->addOption('github', null, InputOption::VALUE_OPTIONAL, 'Create a new repository on GitHub', false)
            ->addOption('organization', null, InputOption::VALUE_REQUIRED, 'The GitHub organization to create the new repository for')
            ->addOption('database', null, InputOption::VALUE_REQUIRED, 'The database driver your application will use')
            ->addOption('stack', null, InputOption::VALUE_OPTIONAL, 'The Breeze / Jetstream stack that should be installed')
            ->addOption('breeze', null, InputOption::VALUE_NONE, 'Installs the Laravel Breeze scaffolding')
            ->addOption('jet', null, InputOption::VALUE_NONE, 'Installs the Laravel Jetstream scaffolding')
            ->addOption('dark', null, InputOption::VALUE_NONE, 'Indicate whether Breeze or Jetstream should be scaffolded with dark mode support')
            ->addOption('typescript', null, InputOption::VALUE_NONE, 'Indicate whether Breeze should be scaffolded with TypeScript support')
            ->addOption('eslint', null, InputOption::VALUE_NONE, 'Indicate whether Breeze should be scaffolded with ESLint and Prettier support')
            ->addOption('ssr', null, InputOption::VALUE_NONE, 'Indicate whether Breeze or Jetstream should be scaffolded with Inertia SSR support')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Indicates whether Jetstream should be scaffolded with API support')
            ->addOption('teams', null, InputOption::VALUE_NONE, 'Indicates whether Jetstream should be scaffolded with team support')
            ->addOption('verification', null, InputOption::VALUE_NONE, 'Indicates whether Jetstream should be scaffolded with email verification support')
            ->addOption('pest', null, InputOption::VALUE_NONE, 'Installs the Pest testing framework')
            ->addOption('phpunit', null, InputOption::VALUE_NONE, 'Installs the PHPUnit testing framework')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists')
            ->addOption('ios', null, InputOption::VALUE_NONE, 'Install NativePHP for iOS instead of Desktop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $cwd = getcwd();
        $filePath = $cwd . '/' . $input->getArgument('name');
        // @phpstan-ignore method.notFound (Relates to getRawTokens only available from ArgvInput.)
        $tokens = $input->getRawTokens(true);

        if (($key = array_search('--ios', $tokens)) !== false) {
            unset($tokens[$key]);
        }

        try {
            $output->writeln('Creating a new NativePHP project...');

            $process = new Process(['laravel', 'new', ...$tokens]);
            $process->setTty(Process::isTtySupported())
                ->mustRun(function ($type, $buffer) {
                    $this->output->write($buffer);
                });

            $process->isSuccessful()
                ? $output->writeln('<info>Laravel project created successfully.</info>')
                : throw new CommandFailed('NativePHP project creation failed.');

            chdir($input->getArgument('name'));

            $composer = new Composer(new Filesystem(), $filePath);

            if (!$input->getOption('ios')) {
                $composer->requirePackages(
                    NativePHP::getPackagesForComposer(),
                    false,
                    $output
                );
            } else {
                $repoMan = new RepositoryManager($composer);
                $repoMan->addRepository('composer', 'https://nativephp-ios.composer.sh');
                $composer->requirePackages(
                    ['nativephp/ios'],
                    false,
                    $output
                );
            }

            // Locate PHP & remove new lines
            $php = trim(Process::fromShellCommandline('which php')->mustRun()->getOutput());

            // Install NativePHP
            $nativePhpInstall = new Process([$php, 'artisan', 'native:install', '--no-interaction']);
            $nativePhpInstall->setTty(Process::isTtySupported())
                ->mustRun(function ($type, $buffer) {
                    $this->output->write($buffer);
                });

            $nativePhpInstall->isSuccessful()
                ? $output->writeln('<info>🚀 NativePHP installed successfully. Go forth and make great apps!</info>')
                : throw new CommandFailed('NativePHP installation failed.');
        } catch (CommandFailed $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Return the local machine's default Git branch if set or default to `main`.
     *
     * @return string
     */
    protected function defaultBranch(): string
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }
}
