<?php

declare(strict_types=1);

namespace DbCms\Core\Modules;

use DbCms\Core\DTOs\Modules\ModuleSettingsUpdateDTO;

final class ModuleSettingsExtensionRegistry
{
    /** @var array<string, callable(ModuleSettingsUpdateDTO): void> */
    private array $updates = [];

    /** @var array<string, callable(): array<string, mixed>> */
    private array $views = [];

    /** @var array<string, callable(array<string, mixed>): array<string, mixed>> */
    private array $normalizers = [];

    /** @param callable(ModuleSettingsUpdateDTO):void $update
     * @param  callable():array<string,mixed>  $view
     */
    public function register(string $module, callable $update, callable $view): void
    {
        $this->updates[$module] = $update;
        $this->views[$module] = $view;
    }

    public function update(string $module, ModuleSettingsUpdateDTO $settings): bool
    {
        if (! isset($this->updates[$module])) {
            return false;
        }
        ($this->updates[$module])($settings);

        return true;
    }

    /** @return array<string,mixed> */
    public function view(string $module): array
    {
        return isset($this->views[$module]) ? ($this->views[$module])() : [];
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $normalizer */
    public function registerNormalizer(string $module, callable $normalizer): void
    {
        $this->normalizers[$module] = $normalizer;
    }

    /** @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public function normalize(string $module, array $settings): array
    {
        return isset($this->normalizers[$module]) ? ($this->normalizers[$module])($settings) : $settings;
    }
}
