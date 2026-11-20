<?php

declare(strict_types=1);

namespace DbCms\Core\Filters;

use DbCms\Core\Filters\Contracts\FilterableModule;
use DbCms\Core\Modules\ModuleManager;

/**
 * Runtime catalog of the filterable front-office modules.
 *
 * Modules register themselves in `boot()` (see {@see FilterableModule}), so any
 * consumer — the curation screen, the sitemap, llms.txt or the MCP tools — can
 * resolve a module by key and reuse its dimensions without coupling to it.
 */
final class FilterModuleRegistry
{
    /** @var array<string, FilterableModule> */
    private array $modules = [];

    public function register(FilterableModule $module): void
    {
        $this->modules[$module->filterModuleKey()] = $module;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function get(string $key): ?FilterableModule
    {
        return app(ModuleManager::class)->isModuleEnabled($key) ? ($this->modules[$key] ?? null) : null;
    }

    /**
     * @return array<string, FilterableModule>
     */
    public function all(): array
    {
        return array_filter($this->modules, fn (FilterableModule $module): bool => app(ModuleManager::class)->isModuleEnabled($module->filterModuleKey()));
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
