<?php

declare(strict_types=1);

namespace DbCms\Core\Console\Commands;

use DbCms\Core\Services\Media\MediaMigrationService;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Console\Command;

class MigrateMediaToExternalCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'media:migrate-to-external
                            {--dry-run : Muestra el plan sin subir ni reescribir nada}
                            {--force : Re-sube los archivos aunque ya existan con el mismo tamaño}
                            {--no-rewrite-urls : Copia los archivos pero no reescribe las URLs almacenadas}
                            {--prune-local : Borra el archivo local tras una subida verificada}
                            {--only= : Limita la migración a estos archivos (separados por comas)}
                            {--rollback : Devuelve las URLs almacenadas a la ubicación local}';

    /**
     * @var string
     */
    protected $description = 'Migra los documentos locales al almacenamiento externo (S3 / OVH / R2) y reescribe sus URLs.';

    public function handle(MediaMigrationService $service, ExternalStorageService $storage): int
    {
        if ((bool) $this->option('rollback')) {
            return $this->rollback($service);
        }

        if (! $storage->isConfigured()) {
            $this->error('[ERROR] No hay un almacenamiento externo configurado. Configúralo en /admin/connections.');

            return self::FAILURE;
        }

        $only = $this->parseOnly();
        $dryRun = (bool) $this->option('dry-run');

        $total = count($service->candidates($only));
        $this->info(sprintf(
            '%s %d archivo(s) en <fg=cyan>%s</>',
            $dryRun ? 'Simulando migración de' : 'Migrando',
            $total,
            $storage->provider()
        ));
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = $service->migrate(
            rewriteUrls: ! (bool) $this->option('no-rewrite-urls'),
            pruneLocal: (bool) $this->option('prune-local'),
            force: (bool) $this->option('force'),
            only: $only,
            dryRun: $dryRun,
            onProgress: function () use ($bar): void {
                $bar->advance();
            },
        );

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Migrados', 'Omitidos', 'Fallidos', 'Filas reescritas', 'Locales borrados'],
            [[$stats['migrated'], $stats['skipped'], $stats['failed'], $stats['rewritten'], $stats['pruned']]]
        );

        foreach ($stats['errors'] as $error) {
            $this->warn(' - '.$error);
        }

        if ($dryRun) {
            $this->comment('Simulación completada: no se ha subido ni modificado nada.');
        } elseif ($stats['failed'] > 0) {
            $this->warn('Migración finalizada con errores. Revisa los avisos anteriores.');
        } else {
            $this->info('[OK] Migración completada correctamente.');
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function rollback(MediaMigrationService $service): int
    {
        $result = $service->rollback();

        $this->info(sprintf(
            '[OK] URLs locales restauradas. %d de %d archivo(s) actualizados.',
            $result['rewritten'],
            $result['total']
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function parseOnly(): array
    {
        $raw = (string) $this->option('only');

        if (trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $value): bool => $value !== ''));
    }
}
