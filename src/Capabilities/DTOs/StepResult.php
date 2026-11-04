<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\DTOs;

final readonly class StepResult
{
    /** @param array<string,mixed> $payload */
    public function __construct(public string $status, public array $payload) {}

    /** @param array<string,mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $status = match (true) {
            ! empty($payload['confirmation_required']) => 'approval_required',
            ! empty($payload['pending']) => 'pending_remote',
            ! empty($payload['uncertain']) => 'uncertain',
            ! empty($payload['denied']) => 'denied',
            isset($payload['validation']) => 'validation_failed',
            isset($payload['error']) => 'failed',
            default => 'completed',
        };

        return new self($status, $payload);
    }
}
