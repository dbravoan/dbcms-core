<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Geo;

use DbCms\Core\Contracts\Services\GeoExportServiceInterface;
use DbCms\Core\Contracts\Services\GeoServiceInterface;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

/** Optional offline snapshots never replace the live public discovery routes. */
final class GeoExportService implements GeoExportServiceInterface
{
    public function __construct(private readonly GeoServiceInterface $generator) {}

    public function export(?string $directory = null): array
    {
        $directory = $this->resolve($directory ?? storage_path('app/private/geo-export'));
        foreach ([public_path(), storage_path('app/public')] as $publicDirectory) {
            $public = $this->resolve($publicDirectory);
            if ($directory === $public || str_starts_with($directory, $public.'/')) {
                throw new InvalidArgumentException('GEO snapshots must be exported outside publicly served directories.');
            }
        }
        File::ensureDirectoryExists($directory, 0700);
        $files = [
            'llms.txt' => $this->generator->generateLlmsTxt(),
            'llms-full.txt' => $this->generator->generateLlmsFullTxt(),
        ];
        $paths = [];
        foreach ($files as $name => $content) {
            $path = $directory.'/'.$name;
            File::replace($path, $content, 0600);
            $paths[] = $path;
        }

        return $paths;
    }

    private function resolve(string $path): string
    {
        if (! str_starts_with($path, '/')) {
            throw new InvalidArgumentException('The GEO export directory must be an absolute path.');
        }
        $suffix = [];
        $ancestor = $path;
        while (! file_exists($ancestor) && $ancestor !== '/') {
            array_unshift($suffix, basename($ancestor));
            $ancestor = dirname($ancestor);
        }
        $resolved = realpath($ancestor);
        if ($resolved === false || ! is_dir($resolved)) {
            throw new InvalidArgumentException('The GEO export destination is not a directory.');
        }
        $parts = [];
        foreach (explode('/', $resolved.'/'.implode('/', $suffix)) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return '/'.implode('/', $parts);
    }
}
