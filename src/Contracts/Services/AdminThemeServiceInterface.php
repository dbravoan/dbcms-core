<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

interface AdminThemeServiceInterface
{
    /**
     * Get the currently active backoffice theme identifier.
     */
    public function getActiveTheme(): string;

    /**
     * Clears the memoised active theme so it is re-read from configuration.
     */
    public function resetActiveTheme(): void;

    /**
     * List all discovered backoffice themes.
     *
     * @return array<string, array{name: string, identifier: string, path: string, version: string, description: string, author: string}>
     */
    public function getAvailableThemes(): array;

    /**
     * Read a backoffice theme manifest (theme.json) with sensible fallbacks.
     *
     * @return array{name: string, identifier: string, path: string, version: string, description: string, author: string, brand: array{name: string, tagline: string, logo: string|null, icon: string|null, edition: string|null, accent: string, mode: string}}
     */
    public function getThemeManifest(string $theme): array;

    /**
     * Resolve the web path of an asset shipped inside a backoffice theme.
     */
    public function themeAsset(string $theme, string $path): string;

    /**
     * Get the absolute filesystem path for a specific admin theme.
     */
    public function getThemePath(string $theme): string;

    /**
     * Determine whether an admin theme directory exists.
     */
    public function themeExists(string $theme): bool;

    /**
     * Register cascading view locations and component paths for the active backoffice theme.
     */
    public function registerThemeViewPaths(): void;

    /**
     * Set the currently active backoffice theme identifier and refresh view paths.
     */
    public function setActiveTheme(string $theme): void;

    /**
     * Register cascading theme override paths for a specific module's view namespace.
     */
    public function registerModuleThemePaths(string $moduleIdentifier, string $moduleBaseViewPath): void;
}
