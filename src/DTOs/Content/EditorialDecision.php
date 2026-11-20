<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Content;

final readonly class EditorialDecision
{
    public function __construct(public string $status, public int $currentVersion, public ?int $revisionId = null) {}
}
