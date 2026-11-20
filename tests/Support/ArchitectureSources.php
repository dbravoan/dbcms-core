<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

final class ArchitectureSources
{
    /** @return array<string,string> */
    public static function directory(string $path, bool $allowEmpty = false): array
    {
        $sources = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! str_ends_with($file->getFilename(), '.blade.php')) {
                $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
            }
        }
        if ($sources === [] && ! $allowEmpty) {
            throw new RuntimeException('No PHP sources found in '.$path);
        }

        return $sources;
    }

    /** @param class-string $class */
    public static function forClass(string $class): string
    {
        $path = (new ReflectionClass($class))->getFileName();
        if ($path === false) {
            throw new RuntimeException('No source for '.$class);
        }

        return (string) file_get_contents($path);
    }
}
