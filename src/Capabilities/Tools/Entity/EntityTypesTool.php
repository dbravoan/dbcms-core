<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Entity;

use DbCms\Core\Capabilities\AvailableCapabilities;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\EntityReadService;

/**
 * Catalog of the entities an agent can manage (posts, pages, products,
 * categories, galleries, forms…), with their semantic fields.
 */
class EntityTypesTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly EntityReadService $entities,
    ) {}

    public function name(): string
    {
        return 'cms_entity_types';
    }

    public function description(): string
    {
        return 'MCP: lists every entity type installed in the CMS with the permission it requires, its writable attributes, its translatable fields and its semantic roles. Call it before cms_entity_create/update to know exactly what you may write';
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
        $items = $this->entities->types();
        if ($actor === null) {
            return ['types' => []];
        }
        $items = array_values(array_filter($items, static fn (array $item): bool => app(AvailableCapabilities::class)->reason('cms_entity_get', $actor, ['type' => $item['type']]) === null));
        foreach ($items as &$item) {
            unset($item['model']);
        }
        unset($item);

        return ['types' => $items];
    }
}
