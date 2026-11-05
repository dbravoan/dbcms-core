<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Entity;

use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\EntityReadService;

/**
 * Reads one entity with all its translations and its canonical URL.
 */
class EntityGetTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly EntityReadService $entities,
    ) {}

    public function name(): string
    {
        return 'cms_entity_get';
    }

    public function description(): string
    {
        return 'MCP: reads one entity of any type by id, including every translation, its writable attributes, its publication status and its canonical front-office URL';
    }

    public function properties(): array
    {
        return [
            'type' => ['type' => 'string', 'description' => 'Entity type (post, page, product, blog_category…)'],
            'id' => ['type' => ['integer', 'string'], 'description' => 'Entity id'],
        ];
    }

    public function required(): array
    {
        return ['type', 'id'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->safePermission(fn (): ?string => $this->entities->schemaFor($this->string($args, 'type'))->permission);
    }

    public function ability(): string
    {
        return self::ABILITY_READ;
    }

    public function handle(array $args): array
    {
        return $this->entities->find(
            $this->string($args, 'type'),
            $args['id'] ?? 0,
        );
    }
}
