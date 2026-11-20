<?php

declare(strict_types=1);

namespace DbCms\Core\Maintenance\Contracts;

interface MaintenanceSettingsAdministrationInterface
{
    /** @param array<string, mixed> $settings */
    public function update(array $settings): void;

    /** @return array<string,mixed> */
    public function view(): array;

    public function toggle(): bool;

    public function preview(): string;
}
