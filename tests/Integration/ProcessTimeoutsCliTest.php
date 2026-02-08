<?php

use Symfony\Component\Process\Process;

test('cli entrypoint uses no-timeout processes end-to-end', function () {
    $entry = tempnam(sys_get_temp_dir(), 'nativecli-entry-');
    if ($entry === false) {
        throw new RuntimeException('Failed to create temp entrypoint file.');
    }

    $autoload = ROOT_DIR . '/vendor/autoload.php';
    $entryContents = <<<PHP
<?php

require '{$autoload}';

use NativeCLI\\Application;
use NativeCLI\\Support\\ProcessFactory;
use Symfony\\Component\\Console\\Command\\Command;
use Symfony\\Component\\Console\\Input\\InputInterface;
use Symfony\\Component\\Console\\Output\\OutputInterface;

\$command = new class extends Command {
    public function __construct()
    {
        parent::__construct('process:check');
    }

    protected function execute(InputInterface \$input, OutputInterface \$output): int
    {
        \$process = ProcessFactory::make(['php', '-r', 'usleep(200000);']);
        \$output->writeln('timeout=' . var_export(\$process->getTimeout(), true));
        \$output->writeln('idle=' . var_export(\$process->getIdleTimeout(), true));
        \$process->run();

        return Command::SUCCESS;
    }
};

\$app = Application::create(__FILE__);
\$app->add(\$command);
\$app->setAutoExit(false);
exit(\$app->run());
PHP;

    try {
        file_put_contents($entry, $entryContents);

        $process = new Process(['php', $entry, 'process:check']);
        $process->setTimeout(10);
        $process->run();

        expect($process->isSuccessful())->toBeTrue();
        expect($process->getOutput())->toContain('timeout=NULL')
            ->and($process->getOutput())->toContain('idle=NULL');
    } finally {
        @unlink($entry);
    }
});
