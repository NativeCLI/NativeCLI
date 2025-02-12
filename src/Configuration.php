<?php

namespace NativeCLI;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use NativeCLI\Configuration\CompiledConfiguration;

final class Configuration
{
    public const FILENAME = '.nativecli.json';

    private array $config;

    /**
     * @throws FileNotFoundException
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private ?string $workingPath = null,
    ) {
        if ($this->workingPath === null) {
            $this->workingPath = getcwd();
        }

        $this->load();
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return Arr::get($this->config, $key, $default);
    }

    public function set(string $key, mixed $value): Configuration
    {
        if ($value === 'true') {
            $value = true;
        } elseif ($value === 'false') {
            $value = false;
        }

        Arr::set($this->config, $key, $value);

        return $this;
    }

    /**
     * @throws FileNotFoundException
     */
    public static function global(): Configuration
    {
        $filesystem = new Filesystem();
        $composer = new Composer($filesystem);

        return new Configuration(
            $filesystem,
            $composer->findGlobalComposerHomeDirectory()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public static function local(): Configuration
    {
        return new Configuration(
            new Filesystem(),
            getcwd()
        );
    }

    /**
     * @throws FileNotFoundException
     */
    public static function compiled(): CompiledConfiguration
    {
        return new CompiledConfiguration(
            self::global()->get(),
            self::local()->get()
        );
    }

    public function init(): void
    {
        $this->filesystem->ensureDirectoryExists($this->workingPath);

        if ($this->filesystem->exists($this->getFilePath())) {
            throw new \RuntimeException('Configuration file already exists');
        }

        $this->filesystem->put($this->getFilePath(), json_encode($this->getDefaultConfiguration(), JSON_PRETTY_PRINT));
    }

    private function getFilePath(): string
    {
        return $this->workingPath . '/' . self::FILENAME;
    }

    /**
     * @throws FileNotFoundException
     */
    private function load(): void
    {
        if (!$this->filesystem->exists($this->getFilePath())) {
            $this->config = [];

            return;
        }

        $this->config = json_decode($this->filesystem->get($this->getFilePath()), true);
    }

    public function save(): void
    {
        $this->filesystem->put($this->getFilePath(), json_encode($this->config, JSON_PRETTY_PRINT));
    }

    private function getDefaultConfiguration(): array
    {
        return [
            'updates' => [
                'check' => true,
                'auto' => false,
            ],
            'append' => [
                'new' => '',
                'cache:clear' => '',
                'check-update' => '',
                'self-update' => '',
                'update' => '',
            ]
        ];
    }
}
