<?php

namespace NativeCLI\Support;

use Symfony\Component\Process\Process;

final class ProcessFactory
{
    public static function make(
        array $command,
        bool $tty = true,
        ?string $cwd = null,
        ?array $env = null,
        mixed $input = null
    ): Process {
        $process = new Process($command, $cwd, $env, $input, timeout: null);

        return self::configure($process, $tty);
    }

    public static function shell(
        string $command,
        bool $tty = true,
        ?string $cwd = null,
        ?array $env = null,
        mixed $input = null
    ): Process {
        $process = Process::fromShellCommandline($command, $cwd, $env, $input, timeout: null);

        return self::configure($process, $tty);
    }

    private static function configure(Process $process, bool $tty): Process
    {
        return $process
            ->setTimeout(null)
            ->setIdleTimeout(null)
            ->setTty($tty && Process::isTtySupported());
    }
}
