<?php

declare(strict_types=1);

use DbCms\Core\Services\Install\ApplicationScaffolder;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->originalSetupPaths = [app()->basePath(), app()->environmentPath(), app()->storagePath(), app()->publicPath()];
    $this->setupRuntime = sys_get_temp_dir().'/dbcms-setup-'.bin2hex(random_bytes(8));
    foreach (['bootstrap/cache', 'public', 'storage', 'resources'] as $directory) {
        File::ensureDirectoryExists($this->setupRuntime.'/'.$directory);
    }
    File::put($this->setupRuntime.'/.env', 'APP_KEY='.(string) config('app.key')."\n");
    File::put($this->setupRuntime.'/package.json', '{}');
    app()->setBasePath($this->setupRuntime);
    app()->useEnvironmentPath($this->setupRuntime)->useStoragePath($this->setupRuntime.'/storage')->usePublicPath($this->setupRuntime.'/public');
    DB::connection()->beforeExecuting(static function (): void {
        throw new LogicException('Application setup must not query or migrate data.');
    });
});

afterEach(function (): void {
    [$base, $environment, $storage, $public] = $this->originalSetupPaths;
    app()->setBasePath($base);
    app()->useEnvironmentPath($environment)->useStoragePath($storage)->usePublicPath($public);
    File::deleteDirectory($this->setupRuntime);
});

it('prepares the initial application name without changing a custom name or rotating the key', function (?string $initialName, string $expectedName): void {
    $key = (string) config('app.key');
    if ($initialName !== null) {
        File::append(base_path('.env'), 'APP_NAME="'.$initialName.'"'.PHP_EOL);
    }
    $scaffolder = app(ApplicationScaffolder::class);
    $scaffolder->prepare('fixture-theme', 'dbcms');
    $before = File::get(base_path('bootstrap/app.php'));
    $environment = File::get(base_path('.env'));
    $scaffolder->prepare('fixture-theme', 'dbcms');
    expect(File::get(base_path('bootstrap/app.php')))->toBe($before)
        ->and(File::get(base_path('.env')))->toBe($environment)
        ->and($environment)->toContain($key, 'CMS_THEME="fixture-theme"', 'CMS_MODULES=""', 'CMS_BLOCK_PACKAGES=""')
        ->and(Dotenv\Dotenv::parse($environment)['APP_NAME'])->toBe($expectedName)
        ->and(File::exists(storage_path('installed')))->toBeFalse()
        ->and(File::get(base_path('public/index.php')))->toContain('InstallerBootstrap')
        ->and(File::get(base_path('vite.config.js')))->toContain('cmsAssets');
})->with([
    'absent' => [null, 'dbCMS'],
    'Laravel default' => ['Laravel', 'dbCMS'],
    'project name' => ['Área Bravo', 'Área Bravo'],
]);

it('rejects modified project files before changing anything', function (): void {
    File::put(base_path('bootstrap/app.php'), '<?php // Custom project bootstrap');
    $environment = File::get(base_path('.env'));
    expect(fn () => app(ApplicationScaffolder::class)->prepare('fixture-theme', 'dbcms'))->toThrow(DomainException::class, 'no se sobrescribirá');
    expect(File::get(base_path('bootstrap/app.php')))->toBe('<?php // Custom project bootstrap')
        ->and(File::get(base_path('.env')))->toBe($environment)
        ->and(File::exists(base_path('config/auth.php')))->toBeFalse();
});

it('rejects unavailable selections and sealed installations', function (): void {
    expect(fn () => app(ApplicationScaffolder::class)->prepare('fixture-theme', 'dbcms', 'not_installed'))->toThrow(DomainException::class, 'no está instalada');
    File::put(storage_path('installed'), 'sealed');
    expect(fn () => app(ApplicationScaffolder::class)->prepare('fixture-theme', 'dbcms'))->toThrow(DomainException::class, 'ya está sellada');
    expect(File::exists(base_path('bootstrap/app.php')))->toBeFalse();
});

it('preserves a custom development router and rejects symlink destinations', function (bool $symlink): void {
    if ($symlink) {
        File::put(base_path('private-router.php'), '<?php // Owner router');
        symlink(base_path('private-router.php'), base_path('server.php'));
    } else {
        File::put(base_path('server.php'), '<?php // Owner router');
    }
    $environment = File::get(base_path('.env'));
    expect(fn () => app(ApplicationScaffolder::class)->prepare('fixture-theme', 'dbcms'))->toThrow(DomainException::class);
    expect(File::get(base_path('server.php')))->toBe('<?php // Owner router')
        ->and(File::get(base_path('.env')))->toBe($environment)
        ->and(File::exists(base_path('bootstrap/app.php')))->toBeFalse();
})->with([false, true]);

it('serves public compiled assets to opaque origins without exposing application responses or private files', function (): void {
    app(ApplicationScaffolder::class)->prepare('fixture-theme', 'dbcms');
    expect(File::exists(base_path('server.php')))->toBeTrue();
    $frameworkRouter = base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
    File::ensureDirectoryExists(dirname($frameworkRouter));
    File::copy($this->originalSetupPaths[0].'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php', $frameworkRouter);
    File::put(public_path('index.php'), '<?php header("Content-Type: text/html"); echo "<h1>Application</h1>";');
    File::ensureDirectoryExists(public_path('build/assets'));
    File::put(public_path('build/assets/app-test.js'), 'export const ready = true;');
    File::put(public_path('build/assets/app-test.css'), 'body { color: black; }');
    File::put(public_path('build/assets/font-test.woff2'), 'synthetic-font');
    File::put(public_path('build/assets/private.html'), '<h1>Not a compiled asset</h1>');
    File::put(base_path('private.js'), 'private-content');
    symlink(base_path('private.js'), public_path('build/assets/linked.js'));
    symlink(public_path('build/assets/private.html'), public_path('build/assets/linked-html.js'));
    File::ensureDirectoryExists(public_path('other'));
    File::put(public_path('other/static.js'), 'unrelated-static');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $process = new Process([PHP_BINARY, '-S', $address, base_path('server.php')], public_path());
    $process->start();
    $http = new Client(['base_uri' => 'http://'.$address, 'http_errors' => false, 'timeout' => 3, 'headers' => ['Origin' => 'null']]);
    try {
        retry(50, fn () => $http->get('/ready'), 20);
        foreach (['app-test.js' => 'application/javascript', 'app-test.css' => 'text/css', 'font-test.woff2' => 'font/woff2'] as $asset => $mime) {
            $response = $http->get('/build/assets/'.$asset);
            expect($response->getStatusCode())->toBe(200)
                ->and($response->getHeaderLine('Access-Control-Allow-Origin'))->toBe('*')
                ->and($response->getHeaderLine('Access-Control-Allow-Credentials'))->toBe('')
                ->and($response->getHeaderLine('Set-Cookie'))->toBe('')
                ->and($response->getHeaderLine('Content-Type'))->toStartWith($mime);
        }
        $head = $http->head('/build/assets/app-test.js');
        expect($head->getStatusCode())->toBe(200)
            ->and($head->getHeaderLine('Access-Control-Allow-Origin'))->toBe('*')
            ->and((string) $head->getBody())->toBe('');
        foreach (['/', '/api/private', '/other/static.js'] as $uri) {
            $response = $http->get($uri);
            expect($response->getStatusCode())->toBe(200)
                ->and($response->getHeaderLine('Access-Control-Allow-Origin'))->toBe('');
        }
        foreach (['/build/assets/private.html', '/build/assets/linked.js', '/build/assets/linked-html.js', '/build/assets/missing.js', '/build/assets/%2e%2e/%2e%2e/%2e%2e/private.js'] as $uri) {
            $response = $http->get($uri);
            expect($response->getStatusCode())->toBe(404)
                ->and($response->getHeaderLine('Access-Control-Allow-Origin'))->toBe('')
                ->and((string) $response->getBody())->not->toContain('private-content');
        }
        $post = $http->post('/build/assets/app-test.js');
        expect($post->getStatusCode())->toBe(405)
            ->and($post->getHeaderLine('Access-Control-Allow-Origin'))->toBe('');
        File::deleteDirectory(public_path('build/assets'));
        File::ensureDirectoryExists(base_path('outside-assets'));
        File::put(base_path('outside-assets/secret.js'), 'private-content');
        symlink(base_path('outside-assets'), public_path('build/assets'));
        $linkedRoot = $http->get('/build/assets/secret.js');
        expect($linkedRoot->getStatusCode())->toBe(404)
            ->and($linkedRoot->getHeaderLine('Access-Control-Allow-Origin'))->toBe('')
            ->and((string) $linkedRoot->getBody())->not->toContain('private-content');
    } finally {
        $process->stop();
    }
});
