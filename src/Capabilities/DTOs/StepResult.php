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
            in_array($payload['status'] ?? null, ['needs_input', 'approval_required', 'pending_remote', 'uncertain', 'denied', 'conflict', 'unavailable', 'validation_failed', 'failed'], true) => $payload['status'],
            isset($payload['validation']) => 'validation_failed',
            isset($payload['error']) => 'failed',
            ($payload['success'] ?? null) === false => 'failed',
            default => 'completed',
        };

        return new self($status, $payload);
    }
}
