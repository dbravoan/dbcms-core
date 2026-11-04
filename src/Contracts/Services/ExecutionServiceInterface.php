<?php

declare(strict_types=1);

namespace DbCms\Core\Contracts\Services;

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Models\Orchestration\Execution;

interface ExecutionServiceInterface
{
    /** @param array<string,mixed> $context */
    public function start(string $instruction, ActorContext $actor, string $key, array $context = []): Execution;

    /** @param array<string,mixed> $args */
    public function startCapability(string $name, array $args, ActorContext $actor): Execution;

    public function owned(string $id, ActorContext $actor): Execution;

    public function answer(string $id, ActorContext $actor, string $answer): Execution;

    public function approve(string $id, ActorContext $actor, string $stepKey, string $binding): Execution;

    public function resume(string $id, ActorContext $actor): Execution;

    public function cancel(string $id, ActorContext $actor): Execution;

    public function run(string $id): void;
}
