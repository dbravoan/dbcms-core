<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use DbCms\Core\Jobs\MigrateMediaToExternalJob;
use DbCms\Core\Models\MediaFile;
use DbCms\Core\Models\MediaMigrationRun;
use DbCms\Core\Services\Privacy\LegalPageService;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Copies local uploads to the configured S3-compatible disk and (optionally)
 * rewrites the stored media URLs so they keep pointing at the right file.
 *
 * Every phase is idempotent and safe to re-run. Nothing is ever deleted unless
 * `pruneLocal` is explicitly requested, and a local file is only removed after
 * its upload has been verified.
 */
final class MediaMigrationService
{
    /**
     * Database columns that may hold media URLs.
     *
     * @var array<int, array{table: string, column: string, type: string}>
     */
    private const TARGETS = [
        ['table' => 'media_files', 'column' => 'url', 'type' => 'string'],
        ['table' => 'settings', 'column' => 'value', 'type' => 'string'],
        ['table' => 'posts', 'column' => 'featured_image', 'type' => 'string'],
        ['table' => 'post_translations', 'column' => 'content', 'type' => 'string'],
        ['table' => 'post_section_translations', 'column' => 'content_json', 'type' => 'json'],
        ['table' => 'page_sections', 'column' => 'styling_json', 'type' => 'json'],
        ['table' => 'section_translations', 'column' => 'content_json', 'type' => 'json'],
        ['table' => 'product_categories', 'column' => 'image', 'type' => 'string'],
        ['table' => 'products', 'column' => 'featured_image', 'type' => 'string'],
        ['table' => 'products', 'column' => 'gallery_images', 'type' => 'json'],
        ['table' => 'product_translations', 'column' => 'description', 'type' => 'string'],
        ['table' => 'product_section_translations', 'column' => 'content_json', 'type' => 'json'],
        ['table' => 'gallery_items', 'column' => 'image_url', 'type' => 'string'],
    ];

    public function __construct(
        private readonly ExternalStorageService $externalStorage,
        private readonly MediaUrlResolver $urlResolver
    ) {}

    /**
     * Queues a new migration run and returns its tracker.
     *
     * @param  array<string, mixed>  $options
     */
    public function start(array $options, ?int $userId): MediaMigrationRun
    {
        app(LegalPageService::class)->requireProcessing('core');
        $only = $options['only'] ?? [];
        $only = is_string($only)
            ? array_values(array_filter(array_map('trim', explode(',', $only))))
            : (is_array($only) ? $only : []);

        $run = MediaMigrationRun::create([
            'status' => MediaMigrationRun::STATUS_PENDING,
            'options' => $options,
            'total' => count($this->candidates($only)),
            'user_id' => $userId,
        ]);

        MigrateMediaToExternalJob::dispatch($run->id);

        return $run;
    }

    /**
     * The run currently pending or running, if any.
     */
    public function activeRun(): ?MediaMigrationRun
    {
        return MediaMigrationRun::query()
            ->whereIn('status', [MediaMigrationRun::STATUS_PENDING, MediaMigrationRun::STATUS_RUNNING])
            ->latest('id')
            ->first();
    }

    /**
     * The most recent run, regardless of status.
     */
    public function latestRun(): ?MediaMigrationRun
    {
        return MediaMigrationRun::query()->latest('id')->first();
    }

    /**
     * Resolves the candidate files to migrate.
     *
     * @param  array<int, string>  $only  Restrict to these relative paths.
     * @return array<string, string> Relative path => absolute local path.
     */
    public function candidates(array $only = []): array
    {
        $dir = $this->urlResolver->uploadDir();
        $result = [];

        if (File::isDirectory($dir)) {
            foreach (File::allFiles($dir) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                $result[$relative] = $file->getPathname();
            }
        }

        // Records may exist without a local file (already migrated / external only).
        if (Schema::hasTable('media_files')) {
            foreach (MediaFile::query()->pluck('filename') as $filename) {
                $filename = (string) $filename;
                if ($filename !== '' && ! array_key_exists($filename, $result)) {
                    $result[$filename] = $this->urlResolver->localPath($filename);
                }
            }
        }

        if ($only !== []) {
            $filtered = [];
            foreach ($result as $path => $absolute) {
                if (in_array($path, $only, true)) {
                    $filtered[$path] = $absolute;
                }
            }

            return $filtered;
        }

        return $result;
    }

    /**
     * Runs the migration.
     *
     * @param  array<int, string>  $only
     * @param  \Closure(string, array<string, int>): void|null  $onProgress
     * @param  \Closure(): bool|null  $shouldStop
     * @return array{
     *     total: int,
     *     migrated: int,
     *     skipped: int,
     *     failed: int,
     *     rewritten: int,
     *     pruned: int,
     *     urls: array<string, string>,
     *     errors: array<int, string>
     * }
     */
    public function migrate(
        bool $rewriteUrls = true,
        bool $pruneLocal = false,
        bool $force = false,
        array $only = [],
        bool $dryRun = false,
        ?\Closure $onProgress = null,
        ?\Closure $shouldStop = null,
    ): array {
        $candidates = $this->candidates($only);

        $stats = [
            'total' => count($candidates),
            'migrated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'rewritten' => 0,
            'pruned' => 0,
            'urls' => [],
            'errors' => [],
        ];

        $urlMap = [];

        foreach ($candidates as $path => $absolute) {
            if ($shouldStop !== null && $shouldStop()) {
                break;
            }

            $newUrl = $this->externalStorage->url($path);

            if (! is_file($absolute)) {
                // Nothing local to upload: only rewrite when the object exists remotely.
                if ($this->externalStorage->exists($path)) {
                    $urlMap[$path] = $newUrl;
                    $stats['skipped']++;
                } else {
                    $stats['failed']++;
                    $stats['errors'][] = "[{$path}] no se encontró el archivo local ni en el almacenamiento externo.";
                }

                $onProgress?->__invoke($path, $stats);

                continue;
            }

            try {
                $alreadyRemote = $this->externalStorage->exists($path);
                $sameSize = $alreadyRemote && $this->remoteSize($path) === (int) filesize($absolute);

                if ($alreadyRemote && $sameSize && ! $force) {
                    $urlMap[$path] = $newUrl;
                    $stats['skipped']++;
                    $onProgress?->__invoke($path, $stats);

                    continue;
                }

                if ($dryRun) {
                    $urlMap[$path] = $newUrl;
                    $stats['migrated']++;
                    $onProgress?->__invoke($path, $stats);

                    continue;
                }

                $this->upload($absolute, $path);

                if (! $this->externalStorage->exists($path)) {
                    throw new RuntimeException('la verificación de subida falló.');
                }

                $urlMap[$path] = $newUrl;
                $stats['migrated']++;

                if ($pruneLocal) {
                    File::delete($absolute);
                    $stats['pruned']++;
                }
            } catch (Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = "[{$path}] ".$e->getMessage();
            }

            $onProgress?->__invoke($path, $stats);
        }

        if ($rewriteUrls && ! $dryRun && $urlMap !== []) {
            $stats['rewritten'] = $this->rewriteDatabase($urlMap, true);
        }

        $stats['urls'] = $urlMap;

        return $stats;
    }

    /**
     * Rewrites stored external URLs back to the local `/uploads` location.
     *
     * @return array{rewritten: int, total: int}
     */
    public function rollback(): array
    {
        $map = [];

        foreach ($this->candidates() as $path => $absolute) {
            $map[$path] = $this->urlResolver->localUrl($path);
        }

        if ($map === []) {
            return ['rewritten' => 0, 'total' => 0];
        }

        return ['rewritten' => $this->rewriteDatabase($map, false), 'total' => count($map)];
    }

    /**
     * @param  array<string, string>  $urlMap  Relative path => replacement URL.
     */
    private function rewriteDatabase(array $urlMap, bool $matchUploadsPath): int
    {
        $touched = 0;

        foreach (self::TARGETS as $target) {
            $table = $target['table'];
            $column = $target['column'];
            $type = $target['type'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $touched += $this->rewriteColumn($table, $column, $type, $urlMap, $matchUploadsPath);
        }

        return $touched;
    }

    /**
     * @param  array<string, string>  $urlMap
     */
    private function rewriteColumn(string $table, string $column, string $type, array $urlMap, bool $matchUploadsPath): int
    {
        $touched = 0;

        DB::table($table)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column, $type, $urlMap, $matchUploadsPath, &$touched): void {
                foreach ($rows as $row) {
                    $record = (array) $row;
                    $original = $record[$column] ?? null;

                    if (! is_string($original) || $original === '') {
                        continue;
                    }

                    $updated = $type === 'json'
                        ? $this->replaceInJson($original, $urlMap, $matchUploadsPath)
                        : $this->replaceInString($original, $urlMap, $matchUploadsPath);

                    if ($updated === null || $updated === $original) {
                        continue;
                    }

                    DB::table($table)->where('id', $record['id'])->update([$column => $updated]);
                    $touched++;
                }
            });

        return $touched;
    }

    /**
     * Rewrites JSON content. Returns null when nothing actually changed, so the
     * original (already normalized by MySQL) value is left untouched.
     *
     * @param  array<string, string>  $urlMap
     */
    private function replaceInJson(string $json, array $urlMap, bool $matchUploadsPath): ?string
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            // Fallback to a raw string replacement when the JSON is unreadable.
            return $this->replaceInString($json, $urlMap, $matchUploadsPath);
        }

        $changed = false;

        $walk = function (mixed $value) use (&$walk, &$changed, $urlMap, $matchUploadsPath): mixed {
            if (is_string($value)) {
                $replaced = $this->replaceInString($value, $urlMap, $matchUploadsPath);
                if ($replaced !== $value) {
                    $changed = true;
                }

                return $replaced;
            }

            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $value[$key] = $walk($item);
                }
            }

            return $value;
        };

        $result = $walk($decoded);

        if (! $changed) {
            return null;
        }

        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }

    /**
     * @param  array<string, string>  $urlMap
     */
    private function replaceInString(string $value, array $urlMap, bool $matchUploadsPath): string
    {
        $names = array_keys($urlMap);

        foreach (array_chunk($names, 400) as $chunk) {
            $alternation = implode('|', array_map(
                static fn (string $name): string => preg_quote($name, '#'),
                $chunk
            ));

            $prefix = $matchUploadsPath
                ? '(?:https?://[^\s"\'<>()]+)?/uploads/'
                : '(?:https?://)?[^\s"\'<>()]*/';

            $pattern = '#'.$prefix.'('.$alternation.')(?![A-Za-z0-9_\-\.])#';

            $value = preg_replace_callback(
                $pattern,
                static function (array $matches) use ($urlMap, $matchUploadsPath): string {
                    // Never downgrade an already-local reference during a rollback.
                    if (! $matchUploadsPath && str_contains($matches[0], '/uploads/')) {
                        return $matches[0];
                    }

                    return $urlMap[$matches[1]] ?? $matches[0];
                },
                $value
            ) ?? $value;
        }

        return $value;
    }

    private function upload(string $absolute, string $path): void
    {
        $this->externalStorage->registerDisk();

        $stream = fopen($absolute, 'rb');

        if ($stream === false) {
            throw new RuntimeException('no se pudo abrir el archivo local para leerlo.');
        }

        try {
            $written = Storage::disk(ExternalStorageService::DISK)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($written === false) {
            throw new RuntimeException('la escritura en el almacenamiento externo devolvió un error.');
        }
    }

    private function remoteSize(string $path): ?int
    {
        try {
            return (int) Storage::disk(ExternalStorageService::DISK)->size($path);
        } catch (Throwable) {
            return null;
        }
    }
}
