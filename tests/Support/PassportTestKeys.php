<?php

declare(strict_types=1);

namespace Tests\Support;

final class PassportTestKeys
{
    /** @var array{private: string, public: string}|null */
    private static ?array $keys = null;

    public static function configure(): void
    {
        if (self::$keys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if ($key === false || ! openssl_pkey_export($key, $private)) {
                throw new \RuntimeException('Cannot generate the OAuth test key.');
            }
            $details = openssl_pkey_get_details($key);
            if ($details === false) {
                throw new \RuntimeException('Cannot read the OAuth test public key.');
            }
            self::$keys = ['private' => $private, 'public' => $details['key']];
        }
        config(['passport.private_key' => self::$keys['private'], 'passport.public_key' => self::$keys['public']]);
    }
}
