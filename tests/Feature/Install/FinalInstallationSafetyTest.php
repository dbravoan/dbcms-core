<?php

declare(strict_types=1);

use DbCms\Core\Console\Commands\InstallCmsCommand;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\Database\Seeders\DatabaseSeeder;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    File::put(app()->environmentPath().'/company.json', json_encode(PrivacyFixtures::company(), JSON_THROW_ON_ERROR));
});

$installationOptions = static fn (): array => [
    '--company-file' => app()->environmentPath().'/company.json',
    '--db-host' => env('DB_HOST'), '--db-port' => (string) env('DB_PORT', '3306'), '--db-database' => env('DB_DATABASE'),
    '--db-username' => env('DB_USERNAME'), '--db-password' => env('DB_PASSWORD'),
    '--without-demo' => true, '--admin-name' => 'RegulaSE Admin',
    '--admin-email' => 'admin@regulase.test', '--admin-password' => 'private-test-password',
    '--site-name' => 'RegulaSE',
];

$environmentInstaller = static function (): InstallCmsCommand {
    $command = new class extends InstallCmsCommand
    {
        public function prepareEnvironment(): void
        {
            $this->ensureEnvFile();
        }
    };
    $command->setLaravel(app());
    $command->setOutput(new OutputStyle(
        new ArrayInput([]),
        new BufferedOutput,
    ));

    return $command;
};

test('CLI persists a valid injected application key without rotation', function () use ($environmentInstaller): void {
    $key = 'base64:'.base64_encode(str_repeat('b', 32));
    config(['app.key' => $key]);
    File::put(app()->environmentFilePath(), "APP_KEY=\n");
    $environmentInstaller()->prepareEnvironment();
    $stored = Dotenv\Dotenv::parse(File::get(app()->environmentFilePath()));
    expect($stored['APP_KEY'])->toBe($key)
        ->and(config('app.key'))->toBe($key)
        ->and(fileperms(app()->environmentFilePath()) & 0777)->toBe(0600);
});

test('installation ensures and updates a shared environment without replacing its link or group access', function (): void {
    $path = app()->environmentFilePath();
    $shared = app()->environmentPath().'/shared';
    File::ensureDirectoryExists($shared, 02770);
    $target = $shared.'/.env';
    File::move($path, $target);
    chmod($target, 0660);
    $group = filegroup($target);
    symlink('shared/.env', $path);
    $service = app(InstallationEnvironmentServiceInterface::class);
    $key = (string) config('app.key');
    $service->ensure();
    $service->write(['APP_NAME' => 'Provisioned application']);
    clearstatcache(true, $target);

    expect(is_link($path))->toBeTrue()
        ->and(Dotenv\Dotenv::parse(File::get($target))['APP_KEY'])->toBe($key)
        ->and(Dotenv\Dotenv::parse(File::get($target))['APP_NAME'])->toBe('Provisioned application')
        ->and(fileperms($target) & 0777)->toBe(0660)
        ->and(filegroup($target))->toBe($group);
});

test('CLI refuses a key generator that reports success without persisting a valid key', function () use ($environmentInstaller): void {
    config(['app.key' => '']);
    File::put(app()->environmentFilePath(), "APP_KEY=\n");
    $kernel = Artisan::getFacadeRoot();
    try {
        Artisan::shouldReceive('call')->with('key:generate', ['--force' => true], Mockery::any())->once()->andReturn(0);
        expect(fn () => $environmentInstaller()->prepareEnvironment())->toThrow(RuntimeException::class);
    } finally {
        Artisan::swap($kernel);
    }
});

test('CLI recognizes a quoted persisted application key without regenerating it', function () use ($environmentInstaller): void {
    $key = 'base64:'.base64_encode(str_repeat('a', 32));
    config(['app.key' => $key]);
    File::put(app()->environmentFilePath(), 'APP_KEY="'.$key.'"'."\n");
    Artisan::shouldReceive('call')->never();
    $environmentInstaller()->prepareEnvironment();
    expect(Dotenv\Dotenv::parse(File::get(app()->environmentFilePath()))['APP_KEY'])->toBe($key);
});

test('production default seeding creates foundation without demo users or content', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertExitCode(0);
        expect(Language::count())->toBeGreaterThan(0)
            ->and(Market::count())->toBeGreaterThan(0)
            ->and(User::where('email', 'admin@db-cms.io')->exists())->toBeFalse();
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('CLI configuration preserves literal special characters and private file permissions', function (): void {
    $path = app()->environmentFilePath();
    $original = File::get($path);
    $command = new class extends InstallCmsCommand
    {
        public function save(array $values): void
        {
            $this->updateEnvFile($values);
        }
    };
    app()->detectEnvironment(fn (): string => 'production');
    try {
        File::put($path, "DB_PASSWORD=old\nAPP_NAME=old\n");
        $command->save(['DB_PASSWORD' => 'literal$1#"\\value', 'APP_NAME' => 'RegulaSE Europa']);
        $parsed = Dotenv\Dotenv::parse(File::get($path));
        expect($parsed['DB_PASSWORD'])->toBe('literal$1#"\\value')
            ->and($parsed['APP_NAME'])->toBe('RegulaSE Europa')
            ->and(fileperms($path) & 0777)->toBe(0600);
        expect(fn () => $command->save(['APP_NAME' => "bad\nAPP_DEBUG=true"]))->toThrow(InvalidArgumentException::class);
    } finally {
        File::put($path, $original);
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('clean CLI installation initializes markets and languages without demo accounts', function () use ($installationOptions): void {
    $this->artisan('cms:install', $installationOptions())->assertExitCode(0);
    expect(Language::count())->toBeGreaterThan(0)
        ->and(Market::count())->toBeGreaterThan(0)
        ->and(User::where('email', 'admin@regulase.test')->exists())->toBeTrue()
        ->and(User::where('email', 'admin@db-cms.io')->exists())->toBeFalse()
        ->and(File::exists(storage_path('installed')))->toBeTrue();
});

test('CLI migration failure never seals a successful installation', function () use ($installationOptions): void {
    $originalKernel = Artisan::getFacadeRoot();
    $kernel = Mockery::mock($originalKernel);
    $kernel->shouldReceive('call')->withAnyArgs()->andReturnUsing(
        static fn ($command, $parameters = [], $output = null): int => $originalKernel->call($command, $parameters, $output)
    )->byDefault();
    $kernel->shouldReceive('call')->with('migrate', ['--force' => true], Mockery::any())->once()->andReturn(1);
    Artisan::swap($kernel);
    $this->artisan('cms:install', $installationOptions())->assertExitCode(1);
    expect(File::exists(storage_path('installed')))->toBeFalse();
});

test('CLI never invents a public administrator password', function () use ($installationOptions): void {
    $options = $installationOptions();
    unset($options['--admin-password']);
    $this->artisan('cms:install', $options)->assertExitCode(1);
    expect(User::where('email', 'admin@regulase.test')->exists())->toBeFalse()
        ->and(File::exists(storage_path('installed')))->toBeFalse();
});

test('unattended CLI requires an explicit administrator email', function () use ($installationOptions): void {
    $options = $installationOptions();
    unset($options['--admin-email']);
    $options['--no-interaction'] = true;
    $this->artisan('cms:install', $options)->assertExitCode(1);
    expect(User::count())->toBe(0)
        ->and(File::exists(storage_path('installed')))->toBeFalse();
});
