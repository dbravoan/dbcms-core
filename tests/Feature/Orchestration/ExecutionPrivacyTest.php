<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\CallableCapability;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\Orchestration\Execution;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Orchestration\ExecutionPrivacyService;
use DbCms\Core\Services\Orchestration\ExecutionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function retainedExecution(string $status = 'completed', string $stepStatus = 'completed'): Execution
{
    $user = User::factory()->create();
    $execution = Execution::create([
        'id' => (string) Str::uuid(), 'user_id' => $user->id, 'idempotency_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64),
        'instruction' => 'Private editorial instruction', 'actor' => (new ActorContext($user->id))->toArray(), 'status' => $status,
        'created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60),
    ]);
    $execution->steps()->create([
        'key' => 'action', 'position' => 0, 'capability' => 'privacy_fixture', 'arguments' => ['content' => 'Private request'], 'dependencies' => [],
        'status' => $stepStatus, 'binding_hash' => str_repeat('b', 64), 'result' => ['content' => 'Private response'],
        'created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60),
    ]);

    return $execution;
}

test('missing execution processing prevents persistence and external calls', function (): void {
    Http::fake();
    $user = User::factory()->create();
    app(CapabilityRegistry::class)->register(new CallableCapability('privacy_fixture', 'Fixture', [], [], 'access:admin', 'read', fn (): array => Http::get('https://remote.invalid')->json()));
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [], 'legal', 'json');
    expect(fn () => app(ExecutionService::class)->startCapability('privacy_fixture', [], new ActorContext($user->id)))->toThrow(ValidationException::class);
    expect(Execution::query()->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('pruning removes expired completed aggregates and retains pending or uncertain evidence', function (): void {
    $completed = retainedExecution();
    $step = $completed->steps()->firstOrFail();
    DB::table('cms_execution_approvals')->insert(['step_id' => $step->id, 'user_id' => $completed->user_id, 'actor_user_id' => $completed->user_id, 'plan_version' => 1, 'binding_hash' => str_repeat('b', 64), 'approved_at' => now()->subDays(60), 'consumed_at' => now()->subDays(60), 'created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)]);
    $uncertain = retainedExecution('uncertain', 'uncertain');
    $remote = retainedExecution('cancelled', 'pending_remote');
    $before = $remote->steps()->firstOrFail()->getAttributes();
    expect(app(ExecutionPrivacyService::class)->prune())->toBe(1)
        ->and($completed->fresh())->toBeNull()
        ->and(DB::table('cms_execution_approvals')->where('step_id', $step->id)->exists())->toBeFalse()
        ->and($uncertain->fresh())->not->toBeNull()
        ->and($remote->steps()->firstOrFail()->getAttributes())->toBe($before);
});

test('active worker locks and unconsumed approvals block erasure without touching payloads', function (): void {
    $execution = retainedExecution();
    $privacy = app(ExecutionPrivacyService::class);
    $lock = Cache::lock('cms-execution:'.$execution->id, 1260);
    expect($lock->get())->toBeTrue();
    try {
        expect(fn () => $privacy->erase([$execution->id]))->toThrow(DomainException::class);
    } finally {
        $lock->release();
    }
    $step = $execution->steps()->firstOrFail();
    DB::table('cms_execution_approvals')->insert(['step_id' => $step->id, 'plan_version' => 1, 'binding_hash' => str_repeat('b', 64), 'approved_at' => now()->subDays(60), 'created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)]);
    expect(fn () => $privacy->erase([$execution->id]))->toThrow(DomainException::class)
        ->and($execution->fresh()->instruction)->toBe('Private editorial instruction')
        ->and($step->fresh()->binding_hash)->toBe(str_repeat('b', 64));
    expect($privacy->erase([$execution->id], true))->toBe(1);
});

test('recent step activity prevents retention from using an older parent timestamp', function (): void {
    $execution = retainedExecution();
    $execution->steps()->firstOrFail()->update(['result' => ['content' => 'Recently reconciled']]);
    expect(fn () => app(ExecutionPrivacyService::class)->erase([$execution->id]))->toThrow(DomainException::class)
        ->and($execution->fresh())->not->toBeNull();
});

test('execution exports require verified scope and contain the selected approval aggregate privately', function (): void {
    Storage::fake('local');
    $execution = retainedExecution();
    $this->artisan('executions:privacy', ['action' => 'export', '--id' => [$execution->id]])->assertFailed();
    expect(Storage::disk('local')->allFiles())->toBe([]);
    $this->artisan('executions:privacy', ['action' => 'export', '--id' => [$execution->id], '--verified' => true])->assertSuccessful();
    $files = Storage::disk('local')->allFiles('privacy');
    expect($files)->toHaveCount(1);
    $export = json_decode(Storage::disk('local')->get($files[0]), true, flags: JSON_THROW_ON_ERROR);
    expect($export[0]['id'])->toBe($execution->id)
        ->and($export[0]['actor']['userId'])->toBe($execution->user_id)
        ->and($export[0]['steps'][0]['arguments']['content'])->toBe('Private request');
});

test('withdrawn processing stops an existing queued execution before any remote effect', function (): void {
    Http::fake();
    $execution = retainedExecution('pending', 'pending');
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [], 'legal', 'json');
    expect(fn () => app(ExecutionService::class)->run($execution->id))->toThrow(ValidationException::class)
        ->and($execution->fresh()->status)->toBe('pending')
        ->and($execution->steps()->firstOrFail()->arguments)->toBe(['content' => 'Private request']);
    Http::assertNothingSent();
});

test('cancellation does not release failed remote checkpoints even with manual retention review', function (): void {
    $execution = retainedExecution('cancelled', 'failed');
    $step = $execution->steps()->firstOrFail();
    $step->forceFill(['result' => ['provider_request_id' => 'remote-receipt'], 'updated_at' => now()->subDays(60)])->save();
    expect(fn () => app(ExecutionPrivacyService::class)->erase([$execution->id], true))->toThrow(DomainException::class)
        ->and($step->fresh()->result)->toBe(['provider_request_id' => 'remote-receipt']);
});
