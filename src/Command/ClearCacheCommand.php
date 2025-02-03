<?php

namespace NativeCLI\Command;

use NativeCLI\Cache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'cache:clear',
    description: 'Clear the application cache'
)]
class ClearCacheCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'cache',
            'c',
            InputOption::VALUE_OPTIONAL,
            'The cache to clear. Default: all',
            'all'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cache = new Cache();
        $cacheKey = strtolower(trim($input->getOption('cache')));

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $question = new ConfirmationQuestion(
            $cacheKey === 'all'
                ? 'Are you sure you want to clear all caches?'
                : 'Are you sure you want to clear the ' . $cacheKey . ' cache? [Y/n]',
            true
        );

        if (!$questionHelper->ask($input, $output, $question)) {
            $output->writeln('<error>Cache clear cancelled by user.</error>');

            return Command::FAILURE;
        }

        if ($cacheKey === 'all') {
            $output->writeln('Clearing all caches');

            $caches = $cache->getAllAvailableCaches();

            if ($caches->isEmpty()) {
                $output->writeln('<info>No caches to clear</info>');

                return Command::SUCCESS;
            }

            $caches->each(function ($cacheName) use ($cache, $output) {
                $output->writeln("Clearing cache: $cacheName");

                if ($cache->removeCache($cacheName)) {
                    $output->writeln("<info>Cache $cacheName cleared!</info>");
                } else {
                    $output->writeln("<error>Failed to clear $cacheName cache</error>");
                }
            });
        } else {
            if (!$cache->cacheExists($cacheKey)) {
                $output->writeln("<error>Cache $cacheKey does not exist</error>");

                return Command::FAILURE;
            }

            $output->writeln("Clearing $cacheKey cache");

            if ($cache->removeCache($cacheKey)) {
                $output->writeln("<info>Cache $cacheKey cleared!</info>");
            } else {
                $output->writeln("<error>Failed to clear $cacheKey cache</error>");
            }
        }

        return Command::SUCCESS;
    }
}