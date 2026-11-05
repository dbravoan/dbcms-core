<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities;

final class ExecutionContext
{
    public ?ActorContext $actor = null;

    public ?string $capabilityName = null;

    /** @var array<string,mixed> */
    public array $arguments = [];
}
