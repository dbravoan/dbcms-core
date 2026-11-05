<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\DTOs;

final readonly class ExecutionPlan
{
    /**
     * @param  list<ExecutionStep>  $steps
     */
    public function __construct(public array $steps, public string $summary = '', public ?string $question = null) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['summary' => $this->summary, 'question' => $this->question, 'steps' => array_map(fn (ExecutionStep $step): array => $step->toArray(), $this->steps)];
    }
}
