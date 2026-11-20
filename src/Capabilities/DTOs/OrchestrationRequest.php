<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\DTOs;

use DbCms\Core\Capabilities\ActorContext;

final readonly class OrchestrationRequest
{
    /** @param array<string,mixed> $editorContext */
    public function __construct(public string $instruction, public ActorContext $actor, public string $idempotencyKey, public array $editorContext = []) {}
}
