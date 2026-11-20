<?php

declare(strict_types=1);

namespace Tests\Release;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

#[Group('release')]
final class InstallerHttpBootstrapTest extends TestCase
{
    public function test_first_install_and_readiness_use_the_real_http_entrypoint(): void
    {
        $source = PACKAGE_ROOT.'/.build/app';
        $directory = sys_get_temp_dir().'/dbcms-installer-http-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $server = null;
        $environment = $this->isolatedEnvironment();
        try {
            $release = $this->prepareApplication($source, $directory, $files);
            $token = bin2hex(random_bytes(32));
            $files->put($release.'/.env', "APP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=https://configured.example\nSESSION_DRIVER=file\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=1\nDB_DATABASE=unavailable\nCMS_THEME=fixture-theme\nCMS_BRAND=dbcms\nCMS_MODULES=\nCMS_SEED_DEMO=false\nCMS_INSTALL_TOKEN={$token}\nAPP_LOCALE=es\nCUSTOM_VALUE=preserved\n");
            [$server, $url] = $this->startServer($release, $environment);

            self::assertSame(302, $this->request($url.'/install')['status']);
            $gate = $this->request($url.'/install/access');
            self::assertSame(200, $gate['status']);
            self::assertStringNotContainsString($token, $gate['body']);
            self::assertSame(1, preg_match('/name="_token" value="([^"]+)"/', $gate['body'], $csrf));
            $grant = $this->request($url.'/install/access', $gate['cookies'], ['_token' => $csrf[1], 'install_token' => $token]);
            self::assertSame(302, $grant['status']);
            $installer = $this->request($url.'/install', $grant['cookies']);
            self::assertSame(200, $installer['status'], $installer['body']);
            self::assertStringContainsString('Continuar a la Base de Datos', $installer['body']);
            self::assertSame(200, $this->request($url.'/install/database', $grant['cookies'])['status']);
            self::assertFileDoesNotExist($release.'/storage/installed');
            $configured = $files->get($release.'/.env');
            self::assertMatchesRegularExpression('/^APP_KEY="base64:[A-Za-z0-9+\/=]{44}"$/m', $configured);
            self::assertStringContainsString('CUSTOM_VALUE=preserved', $configured);
            self::assertStringContainsString('APP_URL=https://configured.example', $configured);
            clearstatcache(true, $release.'/.env');
            self::assertSame(0600, fileperms($release.'/.env') & 0777);

            $health = $this->request($url.'/up');
            self::assertSame(200, $health['status']);
            self::assertSame($configured, $files->get($release.'/.env'));

            // La comprobación de salud sigue disponible durante el mantenimiento.
            $this->artisan($release, $environment, ['down', '--retry=15', '--no-interaction']);
            self::assertSame(503, $this->request($url.'/install')['status']);
            $health = $this->request($url.'/up');
            self::assertSame(200, $health['status']);
            $this->artisan($release, $environment, ['up', '--no-interaction']);

            // An installed marker changes DB outage readiness, never key ownership.
            $files->put($release.'/storage/installed', '{}');
            $health = $this->request($url.'/up');
            self::assertSame(500, $health['status']);
            self::assertSame($configured, $files->get($release.'/.env'));
        } finally {
            $server?->stop();
            $files->deleteDirectory($directory);
        }
    }

    private function prepareApplication(string $source, string $directory, Filesystem $files): string
    {
        $release = $directory.'/application';
        $files->ensureDirectoryExists($release, 0700);
        foreach (['app', 'bootstrap', 'config', 'public', 'resources', 'routes'] as $path) {
            foreach ($files->allFiles($source.'/'.$path, true) as $file) {
                $relative = substr($file->getPathname(), strlen($source) + 1);
                if ($file->isLink() || str_starts_with($relative, 'bootstrap/cache/') || str_starts_with($relative, 'public/storage/') || str_starts_with($relative, 'public/uploads/') || $relative === 'public/hot') {
                    continue;
                }
                $files->ensureDirectoryExists(dirname($release.'/'.$relative), 0700);
                $files->copy($file->getPathname(), $release.'/'.$relative);
            }
        }
        foreach (['.env.example', 'artisan', 'composer.json'] as $path) {
            $files->copy($source.'/'.$path, $release.'/'.$path);
        }
        $files->ensureDirectoryExists($release.'/bootstrap/cache', 0700);
        symlink($source.'/vendor', $release.'/vendor');
        foreach (['storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'storage/app/public'] as $path) {
            $files->ensureDirectoryExists($release.'/'.$path, 0700);
        }

        return $release;
    }

    /** @return array<string, false> */
    private function isolatedEnvironment(): array
    {
        $environment = [];
        foreach (array_keys(getenv()) as $key) {
            if (preg_match('/^(APP_|DB_|DBCMS_|CMS_|CACHE_|SESSION_|QUEUE_|REDIS_|LOG_|VIEW_|MAIL_|FILESYSTEM_|VITE_|COMPOSER_)/', $key) === 1) {
                $environment[$key] = false;
            }
        }

        return $environment;
    }

    /** @param array<string, false> $environment
     * @return array{Process, string}
     */
    private function startServer(string $release, array $environment): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        self::assertIsString($address);
        fclose($socket);
        $process = new Process([PHP_BINARY, '-S', $address, '-t', $release.'/public', $release.'/public/index.php'], $release, $environment, timeout: 30);
        $process->start();
        $ready = $process->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'Development Server'));
        self::assertTrue($ready, $process->getErrorOutput());

        return [$process, 'http://'.$address];
    }

    /** @param list<string> $cookies
     * @param  array<string,string>  $form
     * @return array{status: int, headers: array<string, string>, body: string, cookies: list<string>}
     */
    private function request(string $url, array $cookies = [], array $form = []): array
    {
        $options = ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15];
        if ($cookies !== []) {
            $options['header'] = ['Cookie: '.implode('; ', $cookies)];
        }
        if ($form !== []) {
            $options['method'] = 'POST';
            $options['header'][] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($form);
        }
        $context = stream_context_create(['http' => $options]);
        $body = file_get_contents($url, false, $context);
        self::assertIsString($body);
        $lines = http_get_last_response_headers();
        self::assertIsArray($lines);
        if (preg_match('/^HTTP\/\S+ (\d{3})/', $lines[0], $status) !== 1) {
            self::fail('The HTTP server did not return a valid status line.');
        }
        $headers = [];
        $cookies = [];
        foreach (array_slice($lines, 1) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower($name)] = trim($value);
                if (strtolower($name) === 'set-cookie') {
                    $cookies[] = trim(explode(';', $value, 2)[0]);
                }
            }
        }

        return ['status' => (int) $status[1], 'headers' => $headers, 'body' => $body, 'cookies' => $cookies];
    }

    /** @param array<string, false> $environment
     * @param  list<string>  $arguments
     */
    private function artisan(string $release, array $environment, array $arguments): void
    {
        $process = new Process([PHP_BINARY, 'artisan', ...$arguments], $release, $environment, timeout: 30);
        $process->run();
        self::assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
    }
}
