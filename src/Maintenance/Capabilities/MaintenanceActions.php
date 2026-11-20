<?php

declare(strict_types=1);

namespace DbCms\Core\Maintenance\Capabilities;

use DbCms\Core\Modules\Contracts\MaintenanceModeInterface;

final class MaintenanceActions
{
    public function __construct(private readonly MaintenanceModeInterface $maintenance) {}

    /**
     * @return array<string, mixed>
     */
    public function maintenanceGet(): array
    {
        return [
            'active' => $this->maintenance->isActive(),
            'settings' => $this->maintenance->getSettings(),
            'retry_after_seconds' => $this->maintenance->getRetryAfterSeconds(),
            'ends_at' => $this->maintenance->getEndDateTime()?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function maintenanceToggle(array $args): array
    {
        $enable = array_key_exists('enabled', $args) ? filter_var($args['enabled'], FILTER_VALIDATE_BOOLEAN) : null;

        if ($enable === true) {
            $this->maintenance->enable(
                isset($args['until']) ? (string) $args['until'] : null,
                isset($args['message']) ? (string) $args['message'] : null,
            );
        } elseif ($enable === false) {
            $this->maintenance->disable();
        } else {
            $this->maintenance->toggle();
        }

        return $this->maintenanceGet();
    }
}
