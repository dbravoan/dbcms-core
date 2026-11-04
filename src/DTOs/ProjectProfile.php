<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs;

final readonly class ProjectProfile
{
    /**
     * @param  list<string>  $themes
     * @param  array{module_directories:list<string>,block_package_directories:list<string>,include_showcase:bool}|null  $sourceSelection
     */
    public function __construct(
        public string $name,
        public string $brand,
        public array $themes,
        public string $defaultTheme,
        public string $coreVersion,
        public string $coreSha,
        public string $deployment,
        public ?array $sourceSelection = null,
    ) {}

    /** @return array{schema: int, name: string, brand: string, themes: list<string>, default_theme: string, deployment: string, core: array{repository: string, version: string, sha: string}, source_selection?: array{module_directories:list<string>,block_package_directories:list<string>,include_showcase:bool}} */
    public function toArray(): array
    {
        return ['schema' => 1, 'name' => $this->name, 'brand' => $this->brand, 'themes' => $this->themes, 'default_theme' => $this->defaultTheme, 'deployment' => $this->deployment, 'core' => ['repository' => 'dbravoan/dbcms', 'version' => $this->coreVersion, 'sha' => $this->coreSha], ...($this->sourceSelection === null ? [] : ['source_selection' => $this->sourceSelection])];
    }
}
