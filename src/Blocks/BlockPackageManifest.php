<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DomainException;

/** Validates contribution classes; Composer owns namespaces and dependencies. */
final class BlockPackageManifest
{
    /** @param array<string, mixed> $manifest */
    public static function validate(array $manifest): void
    {
        if (! is_array($manifest['blocks'] ?? null) || $manifest['blocks'] === [] || ! array_is_list($manifest['blocks'])) {
            throw new DomainException('A block package must declare its block classes.');
        }
        foreach ($manifest['blocks'] as $class) {
            if (! is_string($class) || ! is_subclass_of($class, Contracts\BlockTypeInterface::class)) {
                throw new DomainException('Block definitions must implement BlockTypeInterface.');
            }
        }
    }
}
