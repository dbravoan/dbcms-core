<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Resource;

use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;
use DbCms\Core\Services\Capabilities\ResourceCrudService;

/**
 * creates a row of a platform resource (market, language, menu, menu item, user, SEO landing…). Field rules mirror th
 */
class ResourceCreateTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly ResourceCrudService $resources,
    ) {}

    public function name(): string
    {
        return 'cms_resource_create';
    }

    public function description(): string
    {
        return 'MCP: creates a row of a platform resource (market, language, menu, menu item, user, SEO landing…). Field rules mirror the backoffice form';
    }

    public function properties(): array
    {
        return [
            'resource' => ['type' => 'string', 'description' => 'Resource key'],
            'attributes' => ['type' => 'object', 'description' => 'Row attributes, e.g. {"code": "fr", "name": "Francia", "currency": "EUR"}'],
        ];
    }

    public function required(): array
    {
        return ['resource', 'attributes'];
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
        return $this->resources->create($this->string($args, 'resource'), $this->array($args, 'attributes'));
    }
}
