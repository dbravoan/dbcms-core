<?php

declare(strict_types=1);

use DbCms\Core\Models\MediaFile;
use DbCms\Core\Models\User;
use Illuminate\Support\Str;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities(['admin' => [], 'viewer' => ['access:admin']]);
});

function libraryFile(string $name, string $mime = 'image/webp', ?string $title = null): MediaFile
{
    return MediaFile::query()->create([
        'uuid' => (string) Str::uuid(), 'filename' => $name, 'original_name' => $name,
        'title' => $title ?? $name, 'alt_text' => '', 'caption' => null,
        'mime_type' => $mime, 'size_bytes' => 123, 'url' => '/uploads/'.$name,
    ]);
}

it('serves an HTML media library and a separate JSON feed', function (): void {
    $this->actingAs(User::query()->where('email', 'admin@db-cms.io')->firstOrFail());
    $this->get(route('admin.media.index'))->assertOk()->assertViewIs('admin.media.index')
        ->assertSee('data-media-manager="library"', false)->assertSee('Biblioteca multimedia');
    $this->getJson(route('admin.media.index'))->assertOk()->assertViewIs('admin.media.index');
    $this->getJson(route('admin.media.files'))->assertOk()->assertJsonCount(0, 'files')
        ->assertJsonPath('pagination.total', 0)->assertJsonPath('pagination.last_page', 1);
});

it('requires manage media on the library and the shared feed', function (): void {
    $this->actingAs(User::query()->where('email', 'viewer@db-cms.io')->firstOrFail());
    $this->get(route('admin.media.index'))->assertForbidden();
    $this->getJson(route('admin.media.files'))->assertForbidden();
});

it('searches metadata and filenames, filters type, and paginates in the shared feed', function (): void {
    $this->actingAs(User::query()->where('email', 'admin@db-cms.io')->firstOrFail());
    libraryFile('cover.webp', title: 'Portada ÁRBOL');
    libraryFile('notes.pdf', 'application/pdf', 'Portada PDF');
    libraryFile('voice.wav', 'audio/wav', 'Portada audio');
    $this->getJson(route('admin.media.files', ['search' => 'árbol', 'type' => 'image']))
        ->assertOk()->assertJsonCount(1, 'files')->assertJsonPath('files.0.filename', 'cover.webp');
    $this->getJson(route('admin.media.files', ['search' => 'Portada', 'type' => 'doc']))
        ->assertOk()->assertJsonCount(1, 'files')->assertJsonPath('files.0.filename', 'notes.pdf');
    $this->getJson(route('admin.media.files', ['search' => 'voice.wav', 'type' => 'audio']))
        ->assertOk()->assertJsonPath('files.0.is_audio', true);
    foreach (range(1, 11) as $number) {
        libraryFile('sample-'.$number.'.webp');
    }
    $this->getJson(route('admin.media.files', ['search' => 'sample-', 'per_page' => 10, 'page' => 2]))
        ->assertOk()->assertJsonCount(1, 'files')->assertJsonPath('pagination.total', 11)
        ->assertJsonPath('pagination.current_page', 2)->assertJsonPath('pagination.from', 11);
    $this->getJson(route('admin.media.files', ['search' => 'sample-', 'per_page' => 10, 'page' => 99]))
        ->assertOk()->assertJsonPath('pagination.current_page', 2);
});

it('validates the shared feed query without accepting arbitrary page sizes or types', function (): void {
    $this->actingAs(User::query()->where('email', 'admin@db-cms.io')->firstOrFail());
    $this->getJson(route('admin.media.files', ['type' => 'php', 'page' => 0, 'per_page' => 999]))
        ->assertUnprocessable()->assertJsonValidationErrors(['type', 'page', 'per_page']);
});
