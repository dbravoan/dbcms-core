<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Resource;

use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\ResourceCrudService;

/**
 * patches a row of a platform resource (order status, submission reviewed, media alt text, user role, SEO landings, n
 */
class ResourceUpdateTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly ResourceCrudService $resources,
    ) {}

    public function name(): string
    {
        return 'cms_resource_update';
    }

    public function description(): string
    {
        return 'MCP: patches a row of a platform resource (order status, submission reviewed, media alt text, user role, SEO landings, notification read…)';
    }

    public function properties(): array
    {
        return [
            'resource' => ['type' => 'string', 'description' => 'Resource key'],
            'id' => ['type' => ['integer', 'string'], 'description' => 'Row id'],
            'attributes' => ['type' => 'object', 'description' => 'Attributes to patch'],
        ];
    }

    public function required(): array
    {
        return ['resource', 'id', 'attributes'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->safePermission(fn (): ?string => $this->resources->resolve($this->string($args, 'resource'))->permission);
    }

    public function ability(): string
    {
        return self::ABILITY_WRITE;
    }

    public function handle(array $args): array
    {
        return $this->resources->update(
            $this->string($args, 'resource'),
            $args['id'] ?? 0,
            $this->array($args, 'attributes'),
        );
    }
}
