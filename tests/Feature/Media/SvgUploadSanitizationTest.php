<?php

declare(strict_types=1);

use DbCms\Core\Services\Media\MediaManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('the media service sanitises uploaded svg files before storing them', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)">'
        .'<script>alert(2)</script>'
        .'<rect width="10" height="10"/>'
        .'</svg>';

    $file = UploadedFile::fake()->createWithContent('pixel.svg', $svg);

    $service = app(MediaManagerService::class);
    $result = $service->upload($file);

    $path = $service->getUploadDir().'/'.$result['filename'];

    expect(file_exists($path))->toBeTrue();

    $stored = (string) file_get_contents($path);

    expect($stored)
        ->not->toContain('<script')
        ->not->toContain('onload')
        ->toContain('<rect');

    @unlink($path);
});
