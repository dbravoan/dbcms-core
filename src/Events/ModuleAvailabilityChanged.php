<?php

declare(strict_types=1);

namespace DbCms\Core\Events;

final readonly class ModuleAvailabilityChanged
{
    public function __construct(public string $module, public bool $available, public string $operation) {}
}
