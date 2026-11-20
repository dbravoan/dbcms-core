<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\AvailableCapabilities;
use DbCms\Core\Capabilities\CallableCapability;
use DbCms\Core\Capabilities\CapabilityExecutor;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Capabilities\Contracts\PlannerInterface;
use DbCms\Core\Capabilities\DTOs\ExecutionPlan;
use DbCms\Core\Capabilities\DTOs\ExecutionStep;
use DbCms\Core\Capabilities\ExecutionContext;
use DbCms\Core\Capabilities\PlanValidator;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Models\OAuthServiceAccount;
use DbCms\Core\Models\Orchestration\Execution;
use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Auth\ServiceAccountProvisioningService;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use DbCms\Core\Services\Orchestration\ExecutionService;
use DbCms\Core\Services\Orchestration\ResourceFingerprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Queue::fake();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $this->user = User::factory()->create();
    $this->user->roles()->attach($role);
    $this->actor = new ActorContext((int) $this->user->id);
    $this->service = app(ExecutionService::class);
    $this->count = 0;
    app(CapabilityRegistry::class)->register(new CallableCapability('test_read', 'Read', [], [], 'access:admin', 'read', fn (): array => ['content' => 'verified text']));
    app(CapabilityRegistry::class)->register(new CallableCapability('test_draft', 'Draft', ['content' => ['type' => 'string']], ['content'], 'manage:pages', 'write', function (array $args): array {
        $this->count++;

        return ['id' => $this->count, 'content' => $args['content']];
    }), effect: 'draft');
    app(CapabilityRegistry::class)->register(new CallableCapability('test_publish', 'Publish', ['type' => ['type' => 'string'], 'id' => ['type' => 'integer']], ['type', 'id'], 'manage:pages', 'write', function (array $args): array {
        $this->count++;

        return ['published' => true];
    }), effect: 'publish');
});

function nativePlan(ExecutionPlan $plan): void
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

test('human resource review exposes useful fields without credential hashes or hidden attributes', function (): void {
    $target = User::factory()->create(['remember_token' => 'private-review-token']);
    $preview = app(ResourceFingerprint::class)->preview('cms_resource_delete', ['resource' => 'user', 'id' => $target->id]);
    expect($preview['attributes']['email'])->toBe($target->email);
    expect($preview['attributes'])->not->toHaveKeys(['password', 'remember_token', 'is_service_account']);
    expect(json_encode($preview))->not->toContain($target->getRawOriginal('password'))->not->toContain('private-review-token');
});

test('native executor validates result references and checkpoints without repeating completed effects', function (): void {
    nativePlan(new ExecutionPlan([new ExecutionStep('read', 'test_read', []), new ExecutionStep('draft', 'test_draft', ['content' => ['$ref' => 'read.content']], ['read'])]));
    $execution = $this->service->start('Draft using read result', $this->actor, 'native-checkpoint');
    $this->service->run($execution->id);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('completed')->and($this->count)->toBe(1);
    expect($execution->steps()->where('key', 'draft')->first()->result['content'])->toBe('verified text');
});

test('idempotency returns the same execution but rejects a changed request', function (): void {
    nativePlan(new ExecutionPlan([new ExecutionStep('read', 'test_read', [])]));
    $one = $this->service->start('Read', $this->actor, 'same-key');
    $two = $this->service->start('Read', $this->actor, 'same-key');
    expect($two->id)->toBe($one->id);
    expect(fn () => $this->service->start('Changed', $this->actor, 'same-key'))->toThrow(HttpException::class);
});

test('invalid direct capability requests cannot reserve executions or complete on retry', function (string $capability, array $arguments): void {
    $executionCount = Execution::count();
    $stepCount = DB::table('cms_execution_steps')->count();
    for ($attempt = 0; $attempt < 2; $attempt++) {
        expect(fn () => $this->service->startCapability($capability, $arguments, $this->actor))->toThrow(ValidationException::class);
        expect(Execution::count())->toBe($executionCount);
        expect(DB::table('cms_execution_steps')->count())->toBe($stepCount);
    }
    expect($this->count)->toBe(0);
})->with([
    'missing required argument' => ['test_draft', []],
    'invalid argument type' => ['test_draft', ['content' => 42]],
    'forged confirmation' => ['test_publish', ['type' => 'page', 'id' => 0, 'confirm' => true]],
    'unknown capability' => ['unregistered_capability', []],
]);

test('model confirmation cannot publish and approval is bound to actor and content version', function (): void {
    $page = Page::create(['is_published' => false, 'template' => 'default', 'layout' => 'app']);
    $translation = PageTranslation::create(['page_id' => $page->id, 'locale' => 'es', 'title' => 'Original', 'slug' => 'original']);
    $args = ['type' => 'page', 'id' => $page->id];
    expect(app(CapabilityExecutor::class)->execute('test_publish', $args + ['confirm' => true], $this->actor))->toHaveKey('confirmation_required');
    $execution = $this->service->startCapability('test_publish', $args, $this->actor);
    $step = $execution->steps()->first();
    expect($execution->status)->toBe('approval_required')->and($this->count)->toBe(0);
    $other = User::factory()->create();
    expect(fn () => $this->service->approve($execution->id, new ActorContext($other->id), 'action', $step->binding_hash))->toThrow(HttpException::class);
    $translation->update(['title' => 'Changed after review']);
    expect(fn () => $this->service->approve($execution->id, $this->actor, 'action', $step->binding_hash))->toThrow(HttpException::class);
    $this->service->run($execution->id);
    $step->refresh();
    $this->service->approve($execution->id, $this->actor, 'action', $step->binding_hash);
    $this->service->run($execution->id);
    $this->service->run($execution->id);
    expect($this->count)->toBe(1)->and($execution->refresh()->status)->toBe('completed');
});

test('OAuth origins cannot approve through a tool argument or API', function (): void {
    $execution = $this->service->startCapability('test_publish', ['type' => 'page', 'id' => 0], $this->actor);
    $step = $execution->steps()->first();
    expect(fn () => $this->service->approve($execution->id, new ActorContext($this->user->id, 'mcp'), 'action', $step->binding_hash))->toThrow(HttpException::class);
    $this->actingAs($this->user)->postJson('/api/orchestration/'.$execution->id.'/approve', ['step' => 'action', 'binding_hash' => $step->binding_hash])->assertNotFound();
});

test('a cancelled execution cannot be revived by a stale approval form', function (): void {
    $execution = $this->service->startCapability('test_publish', ['type' => 'page', 'id' => 0], $this->actor);
    $step = $execution->steps()->first();
    $this->service->cancel($execution->id, $this->actor);
    expect(fn () => $this->service->approve($execution->id, $this->actor, 'action', $step->binding_hash))->toThrow(HttpException::class);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('cancelled')->and($this->count)->toBe(0);
});

test('permissions are rechecked when a job resumes after its actor is deleted', function (): void {
    nativePlan(new ExecutionPlan([new ExecutionStep('draft', 'test_draft', ['content' => 'text'])]));
    $execution = $this->service->start('Draft', $this->actor, 'delete-actor');
    $replacementAdmin = User::factory()->create();
    $replacementAdmin->roles()->attach(Role::where('slug', 'admin')->sole());
    $this->user->delete();
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('failed')->and($this->count)->toBe(0);
});

test('interrupted started steps are marked uncertain without repeating generation', function (): void {
    nativePlan(new ExecutionPlan([new ExecutionStep('draft', 'test_draft', ['content' => 'text'])]));
    $execution = $this->service->start('Draft', $this->actor, 'uncertain-step');
    $execution->steps()->first()->update(['status' => 'running', 'started_at' => now()->subMinutes(30)]);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('uncertain')->and($this->count)->toBe(0);
    $this->service->resume($execution->id, $this->actor);
    expect($execution->refresh()->status)->toBe('uncertain');
});

test('remote checkpoints survive revoked permissions and resume without another generation', function (): void {
    $generations = 0;
    $polls = 0;
    app(CapabilityRegistry::class)->register(new CallableCapability('test_remote', 'Remote generation', ['provider_request_id' => ['type' => 'string'], 'provider' => ['type' => 'string']], [], 'manage:pages', 'write', function (array $args) use (&$generations, &$polls): array {
        if (! isset($args['provider_request_id'])) {
            $generations++;

            return ['pending' => true, 'provider' => 'remote', 'provider_request_id' => 'remote-existing'];
        }
        $polls++;
        expect($args['provider_request_id'])->toBe('remote-existing');

        return ['content' => 'completed remote result'];
    }), effect: 'generation');
    $execution = $this->service->startCapability('test_remote', [], $this->actor);
    expect($execution->status)->toBe('pending_remote');
    $this->user->roles()->detach();
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('failed');
    $this->user->roles()->attach(Role::where('slug', 'admin')->firstOrFail());
    $this->service->resume($execution->id, $this->actor);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('completed')->and($generations)->toBe(1)->and($polls)->toBe(1);
});

test('missing required arguments reject the complete plan before any action', function (): void {
    nativePlan(new ExecutionPlan([new ExecutionStep('read', 'test_read', []), new ExecutionStep('draft', 'test_draft', [])]));
    $execution = $this->service->start('Invalid', $this->actor, 'invalid-plan');
    expect($execution->status)->toBe('failed')->and($execution->steps()->count())->toBe(0);
});

test('private executions remain private', function (): void {
    $none = User::factory()->create();
    nativePlan(new ExecutionPlan([new ExecutionStep('read', 'test_read', [])]));
    $execution = $this->service->start('Read', $this->actor, 'private-run');
    $this->actingAs($none)->getJson('/admin/executions/'.$execution->id)->assertForbidden();
});

test('CMS catalog and concrete executor work with MCP disabled', function (): void {
    app(CoreFeatureSettings::class)->set('mcp', 'is_enabled', false);
    $manifest = app(AvailableCapabilities::class)->manifest($this->actor);
    expect(array_column($manifest, 'name'))->toContain('cms_entity_create');
    expect(app(CapabilityExecutor::class)->execute('test_read', [], $this->actor))->toBe(['content' => 'verified text']);
});

test('an interrupted planning reservation is replanned rather than completed empty', function (): void {
    $execution = Execution::create(['id' => (string) Str::uuid(), 'user_id' => $this->user->id, 'idempotency_key' => 'interrupted-plan', 'request_hash' => hash('sha256', 'interrupted'), 'instruction' => 'Draft', 'actor' => $this->actor->toArray(), 'status' => 'planning']);
    nativePlan(new ExecutionPlan([new ExecutionStep('draft', 'test_draft', ['content' => 'recovered'])]));
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('planning')->and($this->count)->toBe(0);
    $this->service->resume($execution->id, $this->actor);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('completed')->and($this->count)->toBe(1);
});

test('revoking the operation permission also revokes access to its private results', function (): void {
    nativePlan(new ExecutionPlan([new ExecutionStep('draft', 'test_draft', ['content' => 'private'])]));
    $execution = $this->service->start('Draft', $this->actor, 'revoke-results');
    $this->service->run($execution->id);
    $this->user->roles()->detach();
    expect(fn () => $this->service->owned($execution->id, $this->actor))->toThrow(HttpException::class);
});

test('a direct MCP approval can be reviewed when natural language orchestration is disabled', function (): void {
    $execution = $this->service->startCapability('test_publish', ['type' => 'page', 'id' => 0], $this->actor);
    $this->actingAs($this->user)->get('/admin/executions/'.$execution->id)->assertSuccessful()->assertSee('Revisa la acción solicitada');
    $this->actingAs($this->user)->getJson('/admin/executions/'.$execution->id.'/status')->assertSuccessful()->assertJsonPath('status', 'approval_required');
});

test('a model plan containing both actions and a question cannot erase executed work on clarification', function (): void {
    expect(fn () => app(PlanValidator::class)->validate(['steps' => [['id' => 'read', 'capability' => 'test_read', 'arguments' => []]], 'question' => 'Which page?'], ['test_read']))->toThrow(InvalidArgumentException::class);
});

test('idempotent lookups still enforce live operation permissions and market identity', function (): void {
    nativePlan(new ExecutionPlan([new ExecutionStep('draft', 'test_draft', ['content' => 'private'])]));
    $this->service->start('Draft', $this->actor, 'private-idempotent');
    expect(fn () => $this->service->start('Draft', new ActorContext($this->user->id, market: 'fr'), 'private-idempotent'))->toThrow(HttpException::class);
    $this->user->roles()->detach();
    $use = Permission::firstOrCreate(['slug' => 'use:ai'], ['name' => 'Use AI']);
    $role = Role::firstOrCreate(['slug' => 'ai-only'], ['name' => 'AI only']);
    $role->permissions()->attach($use);
    $this->user->roles()->attach($role);
    expect(fn () => $this->service->start('Draft', $this->actor, 'private-idempotent'))->toThrow(HttpException::class);
});

test('a missing result field fails the dependent step without replaying earlier effects', function (): void {
    nativePlan(new ExecutionPlan([new ExecutionStep('read', 'test_read', []), new ExecutionStep('draft', 'test_draft', ['content' => ['$ref' => 'read.missing']], ['read'])]));
    $execution = $this->service->start('Read missing field', $this->actor, 'missing-result');
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('failed')->and($this->count)->toBe(0);
    expect($execution->steps()->where('key', 'read')->first()->status)->toBe('completed');
    expect($execution->steps()->where('key', 'draft')->first()->error)->toContain('resultado');
});

test('a human approves a service execution without replacing the original service identity', function (): void {
    $client = app(ServiceAccountProvisioningService::class)->create('Review service', ['mcp:access', 'mcp:read', 'mcp:write', 'm2m:service'], Role::where('slug', 'admin')->firstOrFail()->id);
    $binding = OAuthServiceAccount::findOrFail($client->id);
    $token = Passport::token()->create(['id' => bin2hex(random_bytes(40)), 'user_id' => null, 'client_id' => $client->id, 'scopes' => $binding->allowed_scopes, 'revoked' => false, 'expires_at' => now()->addHour()]);
    $actor = new ActorContext((int) $binding->user_id, 'mcp', $binding->allowed_scopes, (string) $client->id, (string) $token->id, true);
    $seen = null;
    app(CapabilityRegistry::class)->register(new CallableCapability('service_publish', 'Publish', [], [], 'manage:pages', 'write', function () use (&$seen): array {
        $seen = app(ExecutionContext::class)->actor?->userId;

        return ['published' => true];
    }), effect: 'publish');
    $execution = $this->service->startCapability('service_publish', [], $actor);
    $step = $execution->steps()->first();
    $this->service->approve($execution->id, $this->actor, 'action', $step->binding_hash);
    $this->service->run($execution->id);
    expect($execution->refresh()->status)->toBe('completed')->and($seen)->toBe((int) $binding->user_id);
    expect(DB::table('cms_execution_approvals')->where('step_id', $step->id)->first()->user_id)->toBe($this->user->id);
});
