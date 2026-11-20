<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\ActorContext;
use DbCms\Core\Capabilities\CallableCapability;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\Orchestration\Execution;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Orchestration\ExecutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\IsolatedTestDatabase;
use Tests\Support\PrivacyFixtures;
use Tests\TestCase;

uses(TestCase::class);

it('preserves cancellation while an independent worker returns a remote checkpoint', function (): void {
    // Check the explicitly selected disposable database before running any DDL.
    IsolatedTestDatabase::assertConnection();
    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', PrivacyFixtures::processingFor([]), 'legal', 'json');
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));
    $actor = new ActorContext((int) $user->id);
    foreach (['concurrent_remote', 'concurrent_later'] as $name) {
        app(CapabilityRegistry::class)->register(new CallableCapability($name, 'Concurrent fixture', [], [], 'manage:pages', 'write', fn (): array => throw new RuntimeException('A cancelled execution must not start another capability.')), effect: 'generation');
    }
    $execution = Execution::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'idempotency_key' => 'real-cancellation', 'request_hash' => hash('sha256', 'real-cancellation'), 'instruction' => 'Remote fixture', 'actor' => $actor->toArray(), 'status' => 'pending']);
    $execution->steps()->create(['key' => 'remote', 'position' => 0, 'capability' => 'concurrent_remote', 'arguments' => [], 'dependencies' => []]);
    $execution->steps()->create(['key' => 'later', 'position' => 1, 'capability' => 'concurrent_later', 'arguments' => [], 'dependencies' => ['remote']]);
    $directory = sys_get_temp_dir().'/dbcms-execution-race-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $process = null;
    try {
        $connection = config('database.connections.mysql');
        $environment = [
            'APP_ENV' => 'testing', 'CMS_MODULES' => '', 'DB_CONNECTION' => 'mysql', 'DB_URL' => false,
            'DB_DATABASE' => (string) $connection['database'], 'DB_HOST' => (string) $connection['host'],
            'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) $connection['password'],
            'DBCMS_TEST_ISOLATED' => '1', 'DBCMS_TEST_DATABASE' => (string) $connection['database'],
            'APP_CONFIG_CACHE' => $directory.'/config.php', 'APP_ROUTES_CACHE' => $directory.'/routes.php',
            'APP_EVENTS_CACHE' => $directory.'/events.php', 'APP_PACKAGES_CACHE' => $directory.'/packages.php', 'APP_SERVICES_CACHE' => $directory.'/services.php',
            'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
        ];
        $process = new Process([PHP_BINARY, PACKAGE_ROOT.'/tests/Support/ConcurrentCoreExecution.php', $execution->id, $directory], base_path(), $environment, timeout: 30);
        $process->start();
        $deadline = microtime(true) + 15;
        while (! is_file($directory.'/started.json')) {
            if (! $process->isRunning()) {
                throw new RuntimeException('Execution worker stopped before the barrier: '.$process->getErrorOutput());
            }
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Execution worker did not reach the capability barrier.');
            }
            usleep(10000);
        }
        $started = json_decode(file_get_contents($directory.'/started.json'), true, flags: JSON_THROW_ON_ERROR);
        $parentConnectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        expect($started['pid'])->not->toBe(getmypid())->and($started['connection_id'])->not->toBe($parentConnectionId);
        expect($execution->fresh()->status)->toBe('running');
        app(ExecutionService::class)->cancel($execution->id, $actor);
        expect($execution->fresh()->status)->toBe('cancelled');
        file_put_contents($directory.'/release', 'return the remote checkpoint');
        expect($process->wait())->toBe(0);
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        expect($result['queued_jobs'])->toBe(0)->and($result['status'])->toBe('cancelled');
        $step = $execution->steps()->where('key', 'remote')->sole();
        expect($step->status)->toBe('pending_remote')
            ->and($step->resolved_arguments['provider_request_id'])->toBe('already-started-request')
            ->and($step->result['provider_request_id'])->toBe('already-started-request')
            ->and($execution->steps()->where('key', 'later')->sole()->status)->toBe('pending')
            ->and(is_file($directory.'/later'))->toBeFalse();
        app(ExecutionService::class)->run($execution->id);
        expect($execution->fresh()->status)->toBe('cancelled');
    } finally {
        if ($process?->isRunning()) {
            $process->stop(1);
        }
        $execution->delete();
        User::withoutEvents(fn () => $user->delete());
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});
