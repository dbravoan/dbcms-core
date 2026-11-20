<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools\Action;

use Closure;
use DbCms\Core\Capabilities\Tools\AbstractCapabilityTool;

/**
 * A capability declared in {@see CmsActionCatalog}.
 *
 * Non-CRUD operations (platform lifecycle, maintenance, caches, editorial
 * workflow…) are declared as data — name, schema, permission, ability and a
 * handler closure bound to the service — instead of one class per action, so the
 * catalog stays the single place where the agent surface is described.
 */
class ActionTool extends AbstractCapabilityTool
{
    /**
     * @param  array<string, array<string, mixed>>  $toolProperties
     * @param  list<string>  $toolRequired
     * @param  Closure(array<string, mixed>): array<string, mixed>  $handler
     * @param  Closure(array<string,mixed>): ?string|null  $permissionResolver
     */
    public function __construct(
        private readonly string $toolName,
        private readonly string $toolDescription,
        private readonly array $toolProperties,
        private readonly array $toolRequired,
        private readonly ?string $toolPermission,
        private readonly string $toolAbility,
        private readonly Closure $handler,
        private readonly ?Closure $permissionResolver = null,
    ) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return $this->toolDescription;
    }

    public function properties(): array
    {
        return $this->toolProperties;
    }

    public function required(): array
    {
        return $this->toolRequired;
    }

    public function permission(): ?string
    {
        return $this->toolPermission;
    }

    public function ability(): string
    {
        return $this->toolAbility;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->permissionResolver === null ? $this->permission() : $this->safePermission(fn (): ?string => ($this->permissionResolver)($args));
    }

    public function handle(array $args): array
    {
        return ($this->handler)($args);
    }
}
