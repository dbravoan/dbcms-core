<?php

declare(strict_types=1);

namespace DbCms\Core\Modules\Contracts;

use Carbon\Carbon;

interface MaintenanceModeInterface
{
    public function isAvailable(): bool;

    public function isActive(): bool;

    /** @return array<string,mixed> */
    public function getSettings(): array;

    public function getRetryAfterSeconds(): int;

    public function getEndDateTime(): ?Carbon;

    public function enable(?string $endDatetime = null, ?string $message = null): void;

    public function disable(): void;

    public function toggle(): bool;

    public function renderHtml(): string;
}
