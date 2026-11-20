<?php

declare(strict_types=1);

use DbCms\Core\Models\User;
use DbCms\Core\Services\Media\MediaManagerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;

it('uploads real animation and audio documents through the shared media boundary', function (): void {
    $media = app(MediaManagerService::class);
    $animation = ['v' => '5.13.0', 'fr' => 30, 'ip' => 0, 'op' => 60, 'layers' => []];
    $uploaded = $media->upload(UploadedFile::fake()->createWithContent('animation.json', json_encode($animation, JSON_THROW_ON_ERROR)));
    expect($uploaded['is_animation'])->toBeTrue()->and($uploaded['filename'])->toEndWith('.json');
    $samples = str_repeat("\0\0", 100);
    $wave = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($samples)).$samples;
    $audio = $media->upload(UploadedFile::fake()->createWithContent('track.wav', $wave));
    expect($audio['is_audio'])->toBeTrue()->and($audio['filename'])->toEndWith('.wav');
    $listed = collect($media->listMedia());
    expect($listed->firstWhere('filename', $uploaded['filename'])['is_animation'])->toBeTrue()
        ->and($listed->firstWhere('filename', $audio['filename'])['is_audio'])->toBeTrue();
});

it('serves validated WebVTT subtitles with the required media type', function (): void {
    $uploaded = app(MediaManagerService::class)->upload(UploadedFile::fake()->createWithContent('captions.vtt', "WEBVTT\n\n00:00.000 --> 00:02.000\nHola\n"));
    expect($uploaded['is_doc'])->toBeTrue()->and($uploaded['mime_type'])->toBe('text/vtt');
    $this->get('/uploads/'.$uploaded['filename'])->assertOk()->assertHeader('Content-Type', 'text/vtt; charset=UTF-8');
});

it('rejects animation expressions and caption styles before storing them', function (): void {
    $media = app(MediaManagerService::class);
    $animation = ['fr' => 30, 'ip' => 0, 'op' => 60, 'layers' => [['x' => 'fetch("https://remote.test")']]];
    expect(fn () => $media->upload(UploadedFile::fake()->createWithContent('unsafe.json', json_encode($animation, JSON_THROW_ON_ERROR))))->toThrow(ValidationException::class);
    expect(fn () => $media->upload(UploadedFile::fake()->createWithContent('unsafe.vtt', "WEBVTT\n\nSTYLE\n::cue { background-image: url(https://remote.test/pixel); }\n")))->toThrow(ValidationException::class);
});

it('accepts animation uploads from the authenticated media picker through the same boundary', function (): void {
    CmsFixtures::identities();
    $this->actingAs(User::query()->where('email', 'admin@db-cms.io')->firstOrFail());
    $animation = ['fr' => 30, 'ip' => 0, 'op' => 60, 'layers' => []];
    $this->post(route('admin.media.upload'), ['file' => UploadedFile::fake()->createWithContent('motion.json', json_encode($animation, JSON_THROW_ON_ERROR))], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('success', true)->assertJsonPath('file.is_animation', true);
});
