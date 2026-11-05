<?php

declare(strict_types=1);

namespace DbCms\Core\Forms;

use DbCms\Core\Forms\Contracts\FieldType;

/**
 * Runtime catalog of the available form field types.
 *
 * The core registers the primitive HTML inputs; modules register their own
 * types in `boot()` (see {@see FieldType}). Consumers
 * (renderer, validator, form builder) resolve everything through this registry
 * instead of branching on a hardcoded type list.
 */
final class FieldTypeRegistry
{
    /** @var array<string, FieldType> */
    private array $types = [];

    public function register(FieldType $type): void
    {
        $this->types[$type->key()] = $type;
    }

    public function forget(string $key): void
    {
        unset($this->types[$key]);
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): ?FieldType
    {
        return $this->types[$key] ?? null;
    }

    /**
     * @return array<string, FieldType>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Types usable right now (their module is installed and active).
     *
     * @return array<string, FieldType>
     */
    public function available(): array
    {
        return array_filter($this->types, static fn (FieldType $type): bool => $type->available());
    }

    /**
     * Resolve the type to use for a field, or `null` when the field must be
     * ignored because its type is declared by an unavailable module.
     */
    public function resolve(string $key): ?FieldType
    {
        $type = $this->get($key);

        if ($type !== null) {
            return $type->available() ? $type : null;
        }

        return null;
    }
}
