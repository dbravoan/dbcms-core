<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Events;

final readonly class BlockPackageAvailabilityChanged
{
    public function __construct(public string $identifier, public bool $enabled) {}
}
