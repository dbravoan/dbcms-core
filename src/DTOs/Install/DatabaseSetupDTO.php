<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Install;

final readonly class DatabaseSetupDTO
{
    public function __construct(public string $host, public string $port, public string $database, public string $username, public string $password, public bool $autoCreate = true)
    {
        if (! preg_match('/\A[A-Za-z0-9_.:\-\[\]]{1,253}\z/', $host) || ! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535 || ! preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $database) || $username === '') {
            throw new \InvalidArgumentException('Invalid database connection settings.');
        }
    }
}
