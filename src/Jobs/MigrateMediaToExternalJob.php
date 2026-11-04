<?php

declare(strict_types=1);

namespace DbCms\Core\Jobs;

use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Models\MediaMigrationRun;
use DbCms\Core\Services\Media\MediaMigrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs the media migration out of band with progress tracking, so the backoffice
 * can poll the run status and the owner gets notified when it finishes.
 */
final class MigrateMediaToExternalJob implements ShouldQueue
{
    use Queueable;

    /**
     * Large libraries can take a while; keep the worker alive.
     */
    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(MediaMigrationService $service): void
    {
        $run = MediaMigrationRun::find($this->runId);

        if ($run === null || $run->isFinished()) {
            return;
        }

        $options = $run->options ?? [];

        $run->update([
            'status' => MediaMigrationRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $stats = $service->migrate(
                rewriteUrls: (bool) ($options['rewrite_urls'] ?? true),
                pruneLocal: (bool) ($options['prune_local'] ?? false),
                force: (bool) ($options['force'] ?? false),
                only: $this->only($options),
                dryRun: (bool) ($options['dry_run'] ?? false),
                onProgress: function (string $path, array $stats) use ($run): void {
                    $this->persistProgress($run, $stats);
                },
            );

            $run->update([
                'status' => MediaMigrationRun::STATUS_COMPLETED,
                'total' => $stats['total'],
                'processed' => $stats['migrated'] + $stats['skipped'] + $stats['failed'],
                'migrated' => $stats['migrated'],
                'skipped' => $stats['skipped'],
                'failed' => $stats['failed'],
                'rewritten' => $stats['rewritten'],
                'pruned' => $stats['pruned'],
                'errors' => $stats['errors'],
                'message' => $this->summary($stats),
                'finished_at' => now(),
            ]);

            $this->notifyOwner($run, true);
        } catch (Throwable $e) {
            $run->update([
                'status' => MediaMigrationRun::STATUS_FAILED,
                'message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $this->notifyOwner($run, false, $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function only(array $options): array
    {
        $only = $options['only'] ?? [];

        if (is_string($only)) {
            $only = array_filter(array_map('trim', explode(',', $only)));
        }

        return is_array($only) ? array_values(array_map('strval', $only)) : [];
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function persistProgress(MediaMigrationRun $run, array $stats): void
    {
        $processed = $stats['migrated'] + $stats['skipped'] + $stats['failed'];

        // Throttle writes: only persist every 25 files or on the last one.
        if ($processed % 25 !== 0 && $processed !== $run->total) {
            return;
        }

        $run->update([
            'total' => max($run->total, $stats['total']),
            'processed' => $processed,
            'migrated' => $stats['migrated'],
            'skipped' => $stats['skipped'],
            'failed' => $stats['failed'],
            'pruned' => $stats['pruned'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function summary(array $stats): string
    {
        return sprintf(
            '%d migrados, %d omitidos, %d fallidos, %d filas reescritas%s.',
            (int) ($stats['migrated'] ?? 0),
            (int) ($stats['skipped'] ?? 0),
            (int) ($stats['failed'] ?? 0),
            (int) ($stats['rewritten'] ?? 0),
            (bool) ($stats['pruned'] ?? false) ? sprintf(', %d locales borrados', (int) $stats['pruned']) : ''
        );
    }

    private function notifyOwner(MediaMigrationRun $run, bool $success, ?string $error = null): void
    {
        if ($run->user_id === null) {
            return;
        }

        try {
            app(ModuleNotificationServiceInterface::class)->notify(
                module: 'media',
                type: $success ? 'success' : 'error',
                title: $success ? 'Migración de archivos completada' : 'Migración de archivos con errores',
                message: $success ? (string) $run->message : 'Error: '.($error ?? 'desconocido'),
                actionUrl: route('admin.connections.index', absolute: false),
                data: ['run_id' => $run->id],
                sendEmail: false,
                userId: (int) $run->user_id,
            );
        } catch (Throwable) {
            // Notifications are best-effort.
        }
    }
}
