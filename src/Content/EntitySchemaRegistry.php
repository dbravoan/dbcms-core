<?php

declare(strict_types=1);

namespace DbCms\Core\Content;

use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;

final class EntitySchemaRegistry implements EntitySchemaRegistryInterface
{
    /**
     * @var array<string, EntitySchema>
     */
    private array $schemas = [];

    public function register(EntitySchema $schema): void
    {
        $this->schemas[$schema->type] = $schema;
    }

    public function get(string $type): ?EntitySchema
    {
        return $this->schemas[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->schemas[$type]);
    }

    /**
     * @return array<string, EntitySchema>
     */
    public function all(): array
    {
        return $this->schemas;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->schemas);
    }
}
