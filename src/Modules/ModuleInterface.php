<?php

declare(strict_types=1);

namespace DbCms\Core\Modules;

use DbCms\Core\Models\User;

interface ModuleInterface
{
    /**
     * Human-readable module name.
     */
    public function getName(): string;

    /**
     * If true, this module registers its own top-level frontoffice routes
     * (e.g., Blog registers /blog/{slug}, /blog/categoria/{slug}).
     */
    public function isPageable(): bool;

    /**
     * Registers frontoffice routes if isPageable() is true.
     */
    public function registerPageableRoutes(): void;

    /**
     * Backoffice navigation items for admin sidebar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminNavigation(): array;

    /** @return array{views: array<string, string>, data: array<string, mixed>}|null */
    public function getAdminDashboard(User $user): ?array;

    /**
     * Returns the settings schema definition for the module.
     *
     * @return array<string, array{type: string, label: string, description?: string, placeholder?: string, required?: bool, secret?: bool, default?: mixed, options?: array<string, string>}>
     */
    public function getSettingsSchema(): array;
}
