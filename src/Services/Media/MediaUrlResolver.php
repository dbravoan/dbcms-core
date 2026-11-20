<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for resolving a stored media file to a servable URL.
 *
 * Resolution is intentionally local-first so existing `/uploads/{filename}`
 * references keep working: a local file always wins, and the external disk is
 * only used when the local copy is missing (e.g. after a migration with
 * `--prune-local`) or when no local file ever existed.
 */
final class MediaUrlResolver
{
    public function __construct(
        private readonly ExternalStorageService $externalStorage
    ) {}

    /**
     * Absolute filesystem path of the local uploads directory.
     */
    public function uploadDir(): string
    {
        $dir = rtrim(Storage::disk('uploads')->path(''), DIRECTORY_SEPARATOR);

        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * Normalizes a stored path so it can never escape the uploads directory.
     */
    public function sanitize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $segments = array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..'
        );

        return implode('/', $segments);
    }

    public function localPath(string $path): string
    {
        return $this->uploadDir().'/'.$this->sanitize($path);
    }

    public function hasLocalFile(string $path): bool
    {
        $sanitized = $this->sanitize($path);

        return $sanitized !== '' && is_file($this->uploadDir().'/'.$sanitized);
    }

    public function localUrl(string $path): string
    {
        return url('uploads/'.$this->sanitize($path));
    }

    /**
     * Resolves the best URL for a media path.
     *
     * @param  string|null  $stored  The URL persisted on the record, when known.
     *                               A non-local stored URL (e.g. a CDN) is kept as-is.
     */
    public function url(string $path, ?string $stored = null): string
    {
        $sanitized = $this->sanitize($path);
        $stored = trim((string) $stored);

        // A record already rewritten to an external/CDN location is authoritative.
        if ($stored !== '' && ! $this->isLocalUploadUrl($stored)) {
            return $stored;
        }

        if ($this->hasLocalFile($sanitized)) {
            return $this->localUrl($sanitized);
        }

        if ($this->externalStorage->isConfigured()) {
            return $this->externalStorage->url($sanitized);
        }

        return $stored !== '' ? $stored : $this->localUrl($sanitized);
    }

    /**
     * Whether the given URL points at the local `/uploads` directory.
     */
    public function isLocalUploadUrl(string $url): bool
    {
        return str_contains($url, '/uploads/');
    }
}
