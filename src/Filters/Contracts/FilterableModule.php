<?php

declare(strict_types=1);

namespace DbCms\Core\Filters\Contracts;

use DbCms\Core\Filters\FilterDimension;

/**
 * Declares the addressable filter dimensions of a front-office module.
 *
 * Any module that is paginable, searchable or filterable must implement this
 * contract so its filters become friendly, canonical, SEO-aware URLs (see
 * `docs/EXTENSIONS.md`).
 */
interface FilterableModule
{
    /**
     * Module identifier (matches the module's `identifier`, e.g. `blog`).
     */
    public function filterModuleKey(): string;

    /**
     * @return array<int, FilterDimension>
     */
    public function filterDimensions(): array;

    /**
     * Localized label of the module, used to compose filter page texts.
     */
    public function filterModuleLabel(string $locale): string;
}
