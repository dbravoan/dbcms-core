<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/** Keeps a test application's mutable bootstrap caches away from the installation. */
final class BootstrapCacheIsolation
{
    private readonly string $directory;

    /** @var array<string, array{process: string|false, env: mixed, server: mixed, hasEnv: bool, hasServer: bool}> */
    private array $previous = [];

    public function __construct()
    {
        $this->directory = sys_get_temp_dir().'/dbcms-test-bootstrap-'.bin2hex(random_bytes(8));
        if (! mkdir($this->directory, 0700)) {
            throw new RuntimeException('Cannot create the private test bootstrap cache.');
        }

        foreach (['APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes.php', 'APP_EVENTS_CACHE' => 'events.php'] as $key => $file) {
            $this->previous[$key] = [
                'process' => getenv($key), 'env' => $_ENV[$key] ?? null, 'server' => $_SERVER[$key] ?? null,
                'hasEnv' => array_key_exists($key, $_ENV), 'hasServer' => array_key_exists($key, $_SERVER),
            ];
            $path = $this->directory.'/'.$file;
            putenv($key.'='.$path);
            $_ENV[$key] = $_SERVER[$key] = $path;
        }
    }

    public function restore(): void
    {
        foreach ($this->previous as $key => $value) {
            putenv($value['process'] === false ? $key : $key.'='.$value['process']);
            if ($value['hasEnv']) {
                $_ENV[$key] = $value['env'];
            } else {
                unset($_ENV[$key]);
            }
            if ($value['hasServer']) {
                $_SERVER[$key] = $value['server'];
            } else {
                unset($_SERVER[$key]);
            }
        }
        $this->previous = [];
        (new Filesystem)->deleteDirectory($this->directory);
    }
}
