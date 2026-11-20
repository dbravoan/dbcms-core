<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\CallableCapability;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Capabilities\Contracts\PlannerInterface;
use DbCms\Core\Capabilities\DTOs\ExecutionPlan;
use DbCms\Core\Capabilities\DTOs\ExecutionStep;
use DbCms\Core\Jobs\Orchestration\RunExecution;
use DbCms\Core\Models\Orchestration\Execution;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Orchestration\ExecutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
    $this->user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));
    $this->actor = new ActorContext((int) $this->user->id);
    $this->service = app(ExecutionService::class);
    $this->laterCalls = 0;
    app(CapabilityRegistry::class)->register(new CallableCapability('test_later', 'Later action', [], [], 'manage:pages', 'write', function (): array {
        $this->laterCalls++;

        return ['success' => true];
    }), effect: 'draft');
});

function coreStatePlan(ExecutionPlan $plan): void
{
    app()->instance(PlannerInterface::class, new class($plan) implements PlannerInterface
    {
        public function __construct(private readonly ExecutionPlan $value) {}

        public function plan(string $instruction, ActorContext $actor, array $context = []): ExecutionPlan
        {
            return $this->value;
        }
    });
}

test('a clarification pauses the plan and an answer replans through the configured planner', function (array $result): void {
    app(CapabilityRegistry::class)->register(new CallableCapability('test_question', 'Needs input', [], [], 'manage:pages', 'write', fn (): array => $result), effect: 'draft');
    coreStatePlan(new ExecutionPlan([new ExecutionStep('question', 'test_question', []), new ExecutionStep('later', 'test_later', [], ['question'])]));
    $execution = $this->service->start('Prepare content', $this->actor, 'core-question');
    $this->service->run($execution->id);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('needs_input')
        ->and($execution->question)->toBe('Which language?')
        ->and($execution->steps()->where('key', 'question')->sole()->status)->toBe('needs_input')
        ->and($this->laterCalls)->toBe(0);
    coreStatePlan(new ExecutionPlan([new ExecutionStep('later', 'test_later', [])]));
    $this->service->answer($execution->id, $this->actor, 'Spanish');
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('completed')->and($this->laterCalls)->toBe(1)
        ->and($execution->instruction)->toContain('Spanish');
    Http::assertNothingSent();
})->with([
    'without error' => [['success' => false, 'status' => 'needs_input', 'question' => 'Which language?']],
    'with validation' => [['success' => false, 'status' => 'needs_input', 'question' => 'Which language?', 'error' => 'Validation failed.', 'validation' => ['locale' => ['Required']]]],
]);

test('an unsuccessful result stops downstream effects even without an error field', function (array $result): void {
    app(CapabilityRegistry::class)->register(new CallableCapability('test_refusal', 'Refused action', [], [], 'manage:pages', 'write', fn (): array => $result), effect: 'draft');
    coreStatePlan(new ExecutionPlan([new ExecutionStep('refusal', 'test_refusal', []), new ExecutionStep('later', 'test_later', [], ['refusal'])]));
    $execution = $this->service->start('Prepare content', $this->actor, 'core-refusal');
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('failed')->and($this->laterCalls)->toBe(0)
        ->and($execution->steps()->where('key', 'refusal')->sole()->status)->not->toBe('completed');
})->with([
    [['success' => false]],
    [['status' => 'conflict']],
    [['denied' => true]],
]);

test('cancellation during a capability survives its late result without queuing more work', function (array $result): void {
    $executionId = null;
    app(CapabilityRegistry::class)->register(new CallableCapability('test_inflight', 'In flight operation', [], [], 'manage:pages', 'write', function () use (&$executionId, $result): array {
        $this->service->cancel($executionId, $this->actor);

        return $result;
    }), effect: 'generation');
    coreStatePlan(new ExecutionPlan([new ExecutionStep('remote', 'test_inflight', []), new ExecutionStep('later', 'test_later', [], ['remote'])]));
    $execution = $this->service->start('Run operation', $this->actor, 'core-cancel');
    $executionId = $execution->id;
    $this->service->run($executionId);
    expect($execution->refresh()->status)->toBe('cancelled')->and($this->laterCalls)->toBe(0);
    $step = $execution->steps()->where('key', 'remote')->sole();
    expect($step->result)->toBe($result);
    if (isset($result['provider_request_id'])) {
        expect($step->resolved_arguments['provider_request_id'])->toBe($result['provider_request_id']);
    }
    $this->service->resume($executionId, $this->actor);
    $this->service->run($executionId);
    expect($execution->refresh()->status)->toBe('cancelled')->and($this->laterCalls)->toBe(0);
    Queue::assertNotPushed(RunExecution::class);
})->with([
    'provider checkpoint' => [['pending' => true, 'provider' => 'test', 'provider_request_id' => 'remote-existing']],
    'failure' => [['error' => 'Remote refused']],
    'completed' => [['success' => true]],
    'clarification' => [['status' => 'needs_input', 'success' => false, 'question' => 'Which language?']],
]);

test('core executions can be reviewed and approved without an AI planner or module', function (): void {
    expect(app()->bound(PlannerInterface::class))->toBeFalse();
    app(CapabilityRegistry::class)->register(new CallableCapability('test_human_review', 'Publish reviewed content', [], [], 'manage:pages', 'write', function (): array {
        $this->laterCalls++;

        return ['success' => true];
    }), effect: 'publish');
    $execution = $this->service->startCapability('test_human_review', [], $this->actor);
    $step = $execution->steps()->sole();
    expect($execution->status)->toBe('approval_required')->and($this->laterCalls)->toBe(0);
    $this->actingAs($this->user)->get('/admin/executions/'.$execution->id)->assertOk();
    $this->actingAs($this->user)->postJson('/admin/executions/'.$execution->id.'/approve', ['step' => $step->key, 'binding_hash' => $step->binding_hash])->assertOk();
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('completed')->and($this->laterCalls)->toBe(1);
    Http::assertNothingSent();
});

test('resume preserves a cancellation made after the ownership read', function (): void {
    coreStatePlan(new ExecutionPlan([new ExecutionStep('later', 'test_later', [])]));
    $execution = $this->service->start('Resume operation', $this->actor, 'resume-during-cancel');
    $execution->update(['status' => 'failed']);
    $execution->steps()->update(['status' => 'failed']);
    $cancelled = false;
    Execution::retrieved(function (Execution $record) use ($execution, &$cancelled): void {
        if ($record->is($execution) && ! $cancelled) {
            $cancelled = true;
            DB::table('cms_executions')->where('id', $record->id)->update(['status' => 'cancelled']);
        }
    });

    $resumed = $this->service->resume($execution->id, $this->actor);

    expect($cancelled)->toBeTrue()
        ->and($resumed->status)->toBe('cancelled')
        ->and($resumed->steps->sole()->status)->toBe('failed');
});

test('answering an intermediate clarification preserves completed effects and their remote checkpoint', function (): void {
    $generations = 0;
    $polls = 0;
    app(CapabilityRegistry::class)->register(new CallableCapability('test_checkpoint', 'Remote draft', ['provider_request_id' => ['type' => 'string'], 'provider' => ['type' => 'string']], [], 'manage:pages', 'write', function (array $args) use (&$generations, &$polls): array {
        if (! isset($args['provider_request_id'])) {
            $generations++;

            return ['pending' => true, 'provider' => 'test', 'provider_request_id' => 'paid-request'];
        }
        $polls++;

        return ['id' => 51, 'content' => 'Existing draft', 'provider_request_id' => $args['provider_request_id']];
    }), effect: 'generation');
    app(CapabilityRegistry::class)->register(new CallableCapability('test_translate', 'Translate draft', ['id' => ['type' => 'integer'], 'locale' => ['type' => 'string']], ['id'], 'manage:pages', 'write', fn (array $args): array => isset($args['locale']) ? ['id' => $args['id'], 'locale' => $args['locale']] : ['status' => 'needs_input', 'success' => false, 'question' => 'Which language?']), effect: 'draft');
    $first = new ExecutionStep('draft', 'test_checkpoint', []);
    $question = new ExecutionStep('translate', 'test_translate', ['id' => ['$ref' => 'draft.id']], ['draft']);
    $later = new ExecutionStep('later', 'test_later', [], ['translate']);
    coreStatePlan(new ExecutionPlan([$first, $question, $later]));
    $execution = $this->service->start('Draft and translate', $this->actor, 'preserve-progress');
    $this->service->run($execution->id);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('needs_input')->and($generations)->toBe(1)->and($polls)->toBe(1);
    $completed = $execution->steps()->where('key', 'draft')->sole();
    $checkpoint = $completed->getRawOriginal();
    DB::table('cms_execution_approvals')->insert(['step_id' => $completed->id, 'user_id' => $this->user->id, 'actor_user_id' => $this->user->id, 'plan_version' => 1, 'binding_hash' => str_repeat('a', 64), 'approved_at' => now(), 'consumed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    coreStatePlan(new ExecutionPlan([$first, new ExecutionStep('translate', 'test_translate', ['id' => ['$ref' => 'draft.id'], 'locale' => 'es'], ['draft']), $later]));
    $this->service->answer($execution->id, $this->actor, 'Spanish');
    expect($execution->steps()->where('key', 'draft')->sole()->getRawOriginal())->toBe($checkpoint);
    expect(DB::table('cms_execution_approvals')->where('step_id', $completed->id)->whereNotNull('consumed_at')->count())->toBe(1);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('completed')->and($generations)->toBe(1)->and($polls)->toBe(1)->and($this->laterCalls)->toBe(1);
    expect($execution->steps()->where('key', 'translate')->sole()->result)->toBe(['id' => 51, 'locale' => 'es']);
    expect($completed->fresh()->getRawOriginal())->toBe($checkpoint);
});

test('a continuation cannot rewrite completed keys or refer to an erased result', function (array $steps): void {
    app(CapabilityRegistry::class)->register(new CallableCapability('test_question', 'Needs input', [], [], 'manage:pages', 'write', fn (): array => ['status' => 'needs_input', 'question' => 'Which language?']), effect: 'draft');
    coreStatePlan(new ExecutionPlan([new ExecutionStep('first', 'test_later', []), new ExecutionStep('question', 'test_question', [])]));
    $execution = $this->service->start('Keep completed work', $this->actor, 'reject-replay');
    $this->service->run($execution->id);
    $completed = $execution->steps()->where('key', 'first')->sole()->getRawOriginal();
    coreStatePlan(new ExecutionPlan($steps));
    $this->service->answer($execution->id, $this->actor, 'Spanish');
    expect($execution->refresh()->status)->toBe('needs_input')->and($execution->error)->not->toBeNull();
    expect($execution->steps()->where('key', 'first')->sole()->getRawOriginal())->toBe($completed);
    $this->service->run($execution->id);
    expect($this->laterCalls)->toBe(1);
})->with([
    'renamed completed key' => [[new ExecutionStep('renamed', 'test_later', []), new ExecutionStep('question', 'test_question', [])]],
    'changed completed operation' => [[new ExecutionStep('first', 'test_question', []), new ExecutionStep('question', 'test_question', [])]],
    'completed key replayed in the tail' => [[new ExecutionStep('first', 'test_later', []), new ExecutionStep('question', 'test_question', []), new ExecutionStep('first', 'test_later', [])]],
    'reference to an erased step' => [[new ExecutionStep('first', 'test_later', []), new ExecutionStep('later', 'test_later', ['content' => ['$ref' => 'erased.content']], ['erased'])]],
]);

test('another planning question keeps existing completed rows until a safe continuation arrives', function (): void {
    app(CapabilityRegistry::class)->register(new CallableCapability('test_question', 'Needs input', [], [], 'manage:pages', 'write', fn (): array => ['status' => 'needs_input', 'question' => 'Which language?']), effect: 'draft');
    $first = new ExecutionStep('first', 'test_later', []);
    coreStatePlan(new ExecutionPlan([$first, new ExecutionStep('question', 'test_question', [])]));
    $execution = $this->service->start('Keep completed work', $this->actor, 'second-question');
    $this->service->run($execution->id);
    $completed = $execution->steps()->where('key', 'first')->sole()->getRawOriginal();
    coreStatePlan(new ExecutionPlan([], question: 'Which region?'));
    $this->service->answer($execution->id, $this->actor, 'Spanish');
    expect($execution->refresh()->status)->toBe('needs_input')->and($execution->question)->toBe('Which region?')
        ->and($execution->steps()->where('key', 'first')->sole()->getRawOriginal())->toBe($completed);
    coreStatePlan(new ExecutionPlan([$first, new ExecutionStep('later', 'test_later', [], ['first'])]));
    $this->service->answer($execution->id, $this->actor, 'Spain');
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('completed')->and($this->laterCalls)->toBe(2);
});
