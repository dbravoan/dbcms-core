<?php

declare(strict_types=1);

namespace DbCms\Core\Search;

use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Search\Contracts\SearchSourceInterface;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;

final class SearchSourceRegistry implements SearchSourceRegistryInterface
{
    /**
     * @var array<string, SearchSourceInterface>
     */
    private array $sources = [];

    public function register(SearchSourceInterface $source): void
    {
        $this->sources[$source->type()] = $source;
    }

    public function has(string $type): bool
    {
        return $this->get($type) !== null;
    }

    public function get(string $type): ?SearchSourceInterface
    {
        $source = $this->sources[$type] ?? null;

        return $source !== null && $this->available($source) ? $source : null;
    }

    /**
     * @return array<string, SearchSourceInterface>
     */
    public function all(): array
    {
        return array_filter($this->sources, fn (SearchSourceInterface $source): bool => $this->available($source));
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->all());
    }

    private function available(SearchSourceInterface $source): bool
    {
        $modules = app(ModuleManager::class);
        $owner = $modules->ownerOfClass($source::class);

        return $owner === null || $modules->isModuleEnabled($owner);
    }
}
