<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\DTOs\BrandIdentity;

/**
 * Resolves the brand identity of backoffice themes.
 *
 * Values come from the theme manifest (`theme.json` → `brand`), with optional
 * environment overrides. Brand values must never be hardcoded in config or
 * views — read them from here.
 *
 * @see docs/EXTENSIONS.md
 */
interface BrandServiceInterface
{
    /**
     * Validated active backoffice theme identifier.
     *
     * @throws \DomainException when the configured theme is not installed.
     */
    public function activeTheme(): string;

    /**
     * Brand identity of the active backoffice theme.
     */
    public function current(): BrandIdentity;

    /**
     * Brand identity of an arbitrary theme identifier (used by the installer).
     */
    public function forTheme(string $theme): BrandIdentity;
}
