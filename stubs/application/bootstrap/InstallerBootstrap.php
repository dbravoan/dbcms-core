<?php

declare(strict_types=1);

/**
 * Protects first-run environment setup before Laravel resolves encrypted cookies.
 *
 * This file deliberately cannot depend on Composer or Laravel: it executes
 * before vendor/autoload.php and must also explain an incomplete package.
 */
final class InstallerBootstrap
{
    /** @param array<string, mixed> $server */
    public function __construct(
        private readonly string $root,
        private readonly array $server,
        private readonly string $phpVersion = PHP_VERSION,
    ) {}

    /** @return array{title: string, message: string, details: list<string>}|null */
    public function prepare(): ?array
    {
        if (version_compare($this->phpVersion, '8.4.0', '<')) {
            return $this->failure('PHP no es compatible', 'dbCMS necesita PHP 8.4 o posterior.', [
                'Selecciona PHP 8.4 o superior desde Plesk o cPanel y vuelve a cargar esta página.',
            ]);
        }

        if (! is_file($this->root.'/vendor/autoload.php')) {
            return $this->failure('El paquete está incompleto', 'Faltan las dependencias de Composer.', [
                'Instala las dependencias de Composer de la aplicación.',
                'Consulta el procedimiento de instalación del proyecto.',
            ]);
        }

        $installedPath = $this->root.'/storage/installed';
        $installed = file_exists($installedPath) || is_link($installedPath);
        $envPath = $this->root.'/.env';
        if ($installed) {
            if (! is_file($envPath) || ! $this->hasValidKey((string) @file_get_contents($envPath))) {
                return $this->failure('La instalación necesita recuperación', 'La clave de cifrado de esta instalación no está disponible o no es válida.', [
                    'Restaura el archivo .env original desde una copia de seguridad.',
                    'No generes otra APP_KEY: los datos cifrados dejarían de poder leerse.',
                ]);
            }

            return null;
        }

        // Reading an already-provisioned link is safe and never changes it.
        if (is_link($envPath) && is_file($envPath) && $this->hasValidKey((string) @file_get_contents($envPath))) {
            return null;
        }

        if (is_link($envPath)) {
            return $this->failure('Destino de configuración no válido', 'El enlace de configuración debe estar provisionado con una APP_KEY válida.', []);
        }
        $writePath = $envPath;
        $template = $this->root.'/.env.example';
        if (! is_file($writePath) && ! is_file($template)) {
            return $this->failure('Falta la plantilla de instalación', 'No se encontró .env.example.', [
                'Restaura la plantilla de configuración incluida en la aplicación.',
            ]);
        }

        $lockPath = $this->root.'/storage/install-bootstrap.lock';
        if (! is_dir(dirname($lockPath)) || ! is_writable(dirname($lockPath))) {
            return $this->permissionsFailure();
        }
        if (is_link($lockPath)) {
            return $this->failure('Destino de configuración no válido', 'La configuración inicial no puede escribirse sobre enlaces simbólicos.', []);
        }

        $lock = @fopen($lockPath, 'c');
        if ($lock === false || ! @flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return $this->permissionsFailure();
        }

        try {
            @chmod($lockPath, 0600);
            if (is_link($writePath)) {
                return $this->failure('Destino de configuración no válido', 'El destino cambió durante la configuración inicial.', []);
            }
            $created = ! is_file($writePath);
            $content = $created ? (string) @file_get_contents($template) : (string) @file_get_contents($writePath);
            if ($content === '') {
                return $this->permissionsFailure();
            }
            if (! $created && $this->hasValidKey($content)) {
                return null;
            }
            if (! $this->hasValidKey($content)) {
                $content = $this->replace($content, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
            }
            if ($created || $this->environmentValue($content, 'APP_URL') === '' || $this->environmentValue($content, 'APP_URL') === 'http://localhost') {
                $content = $this->replace($content, 'APP_URL', $this->requestUrl());
            }

            if (! $this->atomicWrite($writePath, $content) || ! $this->hasValidKey((string) @file_get_contents($writePath))) {
                return $this->permissionsFailure();
            }
        } catch (Throwable) {
            return $this->failure('No se pudo iniciar la instalación', 'El servidor no pudo generar una configuración segura.', [
                'Comprueba que PHP puede escribir en la carpeta raíz del sitio y en storage/.',
            ]);
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }

        return null;
    }

    /** @param array{title: string, message: string, details: list<string>} $failure */
    public static function render(array $failure): string
    {
        $title = htmlspecialchars($failure['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars($failure['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $details = '';
        foreach ($failure['details'] as $detail) {
            $details .= '<li>'.htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>';
        }

        return '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.$title.' · dbCMS</title><style>body{margin:0;background:#0b0d0f;color:#eef2f4;font:16px system-ui,sans-serif}'
            .'main{max-width:680px;margin:10vh auto;padding:32px;background:#15191d;border:1px solid #30373d;border-radius:16px}h1{color:#ffcc66}'
            .'p,li{line-height:1.6;color:#c8d0d6}code{color:#74e6b2}</style><main><h1>'.$title.'</h1><p>'.$message.'</p><ul>'.$details.'</ul>'
            .'<p>Cuando lo hayas corregido, vuelve a cargar esta página.</p></main></html>';
    }

    private function requestUrl(): string
    {
        $https = ! empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
        $host = strtolower((string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost'));
        if (preg_match('/\A(?:[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?|\[[0-9a-f:]+\])(?::[0-9]{1,5})?\z/i', $host) !== 1) {
            $host = 'localhost';
        }

        return ($https ? 'https' : 'http').'://'.$host;
    }

    private function hasValidKey(string $content): bool
    {
        $key = $this->environmentValue($content, 'APP_KEY');
        if ($key === '') {
            return false;
        }
        $bytes = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        return is_string($bytes) && strlen($bytes) === 32;
    }

    private function environmentValue(string $content, string $name): string
    {
        if (preg_match('/^'.preg_quote($name, '/').'\s*=\s*["\']?([^"\'\r\n]*)["\']?\s*$/m', $content, $matches) !== 1) {
            return '';
        }

        return trim($matches[1]);
    }

    private function replace(string $content, string $name, string $value): string
    {
        $line = $name.'="'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
        if (preg_match('/^'.preg_quote($name, '/').'\s*=/m', $content) === 1) {
            return (string) preg_replace('/^'.preg_quote($name, '/').'\s*=.*$/m', $line, $content, 1);
        }

        return rtrim($content)."\n".$line."\n";
    }

    private function atomicWrite(string $path, string $content): bool
    {
        $temporary = dirname($path).'/.'.basename($path).'.'.bin2hex(random_bytes(8)).'.tmp';
        $written = @file_put_contents($temporary, $content, LOCK_EX);
        if ($written !== strlen($content) || ! @chmod($temporary, 0600) || ! @rename($temporary, $path)) {
            @unlink($temporary);

            return false;
        }
        @chmod($path, 0600);

        return true;
    }

    /** @return array{title: string, message: string, details: list<string>} */
    private function permissionsFailure(): array
    {
        return $this->failure('El servidor necesita permisos de escritura', 'PHP no puede crear la configuración inicial.', [
            'En Plesk o cPanel, da permiso de escritura al usuario del sitio sobre la carpeta raíz, storage/ y bootstrap/cache/.',
            'No uses permisos 777. El propietario debe ser el mismo usuario con el que PHP ejecuta este dominio.',
        ]);
    }

    /** @param list<string> $details
     * @return array{title: string, message: string, details: list<string>}
     */
    private function failure(string $title, string $message, array $details): array
    {
        return compact('title', 'message', 'details');
    }
}
