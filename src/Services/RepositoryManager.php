<?php

namespace NativeCLI\Services;

use JsonException;
use NativeCLI\Composer;

readonly class RepositoryManager
{
    public function __construct(
        private Composer $composer
    ) {}

    /**
     * @throws JsonException
     */
    public function addRepository(string $type, string $url): bool
    {
        if ($this->repositoryExists($type, $url)) {
            return true;
        }

        $this->composer->modify(
            function (array $composerFile) use ($type, $url) {
                $composerFile['repositories'][] = [
                    'type' => $type,
                    'url' => $url,
                ];

                return $composerFile;
            }
        );

        return $this->repositoryExists($type, $url);
    }

    /**
     * @throws JsonException
     */
    public function repositoryExists(string $type, string $url): bool
    {
        $composerFile = json_decode(
            file_get_contents(
                $this->composer->getComposerFile()
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $repositories = $composerFile['repositories'] ?? [];

        foreach ($repositories as $repository) {
            if ($repository['type'] === $type && $repository['url'] === $url) {
                return true;
            }
        }

        return false;
    }
}
