<?php

namespace NativeCLI\Command;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use NativeCLI\Composer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;

#[AsCommand(
    name: 'update',
    description: 'Update the NativePHP version'
)]
class UpdateNativePHPCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'release',
            'r',
            InputOption::VALUE_OPTIONAL,
            'The version of NativePHP to update to',
            'latest'
        );
    }

    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Checking for updates...</info>', $this->getOutputVerbosityLevel($input));

        $updateInfo = $this->getUpdateInformation();
        if ($updateInfo->isEmpty() || !$updateInfo->where('isOutdated', true)->count()) {
            $output->writeln('<info>🚀 NativePHP is already up to date. 🚀</info>', $this->getOutputVerbosityLevel($input));
        } else {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'The following packages will be updated: ' . PHP_EOL
                    . $updateInfo->where('isOutdated', true)
                        ->map(function ($packageInfo, $key) {
                            return sprintf(
                                '%s - Old: <error>%s</error> | Latest: <info>%s</info>',
                                $key,
                                $packageInfo['current'],
                                $packageInfo['latest']
                            );
                        })
                        ->implode(PHP_EOL) . PHP_EOL
                    . '<info>Continue? (y/N)</info>',
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<info>Update cancelled.</info>', $this->getOutputVerbosityLevel($input));

                return Command::SUCCESS;
            }

            $output->writeln('<info>Updating NativePHP...</info>', $this->getOutputVerbosityLevel($input));

            $composer = new Composer(new Filesystem(), getcwd());
            $composerOutput = new BufferedOutput();
            $composer->requirePackages(
                $updateInfo->where('isOutdated', true)
                    ->map(function ($packageInfo, $key) {
                        return sprintf('%s:%s', $key, $packageInfo['latest']);
                    })
                    ->toArray(),
                false,
                $composerOutput
            );

            $output->writeln($composerOutput->fetch(), $this->getOutputVerbosityLevel($input));

            $output->writeln('<info>NativePHP has been updated.</info>', $this->getOutputVerbosityLevel($input));
            $output->writeln('<info>Go forth and make great apps 🚀</info>', $this->getOutputVerbosityLevel($input));
        }

        return Command::SUCCESS;
    }

    private function getOutputVerbosityLevel(InputInterface $input): int
    {
        return $input->getOption('no-interaction')
            ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL;
    }

    /**
     * @throws Throwable
     */
    private function getUpdateInformation(): Collection
    {
        $checkInput = new ArrayInput([
            'command' => 'check-update',
            '--format' => 'json',
        ]);

        $checkInput->setInteractive(false);

        $output = new BufferedOutput();

        $this->getApplication()->doRun($checkInput, $output);

        return collect(json_decode(trim($output->fetch()), true));
    }
}