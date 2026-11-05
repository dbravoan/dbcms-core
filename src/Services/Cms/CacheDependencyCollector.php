<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

/**
 * Collects the entities a public page depends on while it is being rendered.
 *
 * A global `Model::retrieved` hook feeds this collector whenever a cacheable
 * model is read; the page-cache middleware then stores the resulting dependency
 * keys next to the cached HTML so the matching pages can be dropped precisely
 * when the entity changes (instead of flushing every page).
 */
final class CacheDependencyCollector
{
    private bool $active = false;

    /**
     * @var array<string, true>
     */
    private array $recorded = [];

    public function start(): void
    {
        $this->active = true;
        $this->recorded = [];
    }

    /**
     * Record a specific entity and its type, so both updates (specific) and
     * creations/deletions (type-wide) can invalidate the right pages.
     */
    public function record(string $type, int|string $id): void
    {
        if (! $this->active) {
            return;
        }

        $this->recorded[$type.':'.$id] = true;
        $this->recorded[$type.':*'] = true;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function recordType(string $type): void
    {
        if ($this->active) {
            $this->recorded[$type.':*'] = true;
        }
    }

    /**
     * Stop collecting and return the dependency keys gathered so far.
     *
     * @return array<int, string>
     */
    public function stop(): array
    {
        $this->active = false;
        $dependencies = array_keys($this->recorded);
        $this->recorded = [];

        return $dependencies;
    }
}
