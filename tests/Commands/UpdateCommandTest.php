<?php

namespace NativeCLI\Tests\Commands;

use NativeCLI\Application;
use NativeCLI\Command\CheckNativePHPUpdatesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Throwable;

class UpdateCommandTest extends TestCase
{
    /**
     * @throws Throwable
     * @TODO Break out the updater to be more testable.
     */
    public function testCheckUpdateCommandCanExecute()
    {
        $commandMock = $this->getMockBuilder(CheckNativePHPUpdatesCommand::class)
            ->setConstructorArgs(['check-update'])
            ->onlyMethods(['execute'])
            ->getMock();

        $commandMock->method('execute')->willReturn(Command::SUCCESS);

        $app = new Application();
        $app->add($commandMock);

        $output = new NullOutput();
        $command = $app->doRun(new ArrayInput(['command' => 'check-update']), $output);

        $this->assertEquals(Command::SUCCESS, $command);
    }
}
