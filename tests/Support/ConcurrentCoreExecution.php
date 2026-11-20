<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\CallableCapability;
use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Jobs\Orchestration\RunExecution;
use DbCms\Core\Models\Orchestration\Execution;
use DbCms\Core\Services\Orchestration\ExecutionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IsolatedTestDatabase;

require dirname(__DIR__, 2).'/.build/app/vendor/autoload.php';

try {
    [$script, $executionId, $directory] = $argv;
    $app = require dirname(__DIR__, 2).'/.build/app/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    IsolatedTestDatabase::assertConnection();
    Queue::fake();
    app(CapabilityRegistry::class)->register(new CallableCapability('concurrent_remote', 'Concurrent remote fixture', [], [], 'manage:pages', 'write', function () use ($directory): array {
        file_put_contents($directory.'/started.json', json_encode(['pid' => getmypid(), 'connection_id' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id], JSON_THROW_ON_ERROR));
        $deadline = microtime(true) + 20;
        while (! is_file($directory.'/release')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Execution result barrier timed out.');
            }
            usleep(10000);
        }

        return ['pending' => true, 'provider' => 'test', 'provider_request_id' => 'already-started-request'];
    }), effect: 'generation');
    app(CapabilityRegistry::class)->register(new CallableCapability('concurrent_later', 'Later fixture', [], [], 'manage:pages', 'write', function () use ($directory): array {
        file_put_contents($directory.'/later', 'executed');

        return ['success' => true];
    }), effect: 'draft');
    app(ExecutionService::class)->run($executionId);
    echo json_encode(['status' => Execution::findOrFail($executionId)->status, 'queued_jobs' => Queue::pushed(RunExecution::class)->count()], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    // Keep SQL, connection credentials and application secrets out of diagnostics.
    fwrite(STDERR, $error::class);
    exit(2);
}
