<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Security;

/**
 * Hardens the Laravel Passport key pair on disk.
 *
 * `passport:keys` writes `oauth-private.key` / `oauth-public.key` using the
 * process umask (commonly 0644 or, inside Sail, 0666/0777 after a broad
 * `chmod -R` over the storage tree). The private key is a signing secret and
 * must never be group- or world-readable, so its permissions are (re)applied
 * after generation and after any deployment-wide permission sweep.
 */
final class PassportKeyPermissionService
{
    /**
     * Mode applied to the signing secret.
     */
    public const PRIVATE_KEY_MODE = 0600;

    /**
     * League validates permissions on both key files, including the public key.
     */
    public const PUBLIC_KEY_MODE = 0600;

    /**
     * Key file name => required octal mode.
     *
     * @var array<string, int>
     */
    private const KEYS = [
        'oauth-private.key' => self::PRIVATE_KEY_MODE,
        'oauth-public.key' => self::PUBLIC_KEY_MODE,
    ];

    /**
     * Apply the expected permissions to any Passport key present in $directory.
     *
     * Failures are silenced on purpose: keys may live on read-only or network
     * filesystems where chmod is unsupported, and a missing key is not fatal.
     *
     * @return array<int, string> Names of the keys that were found and chmod'ed.
     */
    public function harden(string $directory): array
    {
        $base = rtrim($directory, DIRECTORY_SEPARATOR);
        $hardened = [];

        foreach (self::KEYS as $file => $mode) {
            $path = $base.DIRECTORY_SEPARATOR.$file;

            if (! is_file($path)) {
                continue;
            }

            @chmod($path, $mode);
            $hardened[] = $file;
        }

        return $hardened;
    }
}
