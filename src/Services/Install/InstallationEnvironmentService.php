<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use Dotenv\Dotenv;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\OutputInterface;

final class InstallationEnvironmentService implements InstallationEnvironmentServiceInterface
{
    public function ensure(?OutputInterface $output = null): bool
    {
        $envPath = app()->environmentFilePath();
        $created = ! File::exists($envPath);
        if ($created) {
            $examplePath = base_path('.env.example');
            if (File::exists($examplePath)) {
                File::copy($examplePath, $envPath);
            }
        }

        if (! File::exists($envPath)) {
            throw new \RuntimeException('No existe un archivo de configuración que pueda inicializarse.');
        }
        $stored = (string) (Dotenv::parse((string) File::get($envPath))['APP_KEY'] ?? '');
        $configured = (string) config('app.key', '');
        if (($stored !== '' && ! $this->validApplicationKey($stored)) || ($configured !== '' && ! $this->validApplicationKey($configured))) {
            throw new \RuntimeException('La clave de aplicación configurada no es válida. Corrígela antes de instalar.');
        }
        if ($stored !== '' && $configured !== '' && $stored !== $configured) {
            throw new \RuntimeException('Las claves de aplicación del entorno y del archivo difieren. No se sustituirán automáticamente.');
        }
        if ($stored === '' && $configured !== '') {
            $this->persistEnvironmentValues(['APP_KEY' => $configured]);
            $stored = $configured;
        } elseif ($stored === '') {
            $result = Artisan::call('key:generate', ['--force' => true], $output);
            $stored = (string) (Dotenv::parse((string) File::get($envPath))['APP_KEY'] ?? '');
            if ($result !== 0 || ! $this->validApplicationKey($stored)) {
                throw new \RuntimeException('No se pudo persistir una clave de aplicación válida. La instalación no se ha sellado.');
            }
        }
        config(['app.key' => $stored]);
        app()->forgetInstance('encrypter');
        if (! chmod(realpath($envPath) ?: $envPath, $this->permissions($envPath))) {
            throw new \RuntimeException('No se pudo proteger el archivo de configuración.');
        }

        return $created;
    }

    private function validApplicationKey(string $key): bool
    {
        $bytes = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        return is_string($bytes) && Encrypter::supported($bytes, (string) config('app.cipher', 'AES-256-CBC'));
    }

    /**
     * Writes configuration pairs to .env file safely.
     *
     * @param  array<string, string>  $values
     */
    public function write(array $values): void
    {
        $this->persistEnvironmentValues($values);
    }

    /** @param array<string, string> $values */
    private function persistEnvironmentValues(array $values): void
    {
        $envPath = app()->environmentFilePath();
        if (! File::exists($envPath)) {
            $this->ensure();
        }
        $permissions = $this->permissions($envPath);
        $group = is_link($envPath) ? filegroup($envPath) : null;
        $envPath = realpath($envPath) ?: $envPath;

        $content = (string) File::get($envPath);

        foreach ($values as $key => $value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/D', $key) !== 1) {
                throw new \InvalidArgumentException('Invalid environment variable name.');
            }
            $line = $key.'='.EnvValueSerializer::encode($value);
            if (preg_match("/^{$key}=/m", $content)) {
                $content = (string) preg_replace_callback("/^{$key}=.*/m", static fn (): string => $line, $content);
            } else {
                $content .= "\n".$line;
            }
        }

        File::replace($envPath, $content, $permissions);
        if ($group !== null && ($group === false || ! chgrp($envPath, $group))) {
            throw new \RuntimeException('No se pudo conservar el grupo del archivo de configuración compartido.');
        }
    }

    private function permissions(string $path): int
    {
        if (! is_link($path)) {
            return 0600;
        }
        $mode = fileperms($path);
        if ($mode === false) {
            throw new \RuntimeException('No se pudieron leer los permisos de la configuración compartida.');
        }

        return ($mode & 0660) | 0600;
    }
}
