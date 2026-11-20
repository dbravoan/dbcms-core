<?php

declare(strict_types=1);

use DbCms\Core\Services\Security\PassportKeyPermissionService;

$keyPerms = static fn (string $path): string => substr(sprintf('%o', fileperms($path)), -4);

beforeEach(function (): void {
    $this->keysDir = sys_get_temp_dir().'/dbcms-passport-keys-'.uniqid('', true);
    mkdir($this->keysDir, 0700, true);
});

afterEach(function (): void {
    foreach (glob($this->keysDir.'/*') ?: [] as $file) {
        chmod($file, 0600);
        unlink($file);
    }

    rmdir($this->keysDir);
});

test('it restricts both key files to the application owner for League permission validation', function () use ($keyPerms): void {
    $private = $this->keysDir.'/oauth-private.key';
    $public = $this->keysDir.'/oauth-public.key';

    file_put_contents($private, 'private-material');
    file_put_contents($public, 'public-material');
    chmod($private, 0666);
    chmod($public, 0666);

    $hardened = (new PassportKeyPermissionService)->harden($this->keysDir);

    expect($hardened)->toContain('oauth-private.key')
        ->toContain('oauth-public.key')
        ->and($keyPerms($private))->toBe('0600')
        ->and($keyPerms($public))->toBe('0600');
});

test('it ignores a missing key pair instead of failing', function (): void {
    expect((new PassportKeyPermissionService)->harden($this->keysDir))->toBe([]);
});
