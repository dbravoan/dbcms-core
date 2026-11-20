<?php

declare(strict_types=1);

namespace DbCms\Core\Forms;

/**
 * Declares every field type key the CMS knows about, even when the module that
 * implements it is disabled.
 *
 * Without this catalog, disabling a module would make its stored field type
 * "unknown", and the validator would silently degrade the field to a plain text
 * input. Here instead a declared-but-unavailable type is explicitly ignored.
 */
final class FieldTypeCatalog
{
    /**
     * @return array<string, string|null> type key => owning module identifier (null = core)
     */
    public static function all(): array
    {
        /** @var array<string, string|null> $types */
        $types = (array) config('forms.types', []);

        return $types;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
