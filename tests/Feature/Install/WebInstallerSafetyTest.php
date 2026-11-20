<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Tests\Support\CmsFixtures;
use Tests\Support\PrivacyFixtures;

beforeEach(fn () => CmsFixtures::localization(['es']));

function safeInstallerPayload(): array
{
    return ['company' => PrivacyFixtures::company(), 'name' => 'QA Admin', 'email' => 'new-admin@example.invalid', 'password' => 'private-password', 'password_confirmation' => 'private-password', 'site_name' => 'RegulaSE "Europa"', 'admin_path' => 'control'];
}

test('web installer rejects DSN controls and malformed database names before connection', function (): void {
    $this->postJson(route('install.test-db'), ['host' => 'mysql;dbname=other', 'port' => '0', 'database' => 'bad-name', 'username' => 'sail', 'password' => 'secret'])->assertUnprocessable()->assertJsonValidationErrors(['host', 'port', 'database']);
});

test('web installer does not save failed database credentials or pretend connection success', function (): void {
    $this->postJson(route('install.save-db'), ['host' => env('DB_HOST'), 'port' => '3306', 'database' => env('DB_DATABASE'), 'username' => 'sail', 'password' => 'incorrect-qa-password', 'auto_create' => false])->assertUnprocessable();
});

test('web migration exit failure does not claim provisioning success', function (): void {
    $kernel = Artisan::getFacadeRoot();
    try {
        Artisan::shouldReceive('call')->with('migrate', ['--force' => true], Mockery::any())->once()->andReturn(1);
        $this->postJson(route('install.run-migrations'), ['with_demo' => false])->assertStatus(500)->assertJson(['success' => false]);
    } finally {
        Artisan::swap($kernel);
    }
    expect(File::exists(storage_path('installed')))->toBeFalse();
});

test('web installer never overwrites an existing user account', function (): void {
    $original = User::factory()->create(['email' => 'new-admin@example.invalid', 'name' => 'Original']);
    $this->post(route('install.store-admin'), safeInstallerPayload())->assertSessionHasErrors('email');
    expect($original->fresh()->name)->toBe('Original')->and(File::exists(storage_path('installed')))->toBeFalse();
});

test('the completed web installer writes only the two public names to the generated readme', function (): void {
    $this->withPendingProjectReadme(function (string $path): void {
        $payload = safeInstallerPayload();
        $payload['site_name'] = 'Guía [Ágil]';
        $payload['company']['company_name'] = 'Edición *Norte* & <Sur>';
        $payload['app_url'] = 'https://installation.example.invalid';

        $this->post(route('install.store-admin'), $payload)->assertRedirectToRoute('install.step5')
            ->assertSessionMissing('install.readme_identity');
        expect(File::get($path))->toContain('## Identidad del sitio', 'Guía &#91;Ágil&#93;', 'Edición &#42;Norte&#42; &#38; &#60;Sur&#62;', 'Texto del proyecto.')
            ->not->toContain('dbcms:site:pending', 'installation.example.invalid', 'new-admin@example.invalid', 'private-password', (string) $payload['company']['contact_email']);
    });
});

test('a read-only readme keeps the site installed and offers the two names in the completion screen', function (): void {
    $this->withPendingProjectReadme(function (string $path, string $original): void {
        $payload = safeInstallerPayload();
        $this->post(route('install.store-admin'), $payload)->assertRedirectToRoute('install.step5')
            ->assertSessionHas('install.readme_identity', ['site_name' => $payload['site_name'], 'holder' => $payload['company']['company_name']]);
        $this->get(route('install.step5'))->assertOk()->assertSee($payload['site_name'])->assertSee('Añade estos dos nombres');
        expect(File::get($path))->toBe($original)
            ->and(File::exists(storage_path('installed')))->toBeTrue();
    }, 0444);
});

test('web admin finalization rolls back account creation when installation cannot be sealed', function (): void {
    $filesystem = File::getFacadeRoot();
    $failingFilesystem = Mockery::mock($filesystem);
    $failingFilesystem->shouldReceive('replace')->withAnyArgs()->andReturnUsing(fn (...$arguments) => $filesystem->replace(...$arguments))->byDefault();
    $failingFilesystem->shouldReceive('replace')->with(storage_path('installed'), Mockery::type('string'), 0600)->once()->andThrow(new RuntimeException('Installation seal cannot be written'));
    File::swap($failingFilesystem);
    $originalEnvironment = File::get(app()->environmentFilePath());
    try {
        $this->withPendingProjectReadme(function (string $path, string $original): void {
            $this->postJson(route('install.store-admin'), safeInstallerPayload())->assertServerError();
            expect(File::get($path))->toBe($original);
        });
        expect(User::where('email', 'new-admin@example.invalid')->exists())->toBeFalse()
            ->and(File::exists(storage_path('installed')))->toBeFalse()
            ->and(File::get(app()->environmentFilePath()))->toBe($originalEnvironment);
    } finally {
        File::swap($filesystem);
    }
});

test('web admin finalization restores configuration and rolls back after a late environment failure', function (): void {
    $path = app()->environmentFilePath();
    $original = File::get($path);
    $environment = Mockery::mock(InstallationEnvironmentServiceInterface::class);
    $environment->shouldReceive('write')->once()->andReturnUsing(function () use ($path): void {
        File::put($path, "APP_NAME=partial\n");
        throw new RuntimeException('Late write failure');
    });
    app()->instance(InstallationEnvironmentServiceInterface::class, $environment);
    $this->postJson(route('install.store-admin'), safeInstallerPayload())->assertServerError();
    expect(User::where('email', 'new-admin@example.invalid')->exists())->toBeFalse()
        ->and(File::get($path))->toBe($original)
        ->and(File::exists(storage_path('installed')))->toBeFalse();
});

test('web provisioning preserves existing OAuth keys and does not opt into demonstration seeding', function (): void {
    $keyRoot = storage_path();
    $this->postJson(route('install.run-migrations'))->assertOk()->assertJson(['success' => true]);
    $private = File::get($keyRoot.'/oauth-private.key');
    $public = File::get($keyRoot.'/oauth-public.key');
    expect(fileperms($keyRoot.'/oauth-private.key') & 0777)->toBe(0600)
        ->and(fileperms($keyRoot.'/oauth-public.key') & 0777)->toBe(0600)
        ->and(app(AuthorizationServer::class))->toBeInstanceOf(AuthorizationServer::class)
        ->and(app(ResourceServer::class))->toBeInstanceOf(ResourceServer::class);
    $this->postJson(route('install.run-migrations'))->assertOk()->assertJson(['success' => true]);
    expect(File::get($keyRoot.'/oauth-private.key'))->toBe($private)
        ->and(File::get($keyRoot.'/oauth-public.key'))->toBe($public)
        ->and(User::where('email', 'admin@db-cms.io')->exists())->toBeFalse();
});

test('web provisioning rejects existing invalid OAuth keys without rotating them', function (): void {
    File::put(storage_path('oauth-private.key'), 'invalid private QA');
    File::put(storage_path('oauth-public.key'), 'invalid public QA');
    $this->postJson(route('install.run-migrations'), ['with_demo' => false])->assertServerError()->assertJson(['success' => false]);
    expect(File::get(storage_path('oauth-private.key')))->toBe('invalid private QA');
});
