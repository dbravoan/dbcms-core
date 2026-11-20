<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Content;

final readonly class PendingEditorialDraft
{
    /** @param array<string,mixed> $snapshot */
    public function __construct(public int $id, public array $snapshot) {}
}
