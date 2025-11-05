<?php

namespace NativeCLI\Command\Make;

use Illuminate\Filesystem\Filesystem;
use NativeCLI\Composer;
use NativeCLI\Generators\CodeModifier;
use NativeCLI\Generators\MenuGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:menu',
    description: 'Generate native menu structures with common patterns',
)]
class MakeMenuCommand extends Command
{
    protected MenuGenerator $menuGenerator;

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Menu name (e.g., "File", "Edit")')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Menu type (app|file|edit|view|window|custom)')
            ->addOption('menubar', 'm', InputOption::VALUE_NONE, 'Generate menu bar specific code')
            ->addOption('no-listener', null, InputOption::VALUE_NONE, 'Skip event listener generation')
            ->setHelp(
                <<<'HELP'
The <info>make:menu</info> command scaffolds native menu structures with common patterns.

<comment>Usage:</comment>
  <info>nativecli make:menu</info>                    # Interactive mode
  <info>nativecli make:menu File --type=file</info>   # Generate File menu
  <info>nativecli make:menu App --type=app --menubar</info>  # Menu bar app menu

<comment>Available menu types:</comment>
  <info>app</info>     - Standard app menu (About, Preferences, Quit)
  <info>file</info>    - File operations (New, Open, Save, Close)
  <info>edit</info>    - Edit operations (Undo, Redo, Cut, Copy, Paste)
  <info>view</info>    - View controls (Zoom, Fullscreen, Dev Tools)
  <info>window</info>  - Window management (Minimize, Close, Bring All to Front)
  <info>custom</info>  - Empty menu with interactive item builder

The command will:
- Generate menu code in NativeAppServiceProvider
- Create event listeners for menu actions
- Add necessary use statements
- Backup provider file before modification

<comment>Examples:</comment>
  # Interactive menu builder
  <info>nativecli make:menu</info>

  # Generate standard Edit menu
  <info>nativecli make:menu Edit --type=edit</info>

  # Generate menu bar app menu
  <info>nativecli make:menu MyApp --type=app --menubar</info>

  # Generate custom menu (interactive)
  <info>nativecli make:menu Custom --type=custom</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->menuGenerator = new MenuGenerator;
        $io = new SymfonyStyle($input, $output);

        // Check if in Laravel project
        if (! $this->isInLaravelProject()) {
            $io->error('This command must be run from within a Laravel project.');

            return Command::FAILURE;
        }

        // Check if NativePHP is installed
        if (! $this->isNativePHPInstalled()) {
            $io->error('NativePHP does not appear to be installed. Run "composer require nativephp/desktop" first.');

            return Command::FAILURE;
        }

        $io->title('NativePHP Menu Generator');

        // Get menu name
        $menuName = $input->getArgument('name');
        if (! $menuName) {
            $question = new Question('Menu name (e.g., "File", "Edit", "My Menu"): ');
            $question->setValidator(function ($answer) {
                if (empty(trim($answer))) {
                    throw new \RuntimeException('Menu name cannot be empty');
                }

                return $answer;
            });
            $menuName = $io->askQuestion($question);
        }

        // Get menu type
        $menuType = $input->getOption('type');
        if (! $menuType) {
            $types = $this->menuGenerator->getMenuTypes();
            $choices = [];
            foreach ($types as $type) {
                $description = $this->menuGenerator->getMenuTypeDescription($type);
                $choices[$type] = "{$type} - {$description}";
            }

            $question = new ChoiceQuestion('Select menu type:', $choices);
            $selected = $io->askQuestion($question);
            $menuType = explode(' - ', $selected)[0];
        }

        // Validate menu type
        if (! in_array($menuType, $this->menuGenerator->getMenuTypes())) {
            $io->error("Invalid menu type: {$menuType}");

            return Command::FAILURE;
        }

        $isMenuBar = $input->getOption('menubar');
        $skipListener = $input->getOption('no-listener');

        // For custom type, build menu items interactively
        $menuItems = [];
        if ($menuType === 'custom') {
            $io->section('Building Custom Menu');
            $menuItems = $this->buildCustomMenu($io);

            if (empty($menuItems)) {
                $io->warning('No menu items added. Menu will be empty.');
            }
        }

        // Generate menu code
        $io->section('Generating Menu Code');

        $menuCode = $this->menuGenerator->generateMenuCode($menuName, $menuType, $menuItems, $isMenuBar);

        // Find NativeAppServiceProvider
        $providerPath = $this->findNativeAppServiceProvider();
        if (! $providerPath) {
            $io->error('Could not find NativeAppServiceProvider. Make sure NativePHP is properly installed.');

            return Command::FAILURE;
        }

        try {
            $codeModifier = new CodeModifier($providerPath);

            // Backup the file
            $io->text('Creating backup of NativeAppServiceProvider...');
            $codeModifier->backup();

            // Check if menu already exists
            if ($codeModifier->methodContains('boot', "->label('{$menuName}')")) {
                $question = new ConfirmationQuestion(
                    "A menu with label '{$menuName}' already exists. Do you want to add it anyway? [y/N] ",
                    false
                );

                if (! $io->askQuestion($question)) {
                    $io->info('Menu generation cancelled.');

                    return Command::SUCCESS;
                }
            }

            // Add use statement for Menu facade
            $io->text('Adding use statements...');
            $codeModifier->addUseStatement('Native\Laravel\Facades\Menu');

            // Insert menu code into boot method
            $io->text('Inserting menu code into boot() method...');
            $codeModifier->insertIntoMethod('boot', $menuCode, 'end');

            // Save the file
            $codeModifier->save();

            $io->success("Menu code added to {$providerPath}");

            // Generate event listener if not skipped
            if (! $skipListener && $menuType !== 'custom') {
                $this->generateEventListener($io, $menuName, $menuType);
            }

            // Display next steps
            $this->displayNextSteps($io, $menuName, $skipListener);
        } catch (\Exception $e) {
            $io->error("Failed to modify provider: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function buildCustomMenu(SymfonyStyle $io): array
    {
        $items = [];
        $continue = true;

        while ($continue) {
            $io->writeln('');
            $typeQuestion = new ChoiceQuestion(
                'Add menu item:',
                ['Item', 'Separator', 'Done']
            );
            $type = $io->askQuestion($typeQuestion);

            if ($type === 'Done') {
                break;
            }

            if ($type === 'Separator') {
                $items[] = ['separator' => true];
                $io->success('Separator added');

                continue;
            }

            // Add regular item
            $label = $io->ask('Item label:', null, function ($answer) {
                if (empty(trim($answer))) {
                    throw new \RuntimeException('Label cannot be empty');
                }

                return $answer;
            });

            $action = $io->ask('Item action ID (e.g., "file.open"):', null, function ($answer) {
                if (empty(trim($answer))) {
                    throw new \RuntimeException('Action cannot be empty');
                }

                return $answer;
            });

            $shortcut = $io->ask('Keyboard shortcut (optional, e.g., "CmdOrCtrl+N"):', null);

            $item = [
                'label' => $label,
                'action' => $action,
            ];

            if ($shortcut) {
                $item['shortcut'] = $shortcut;
            }

            $items[] = $item;
            $io->success("Added: {$label}");
        }

        return $items;
    }

    protected function generateEventListener(SymfonyStyle $io, string $menuName, string $menuType): void
    {
        $io->section('Generating Event Listener');

        $className = $this->menuGenerator->getListenerClassName($menuName);
        $listenerPath = getcwd()."/app/Listeners/{$className}.php";

        if (file_exists($listenerPath)) {
            $question = new ConfirmationQuestion(
                "Listener {$className} already exists. Overwrite? [y/N] ",
                false
            );

            if (! $io->askQuestion($question)) {
                $io->info('Listener generation skipped.');

                return;
            }
        }

        // Create Listeners directory if it doesn't exist
        $listenersDir = getcwd().'/app/Listeners';
        if (! is_dir($listenersDir)) {
            mkdir($listenersDir, 0755, true);
        }

        // Generate a generic listener (user will need to customize)
        $listenerCode = $this->menuGenerator->generateEventListenerCode($menuName, $className);
        file_put_contents($listenerPath, $listenerCode);

        $io->success("Event listener created: app/Listeners/{$className}.php");
        $io->info('Remember to register the listener in EventServiceProvider:');
        $io->text($this->menuGenerator->generateListenerRegistration($className));
    }

    protected function displayNextSteps(SymfonyStyle $io, string $menuName, bool $skipListener): void
    {
        $io->section('Next Steps');

        $io->listing([
            'Review the generated menu code in NativeAppServiceProvider::boot()',
            $skipListener ? 'Create event listeners for menu actions if needed' : 'Register the event listener in app/Providers/EventServiceProvider.php',
            'Implement the action handlers in your listener class',
            'Test the menu in your application: php artisan native:run',
        ]);

        $io->note([
            'A backup of your NativeAppServiceProvider has been created.',
            'You can restore it if needed: NativeAppServiceProvider.php.backup',
        ]);
    }

    protected function isInLaravelProject(): bool
    {
        return file_exists(getcwd().'/artisan') && file_exists(getcwd().'/composer.json');
    }

    protected function isNativePHPInstalled(): bool
    {
        try {
            $composer = new Composer(new Filesystem, getcwd());

            return $composer->packageExistsInComposerFile('nativephp/desktop')
                || $composer->packageExistsInComposerFile('nativephp/mobile');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function findNativeAppServiceProvider(): ?string
    {
        $path = getcwd().'/app/Providers/NativeAppServiceProvider.php';

        return file_exists($path) ? $path : null;
    }
}
