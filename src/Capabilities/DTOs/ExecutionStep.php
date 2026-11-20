<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\DTOs;

final readonly class ExecutionStep
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $dependsOn
     */
    public function __construct(public string $id, public string $capability, public array $arguments, public array $dependsOn = []) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'capability' => $this->capability, 'arguments' => $this->arguments, 'depends_on' => $this->dependsOn];
    }
}
