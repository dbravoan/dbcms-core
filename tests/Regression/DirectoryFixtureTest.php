<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Tests\Support\DirectoryFixture;

it('hides a directory and restores every file when the callback throws', function (): void {
    $files = new Filesystem;
    $root = sys_get_temp_dir().'/dbcms-theme-'.bin2hex(random_bytes(6));
    $files->makeDirectory($root.'/theme/nested', 0755, true);
    $files->put($root.'/theme/.hidden', 'hidden fixture');
    $files->put($root.'/theme/nested/template.blade.php', 'template fixture');
    try {
        expect(fn () => DirectoryFixture::hide($root.'/theme', $root.'/.backup', function () use ($files, $root): void {
            expect($files->isDirectory($root.'/theme'))->toBeFalse();
            expect($files->get($root.'/.backup/nested/template.blade.php'))->toBe('template fixture');
            throw new RuntimeException('callback failure');
        }))->toThrow(RuntimeException::class, 'callback failure');
        expect($files->get($root.'/theme/.hidden'))->toBe('hidden fixture');
        expect($files->get($root.'/theme/nested/template.blade.php'))->toBe('template fixture');
        expect($files->exists($root.'/.backup'))->toBeFalse();
    } finally {
        $files->deleteDirectory($root);
    }
});

it('preserves the source when copying a directory fixture fails', function (): void {
    $files = new class extends Filesystem
    {
        public function copyDirectory($directory, $destination, $options = null): bool
        {
            return false;
        }
    };
    $root = sys_get_temp_dir().'/dbcms-theme-'.bin2hex(random_bytes(6));
    $files->makeDirectory($root.'/theme', 0755, true);
    $files->put($root.'/theme/template', 'original');
    try {
        expect(fn () => DirectoryFixture::hide($root.'/theme', $root.'/.backup', fn () => null, $files))
            ->toThrow(RuntimeException::class, 'copy');
        expect($files->get($root.'/theme/template'))->toBe('original');
    } finally {
        $files->deleteDirectory($root);
    }
});

it('restores filesystem ownership and permissions of a hidden fixture', function (): void {
    $files = new Filesystem;
    $root = sys_get_temp_dir().'/dbcms-theme-'.bin2hex(random_bytes(6));
    $files->makeDirectory($root.'/theme/nested', 0755, true);
    $files->put($root.'/theme/nested/template', 'fixture');
    chmod($root.'/theme/nested', 0700);
    chmod($root.'/theme/nested/template', 0600);
    if (function_exists('posix_getuid') && posix_getuid() === 0) {
        chown($root.'/theme/nested', 1002);
        chgrp($root.'/theme/nested', 1002);
        chown($root.'/theme/nested/template', 1002);
        chgrp($root.'/theme/nested/template', 1002);
    }
    $owner = fileowner($root.'/theme/nested/template');
    $group = filegroup($root.'/theme/nested/template');
    try {
        DirectoryFixture::hide($root.'/theme', $root.'/.backup', fn () => null);
        clearstatcache();
        expect(fileperms($root.'/theme/nested') & 0777)->toBe(0700);
        expect(fileperms($root.'/theme/nested/template') & 0777)->toBe(0600);
        expect(fileowner($root.'/theme/nested/template'))->toBe($owner);
        expect(filegroup($root.'/theme/nested/template'))->toBe($group);
    } finally {
        $files->deleteDirectory($root);
    }
});
