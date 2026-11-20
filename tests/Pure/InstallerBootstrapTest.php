<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use Illuminate\Filesystem\Filesystem;

require_once Cms::path('stubs/application/bootstrap/InstallerBootstrap.php');

beforeEach(function (): void {
    $this->files = new Filesystem;
    $this->root = sys_get_temp_dir().'/dbcms-bootstrap-'.bin2hex(random_bytes(8));
    $this->files->ensureDirectoryExists($this->root.'/vendor', 0700);
    $this->files->ensureDirectoryExists($this->root.'/storage', 0700);
    $this->files->put($this->root.'/vendor/autoload.php', '<?php');
    $this->files->put($this->root.'/.env.example', "APP_ENV=production\nAPP_KEY=\nAPP_URL=http://localhost\nSESSION_DRIVER=file\nCACHE_STORE=file\nQUEUE_CONNECTION=sync\n");
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->root);
});

test('fresh application bootstrap atomically creates a protected environment and stable key', function (): void {
    $bootstrap = new InstallerBootstrap($this->root, ['HTTP_HOST' => 'cms.example', 'HTTPS' => 'on']);

    expect($bootstrap->prepare())->toBeNull();
    $first = $this->files->get($this->root.'/.env');
    expect($first)->toContain('APP_URL="https://cms.example"')
        ->and($first)->toMatch('/^APP_KEY="base64:[A-Za-z0-9+\/=]+"$/m')
        ->and(fileperms($this->root.'/.env') & 0777)->toBe(0600);

    expect($bootstrap->prepare())->toBeNull()
        ->and($this->files->get($this->root.'/.env'))->toBe($first);
});

test('bootstrap preserves an existing valid environment byte for byte', function (): void {
    $existing = 'APP_KEY=base64:'.base64_encode(str_repeat('k', 32))."\nAPP_URL=https://configured.example/subdirectory\nCUSTOM_VALUE=keep-this-byte-for-byte\n";
    $this->files->put($this->root.'/.env', $existing);
    chmod($this->root.'/.env', 0640);

    $bootstrap = new InstallerBootstrap($this->root, ['HTTP_HOST' => 'different.example', 'HTTPS' => 'on']);

    expect($bootstrap->prepare())->toBeNull()
        ->and($this->files->get($this->root.'/.env'))->toBe($existing)
        ->and(fileperms($this->root.'/.env') & 0777)->toBe(0640);
});

test('bootstrap accepts a provisioned external environment without replacing its link', function (): void {
    $this->files->ensureDirectoryExists($this->root.'/shared');
    $existing = 'APP_KEY=base64:'.base64_encode(str_repeat('k', 32))."\nAPP_URL=https://configured.example\n";
    $this->files->put($this->root.'/shared/.env', $existing);
    symlink($this->root.'/shared/.env', $this->root.'/.env');

    expect((new InstallerBootstrap($this->root, []))->prepare())->toBeNull()
        ->and(is_link($this->root.'/.env'))->toBeTrue()
        ->and($this->files->get($this->root.'/shared/.env'))->toBe($existing)
        ->and(file_exists($this->root.'/storage/install-bootstrap.lock'))->toBeFalse();
});

test('bootstrap never generates a key through an invalid or dangling environment link', function (bool $exists): void {
    $this->files->ensureDirectoryExists($this->root.'/shared');
    if ($exists) {
        $this->files->put($this->root.'/shared/.env', "APP_KEY=\n");
    }
    symlink($this->root.'/shared/.env', $this->root.'/.env');

    expect((new InstallerBootstrap($this->root, []))->prepare())->not->toBeNull()
        ->and(is_link($this->root.'/.env'))->toBeTrue()
        ->and(is_file($this->root.'/shared/.env'))->toBe($exists);
    if ($exists) {
        expect($this->files->get($this->root.'/shared/.env'))->toBe("APP_KEY=\n");
    }
})->with([true, false]);

test('concurrent first requests persist one complete application key', function (): void {
    $gate = $this->root.'/start-concurrent-bootstrap';
    $runner = $this->root.'/run-bootstrap.php';
    $this->files->put($runner, <<<'PHP'
<?php
require $argv[1];
while (! is_file($argv[3])) {
    usleep(1000);
}
$failure = (new InstallerBootstrap($argv[2], ['HTTP_HOST' => 'cms.example']))->prepare();
exit($failure === null ? 0 : 1);
PHP);
    $processes = [];
    for ($index = 0; $index < 2; $index++) {
        $process = proc_open(
            [PHP_BINARY, $runner, Cms::path('stubs/application/bootstrap/InstallerBootstrap.php'), $this->root, $gate],
            [['file', '/dev/null', 'r'], ['file', '/dev/null', 'a'], ['file', '/dev/null', 'a']],
            $pipes,
        );
        expect($process)->toBeResource();
        $processes[] = $process;
    }

    $this->files->put($gate, 'go');
    foreach ($processes as $process) {
        expect(proc_close($process))->toBe(0);
    }

    $environment = $this->files->get($this->root.'/.env');
    expect(substr_count($environment, 'APP_KEY='))->toBe(1)
        ->and($environment)->toMatch('/^APP_KEY="base64:[A-Za-z0-9+\/=]{44}"$/m')
        ->and(fileperms($this->root.'/.env') & 0777)->toBe(0600);
});

test('fresh application bootstrap ignores an untrusted host header', function (): void {
    $bootstrap = new InstallerBootstrap($this->root, ['HTTP_HOST' => "bad.example\r\nX-Injected: yes"]);

    expect($bootstrap->prepare())->toBeNull()
        ->and($this->files->get($this->root.'/.env'))->toContain('APP_URL="http://localhost"');
});

test('fresh application bootstrap does not trust a forwarded protocol header by itself', function (): void {
    $bootstrap = new InstallerBootstrap($this->root, ['HTTP_HOST' => 'cms.example', 'HTTP_X_FORWARDED_PROTO' => 'https']);

    expect($bootstrap->prepare())->toBeNull()
        ->and($this->files->get($this->root.'/.env'))->toContain('APP_URL="http://cms.example"');
});

test('sealed installation without its original key returns recovery guidance and never rotates it', function (): void {
    $this->files->put($this->root.'/storage/installed', '{}');
    $this->files->put($this->root.'/.env', "APP_KEY=\n");

    $failure = (new InstallerBootstrap($this->root, ['HTTP_HOST' => 'cms.example']))->prepare();

    expect($failure)->not->toBeNull()
        ->and($failure['title'])->toBe('La instalación necesita recuperación')
        ->and($this->files->get($this->root.'/.env'))->toBe("APP_KEY=\n");
});

test('bootstrap explains when Composer dependencies are absent', function (): void {
    $this->files->delete($this->root.'/vendor/autoload.php');

    $failure = (new InstallerBootstrap($this->root, []))->prepare();

    expect($failure)->not->toBeNull()
        ->and($failure['title'])->toBe('El paquete está incompleto')
        ->and(InstallerBootstrap::render($failure))->toContain('Composer');
});

test('bootstrap reports an unsupported php runtime without exposing a trace', function (): void {
    $failure = (new InstallerBootstrap($this->root, [], '8.2.20'))->prepare();

    expect($failure)->not->toBeNull()
        ->and($failure['title'])->toBe('PHP no es compatible')
        ->and(InstallerBootstrap::render($failure))->not->toContain($this->root);
});

test('bootstrap rejects a symbolic lock without changing its target or creating configuration', function (): void {
    $target = $this->root.'/operator-file';
    $this->files->put($target, 'preserved');
    symlink($target, $this->root.'/storage/install-bootstrap.lock');

    expect((new InstallerBootstrap($this->root, []))->prepare())->not->toBeNull()
        ->and($this->files->get($target))->toBe('preserved')
        ->and(file_exists($this->root.'/.env'))->toBeFalse();
});
