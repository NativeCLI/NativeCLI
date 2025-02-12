<?php

namespace NativeCLI\Configuration;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final readonly class CompiledConfiguration
{
    private array $config;

    public function __construct(array $global, array $local)
    {
        $this->config = array_merge($global, $local);
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return Arr::get($this->config, $key, $default);
    }

    public function all(): Collection
    {
        return collect($this->config);
    }
}
