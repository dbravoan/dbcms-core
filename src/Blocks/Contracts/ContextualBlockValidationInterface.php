<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

use DbCms\Core\Models\Market;

/** Validates owner-defined inputs whose allowed values depend on public context. */
interface ContextualBlockValidationInterface
{
    /** @param array<string,mixed> $payload */
    public function validatePayload(array $payload, Market $market, string $locale): void;
}
