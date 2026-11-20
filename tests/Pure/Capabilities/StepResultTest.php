<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\DTOs\StepResult;

it('classifies capability outcomes without inventing a successful result', function (array $payload, string $status): void {
    expect(StepResult::fromPayload($payload)->status)->toBe($status);
})->with([
    'clarification without an error' => [['success' => false, 'status' => 'needs_input', 'question' => 'Which language?'], 'needs_input'],
    'clarification after validation' => [['success' => false, 'status' => 'needs_input', 'error' => 'Validation failed.', 'validation' => ['title' => ['Required']]], 'needs_input'],
    'unsuccessful payload' => [['success' => false], 'failed'],
    'explicit conflict' => [['status' => 'conflict'], 'conflict'],
    'explicit unavailability' => [['status' => 'unavailable'], 'unavailable'],
    'denied without an error' => [['denied' => true], 'denied'],
    'uncertain without an error' => [['uncertain' => true], 'uncertain'],
    'human approval' => [['confirmation_required' => true, 'error' => 'Review required'], 'approval_required'],
    'provider checkpoint' => [['pending' => true, 'provider_request_id' => 'remote-1'], 'pending_remote'],
    'completed payload' => [['success' => true, 'id' => 1], 'completed'],
]);
