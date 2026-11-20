<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

interface ModuleSettingsServiceInterface
{
    /**
     * Get a setting value for a specific module, falling back to default or config.
     */
    public function get(string $moduleIdentifier, string $key, mixed $default = null): mixed;

    /**
     * Get all stored settings for a module.
     *
     * @return array<string, mixed>
     */
    public function getAll(string $moduleIdentifier): array;

    /**
     * Set a single setting value for a module.
     */
    public function set(string $moduleIdentifier, string $key, mixed $value): void;

    /**
     * Mass update settings for a module, preserving unedited secret keys.
     *
     * @param  array<string, mixed>  $settings
     */
    public function setMany(string $moduleIdentifier, array $settings): void;

    /**
     * Get the schema definition for a module's settings.
     *
     * @return array<string, array{type: string, label: string, description?: string, placeholder?: string, required?: bool, secret?: bool, default?: mixed, options?: array<string, string>}>
     */
    public function getSchema(string $moduleIdentifier): array;

    /**
     * Check if a module has all its required settings configured.
     */
    public function isConfigured(string $moduleIdentifier): bool;
}
