<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use DbCms\Core\Sections\SectionAppearance;
use Tests\TestCase;

uses(TestCase::class);

test('the shared presentation metadata declares distinct image video and poster pickers', function (): void {
    config(['privacy.external_services' => []]);
    $fields = SectionAppearance::properties();
    expect($fields['bg_image_url'])->toMatchArray(['type' => 'media', 'control' => 'media', 'media_type' => 'image'])
        ->and($fields['bg_video_url'])->toMatchArray(['type' => 'media', 'control' => 'media', 'media_type' => 'video'])
        ->and($fields['bg_video_poster'])->toMatchArray(['type' => 'media', 'control' => 'media', 'media_type' => 'image']);
});

test('the media picker and library share the registered manager and validated modal', function (): void {
    $picker = (string) file_get_contents(Cms::path('resources/views/components/admin/filemanager-modal.blade.php'));
    $library = (string) file_get_contents(Cms::path('resources/views/admin/media/index.blade.php'));
    $manager = (string) file_get_contents(Cms::path('resources/views/components/admin/media-manager.blade.php'));
    $runtime = (string) file_get_contents(Cms::path('resources/js/media-manager.js'));

    expect($picker)->toContain('<x-admin.media-manager :picker="true"')
        ->and($library)->toContain('<x-admin.media-manager')
        ->and($manager)->toContain('<x-admin.modal', "route('admin.media.files')", 'mediaManager(')
        ->not->toContain('fixed inset-0')
        ->and($runtime)->toContain('open-filemanager', 'file-selected', 'filemanager-closed')
        ->not->toContain('confirm(');
});
