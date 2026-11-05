<?php

declare(strict_types=1);

namespace DbCms\Core\Modules;

use Carbon\Carbon;
use DbCms\Core\Modules\Contracts\MaintenanceModeInterface;

/** Allows the CMS to boot without the optional module; mutations fail explicitly. */
final class NullMaintenanceModeService implements MaintenanceModeInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function isActive(): bool
    {
        return false;
    }

    public function getSettings(): array
    {
        return [];
    }

    public function getRetryAfterSeconds(): int
    {
        return 3600;
    }

    public function getEndDateTime(): ?Carbon
    {
        return null;
    }

    public function enable(?string $endDatetime = null, ?string $message = null): void
    {
        throw new \LogicException('The maintenance module is unavailable.');
    }

    public function disable(): void
    {
        throw new \LogicException('The maintenance module is unavailable.');
    }

    public function toggle(): bool
    {
        throw new \LogicException('The maintenance module is unavailable.');
    }

    public function renderHtml(): string
    {
        return '';
    }
}
