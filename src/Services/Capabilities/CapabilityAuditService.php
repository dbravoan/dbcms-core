<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Capabilities\Contracts\CapabilityAuditInterface;

final readonly class CapabilityAuditService implements CapabilityAuditInterface
{
    public function __construct(private CapabilityAuditLogger $logger) {}

    public function log(array $context): void
    {
        $this->logger->log($context);
    }
}
