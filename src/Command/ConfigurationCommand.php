<?php

namespace NativeCLI\Command;

use NativeCLI\Configuration;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'config',
    description: 'Configure the NativePHP CLI tool'
)]
class ConfigurationCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument(
            'key',
            InputArgument::OPTIONAL,
            'The configuration key',
        )
            ->addArgument(
                'value',
                InputArgument::OPTIONAL,
                'The configuration value',
            );

        $this->addOption(
            'global',
            'g',
            InputOption::VALUE_NONE,
            'Set the configuration globally'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');
        $global = $input->getOption('global') ? 'global' : 'local';

        /** @var Configuration $config */
        $config = Configuration::$global();

        if ($key === null) {
            $output->writeln(json_encode($config->get(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        } elseif ($key === 'init') {
            $config->init();

            $output->writeln('Configuration file created.');

            return Command::SUCCESS;
        }

        if ($value === null) {
            $output->writeln($config->get($key) ?? 'Configuration key not found.');

            return Command::SUCCESS;
        }

        $config->set($key, $value)->save();

        return Command::SUCCESS;
    }
}
