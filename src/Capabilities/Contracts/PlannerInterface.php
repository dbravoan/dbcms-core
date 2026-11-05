<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Contracts;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\DTOs\ExecutionPlan;

interface PlannerInterface
{
    /**
     * @param  array<string,mixed>  $context
     */
    public function plan(string $instruction, ActorContext $actor, array $context = []): ExecutionPlan;
}
