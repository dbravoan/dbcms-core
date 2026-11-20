<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Contracts;

interface CapabilityAuditInterface
{
    /**
     * @param  array<string,mixed>  $context
     */
    public function log(array $context): void;
}
