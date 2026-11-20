<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** Copy-based fixtures also work for image directories on Docker overlay filesystems. */
final class DirectoryFixture
{
    public static function hide(string $source, string $backup, Closure $callback, ?Filesystem $files = null): mixed
    {
        $files ??= new Filesystem;
        if (! $files->isDirectory($source) || $files->exists($backup)) {
            throw new RuntimeException('Fixture source missing or backup already exists.');
        }
        $manifest = self::manifest($files, $source);
        $metadata = self::metadata($source);
        if (! $files->copyDirectory($source, $backup) || self::manifest($files, $backup) !== $manifest) {
            $files->deleteDirectory($backup);
            throw new RuntimeException('Unable to copy and verify directory fixture.');
        }
        try {
            if (! $files->deleteDirectory($source)) {
                throw new RuntimeException('Unable to hide directory fixture.');
            }

            return $callback();
        } finally {
            if (! $files->copyDirectory($backup, $source) || self::manifest($files, $source) !== $manifest) {
                throw new RuntimeException('Unable to restore fixture; verified backup retained at '.$backup);
            }
            self::restoreMetadata($source, $metadata);
            if (! $files->deleteDirectory($backup)) {
                throw new RuntimeException('Unable to remove restored fixture backup.');
            }
        }
    }

    /** @return array<string, array{mode:int, uid:int, gid:int}> */
    private static function metadata(string $directory): array
    {
        $paths = [$directory];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $file) {
            $paths[] = $file->getPathname();
        }
        $metadata = [];
        foreach ($paths as $path) {
            $stat = stat($path);
            if ($stat === false) {
                throw new RuntimeException('Unable to read fixture metadata.');
            }
            $relative = $path === $directory ? '' : substr($path, strlen($directory) + 1);
            $metadata[$relative] = ['mode' => $stat['mode'] & 0777, 'uid' => $stat['uid'], 'gid' => $stat['gid']];
        }

        return $metadata;
    }

    /** @param array<string, array{mode:int, uid:int, gid:int}> $metadata */
    private static function restoreMetadata(string $directory, array $metadata): void
    {
        foreach (array_reverse($metadata, true) as $relative => $stat) {
            $path = $directory.($relative === '' ? '' : '/'.$relative);
            clearstatcache(true, $path);
            if ((fileowner($path) !== $stat['uid'] && ! chown($path, $stat['uid']))
                || (filegroup($path) !== $stat['gid'] && ! chgrp($path, $stat['gid']))
                || ! chmod($path, $stat['mode'])) {
                throw new RuntimeException('Unable to restore fixture metadata; backup retained.');
            }
        }
    }

    /** @return array<string, string> */
    private static function manifest(Filesystem $files, string $directory): array
    {
        $manifest = [];
        foreach ($files->allFiles($directory, true) as $file) {
            $checksum = hash_file('sha256', $file->getPathname());
            if ($checksum === false) {
                throw new RuntimeException('Unable to verify fixture file '.$file->getPathname());
            }
            $manifest[$file->getRelativePathname()] = $checksum;
        }
        ksort($manifest);

        return $manifest;
    }
}
