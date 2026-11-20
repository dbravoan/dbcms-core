<?php

declare(strict_types=1);

use DbCms\Core\Http\Middleware\EnsureCmsIsInstalled;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities(['admin' => [], 'viewer' => ['access:admin']]);
    Storage::fake('public');
});

test('media upload rejects dangerous executable files like php and sh', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $file = UploadedFile::fake()->create('payload.php', 10, 'application/x-php');

    $response = $this->actingAs($admin, 'web')
        ->postJson(route('admin.media.upload'), [
            'file' => $file,
        ]);

    $response->assertStatus(422);
});

test('media upload accepts valid media files and returns json payload', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $file = UploadedFile::fake()->image('cyber_hero.png', 400, 300);

    $response = $this->actingAs($admin, 'web')
        ->postJson(route('admin.media.upload'), [
            'file' => $file,
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);
});

test('media manager service rejects path traversal deletion attempts', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin, 'web')
        ->deleteJson(route('admin.media.destroy', ['filename' => '../../.env']));

    $response->assertStatus(200)
        ->assertJson([
            'success' => false,
        ]);
});

test('media routes require manage:media permission and reject unauthorized roles', function (): void {
    $viewer = User::where('email', 'viewer@db-cms.io')->firstOrFail();

    $file = UploadedFile::fake()->image('test.jpg');

    $response = $this->actingAs($viewer, 'web')
        ->postJson(route('admin.media.upload'), [
            'file' => $file,
        ]);

    $response->assertStatus(403);
});

test('quick login redirects to login with error in production', function (): void {
    // Exercise production authentication independently of the installer redirect.
    $this->withoutMiddleware(EnsureCmsIsInstalled::class);
    app()['env'] = 'production';
    config([
        'app.env' => 'production',
    ]);

    $response = $this->get(route('admin.quick-login', 'admin'));

    $response->assertRedirect(route('admin.login'));
    $response->assertSessionHasErrors(['email']);
    $this->assertGuest('web');
});
