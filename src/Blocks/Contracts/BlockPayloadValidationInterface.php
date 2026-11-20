<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Contracts;

/** Validates relationships among fields after the editor schema has validated their shape. */
interface BlockPayloadValidationInterface
{
    /** @param array<string,mixed> $payload */
    public function validateStructure(array $payload): void;
}
