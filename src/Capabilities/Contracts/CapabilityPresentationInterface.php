<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Contracts;

use DbCms\Core\Capabilities\ActorContext;

interface CapabilityPresentationInterface
{
    /**
     * @param  array<string,mixed>  $args
     */
    public function reason(string $name, array $args, ActorContext $actor): ?string;

    /**
     * @param  array<string,mixed>  $schema
     * @return array<string,mixed>|null
     */
    public function schema(string $name, array $schema, ActorContext $actor): ?array;
}
