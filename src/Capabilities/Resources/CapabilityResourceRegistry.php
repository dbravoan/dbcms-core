<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Resources;

/**
 * Discovery point of the platform resources exposed to agents.
 */
class CapabilityResourceRegistry
{
    /**
     * @var array<string, CapabilityResource>
     */
    private array $resources = [];

    public function register(CapabilityResource $resource): void
    {
        $this->resources[$resource->key] = $resource;
    }

    /**
     * @param  iterable<int, CapabilityResource>  $resources
     */
    public function registerMany(iterable $resources): void
    {
        foreach ($resources as $resource) {
            $this->register($resource);
        }
    }

    public function has(string $key): bool
    {
        return isset($this->resources[$key]);
    }

    public function get(string $key): ?CapabilityResource
    {
        return $this->resources[$key] ?? null;
    }

    /**
     * @return array<string, CapabilityResource>
     */
    public function all(): array
    {
        ksort($this->resources);

        return $this->resources;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }
}
