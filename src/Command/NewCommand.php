<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Exception\CommandFailed;
use NativeCLI\Services\RepositoryManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

#[AsCommand(
    name: 'new',
    description: 'Create a new Laravel project with NativePHP',
)]
class NewCommand extends Command
{
    private OutputInterface $output;
    private InputInterface $input;

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
            ->addOption('react', null, InputOption::VALUE_NONE, 'Install the React Starter Kit')
            ->addOption('vue', null, InputOption::VALUE_NONE, 'Install the Vue Starter Kit')
            ->addOption('livewire', null, InputOption::VALUE_NONE, 'Install the Livewire Starter Kit')
            ->addOption('livewire-class-components', null, InputOption::VALUE_NONE, 'Generate stand-alone Livewire class components')
            ->addOption('workos', null, InputOption::VALUE_NONE, 'Use WorkOS for authentication')
            ->addOption('pest', null, InputOption::VALUE_NONE, 'Install the Pest testing framework')
            ->addOption('phpunit', null, InputOption::VALUE_NONE, 'Install the PHPUnit testing framework')
            ->addOption('npm', null, InputOption::VALUE_NONE, 'Install and build NPM dependencies')
            ->addOption('using', null, InputOption::VALUE_OPTIONAL, 'Install a custom starter kit from a community maintained package')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists')
            ->addOption('mobile', null, InputOption::VALUE_NONE, 'Install NativePHP for Mobile instead of Desktop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->input = $input;
        $cwd = getcwd();
        $filePath = $cwd . '/' . $input->getArgument('name');

        /**
         * @noinspection PhpPossiblePolymorphicInvocationInspection
         *
         * @phpstan-ignore method.notFound (Relates to getRawTokens only available from ArgvInput.)
         */
        $tokens = $input->getRawTokens(true);

        if (($key = array_search('--mobile', $tokens)) !== false) {
            unset($tokens[$key]);
        }

        try {
            $output->writeln('Creating a new NativePHP project...');

            $process = new Process(['laravel', 'new', ...$tokens]);
            $process->setTimeout(null)
                ->setTty(Process::isTtySupported())
                ->mustRun(function ($type, $buffer) {
                    $this->output->write($buffer);
                });

            $process->isSuccessful()
                ? $output->writeln('<info>Laravel project created successfully.</info>')
                : throw new CommandFailed('NativePHP project creation failed.');

            chdir($input->getArgument('name'));

            $composer = new Composer(new Filesystem(), $filePath);

            if (!$input->getOption('mobile')) {
                $composer->requirePackages(
                    packages: ['nativephp/desktop'],
                    output: $output,
                    tty: Process::isTtySupported()
                );
            } else {
                $repoMan = new RepositoryManager($composer);
                $repoMan->addRepository('composer', 'https://nativephp.composer.sh');
                $composer->requirePackages(
                    packages: ['nativephp/mobile'],
                    output: $output,
                    tty: Process::isTtySupported()
                );
            }

            // Locate PHP & remove new lines
            $php = trim(Process::fromShellCommandline('which php')->mustRun()->getOutput());

            // Install NativePHP
            $nativePhpInstall = new Process([$php, 'artisan', 'native:install', '--no-interaction']);
            $nativePhpInstall->setTimeout(null)
                ->setTty(Process::isTtySupported())
                ->mustRun(function ($type, $buffer) {
                    $this->output->write($buffer);
                });

            if (!$nativePhpInstall->isSuccessful()) {
                throw new CommandFailed('NativePHP installation failed.');
            }

            if ($input->getOption('mobile')) {
                $this->populateMobileEnv(
                    rtrim($filePath, '/') . '/.env',
                );
            }

            $output->writeln('<info>🚀 NativePHP installed successfully. Go forth and make great apps!</info>');

            if (confirm(
                'Would you like to start your new NativePHP project now?',
                true,
            )) {
                $output->writeln('<info>Starting your NativePHP application...</info>');

                $startProcess = new Process([$php, 'artisan', 'native:run']);
                $startProcess->setTimeout(null)
                    ->setTty(Process::isTtySupported())
                    ->mustRun(function ($type, $buffer) {
                        $this->output->write($buffer);
                    });
            }
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
     */
    protected function defaultBranch(): string
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }

    private function populateMobileEnv(string $filePath): void
    {
        $appId = 'com.example.app';

        if ($this->input->isInteractive()) {
            $appId = text(
                'What is the Application ID (e.g., com.example.app)?',
                default: '',
                validate: function (string $value) {
                    $value = trim($value);

                    if ($value === '') {
                        return 'An App ID is required.';
                    }

                    if (strlen($value) > 255) {
                        return 'The App ID must not exceed 255 characters.';
                    }

                    if (str_starts_with($value, 'com.nativephp')) {
                        return 'The App ID must not start with "com.nativephp". Please choose a different App ID.';
                    }

                    // Reverse-DNS style validation (e.g., com.example.app)
                    // - At least two dot-separated segments
                    // - Each segment starts with a letter
                    // - Only letters and digits allowed within segments (hyphens/underscores omitted for cross-platform compatibility)
                    // Example valid: com.example.app, io.mycompany.app
                    $pattern = '/^[A-Za-z][A-Za-z0-9]*(\.[A-Za-z][A-Za-z0-9]*)+$/';
                    if (!preg_match($pattern, $value)) {
                        return 'Invalid App ID. Use reverse-DNS format like com.example.app with at least two segments. Each segment must start with a letter and contain only letters or digits.';
                    }

                    // Finally, validate that the string does not contain `nativephp` in any segment.
                    $segments = explode('.', $value);
                    foreach ($segments as $segment) {
                        if (strtolower($segment) === 'nativephp') {
                            return 'The App ID must not contain the segment "nativephp". Please choose a different App ID.';
                        }
                    }

                    return null;
                },
                transform: fn ($value) => trim($value),
            );
        }

        $this->output->writeln("<info>Adding `NATIVEPHP_APP_ID=$appId to $filePath.</info>");

        $currentContents = file_get_contents($filePath);

        // Check for existing NATIVEPHP_APP_ID=* string and replace it if found
        if (preg_match('/^NATIVEPHP_APP_ID=.*$/m', $currentContents)) {
            $newContents = preg_replace('/^NATIVEPHP_APP_ID=.*$/m', "NATIVEPHP_APP_ID=$appId", $currentContents);
        } else {
            // Otherwise, append to the end of the file
            $newContents = rtrim($currentContents) . "\nNATIVEPHP_APP_ID=$appId\n";
        }

        // Check for existing `NATIVEPHP_APP_VERSION=*`. Do not replace if present.
        if (!preg_match('/^NATIVEPHP_APP_VERSION=.*$/m', $newContents)) {
            $newContents .= "NATIVEPHP_APP_VERSION=DEBUG\n";
        }

        $result = file_put_contents($filePath, $newContents);

        if ($result === false) {
            $this->output->writeln("<error>Failed to write NATIVEPHP_APP_ID to $filePath.</error>");
        }
    }
}
