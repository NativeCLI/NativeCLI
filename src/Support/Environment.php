<?php

namespace NativeCLI\Support;

class Environment
{
    public static function currentDirectory()
    {
        $candidates = [
            getenv('PWD') ?: null,
            $_SERVER['PWD'] ?? null,
            getcwd() !== false ? getcwd() : null,
            isset($_SERVER['SCRIPT_FILENAME']) ? dirname($_SERVER['SCRIPT_FILENAME']) : null,
            __DIR__,
            '.',
        ];

        foreach ($candidates as $cand) {
            if (!$cand) {
                continue;
            }
            $real = realpath($cand);
            if ($real && is_dir($real)) {
                return $real;
            }
        }

        // Last resort: return something non-empty
        return getcwd() ?: (getenv('PWD') ?: '/');
    }
}
