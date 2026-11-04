<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

interface GeneralSettingsServiceInterface
{
    /**
     * Retrieve a setting value by key, falling back to default.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store or update a single setting.
     */
    public function set(string $key, mixed $value, string $group = 'general', string $type = 'string'): void;

    /**
     * Store or update multiple settings at once.
     *
     * @param  array<string, mixed>  $settings  Key-value dictionary
     * @param  string  $group  Functional group name
     */
    public function setMany(array $settings, string $group = 'general'): void;

    /**
     * Retrieve all settings grouped by group name.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allGrouped(): array;

    /**
     * Flush the in-memory settings cache.
     */
    public function flushCache(): void;
}
