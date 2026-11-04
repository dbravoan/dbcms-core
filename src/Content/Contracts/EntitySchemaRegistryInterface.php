<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use DbCms\Core\Content\EntitySchema;

/**
 * Discovery point for the content entities declared by the installed modules.
 *
 * Kept in core (not in any feature module) so modules can describe their own
 * entities without depending on the AI assistant, the MCP bridge or anything
 * else that consumes them.
 */
interface EntitySchemaRegistryInterface
{
    public function register(EntitySchema $schema): void;

    public function get(string $type): ?EntitySchema;

    public function has(string $type): bool;

    /**
     * @return array<string, EntitySchema>
     */
    public function all(): array;

    /**
     * @return list<string>
     */
    public function types(): array;
}
