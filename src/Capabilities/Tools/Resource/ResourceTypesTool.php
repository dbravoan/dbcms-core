<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Resource;

use DbCms\Core\Capabilities\AvailableCapabilities;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\ResourceCrudService;

/**
 * lists the platform resources an agent may manage (markets, languages, menus, media, orders, form submissions, users
 */
class ResourceTypesTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly ResourceCrudService $resources,
    ) {}

    public function name(): string
    {
        return 'cms_resource_types';
    }

    public function description(): string
    {
        return 'MCP: lists the platform resources an agent may manage (markets, languages, menus, media, orders, form submissions, users, SEO landings, audit queue, tokens…) with the permission each one needs and the attributes it accepts';
    }

    public function properties(): array
    {
        return [];
    }

    public function required(): array
    {
        return [];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function ability(): string
    {
        return self::ABILITY_READ;
    }

    public function handle(array $args): array
    {
        $actor = app(ExecutionContext::class)->actor;
        $items = $this->resources->resources();
        if ($actor === null) {
            return ['resources' => []];
        }
        $items = array_values(array_filter($items, static fn (array $item): bool => app(AvailableCapabilities::class)->reason('cms_resource_get', $actor, ['resource' => $item['resource']]) === null));
        foreach ($items as &$item) {
            unset($item['model']);
        }
        unset($item);

        return ['resources' => $items];
    }
}
