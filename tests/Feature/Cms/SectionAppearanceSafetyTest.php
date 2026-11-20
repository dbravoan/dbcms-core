<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionRendererService;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('section writes reject active markup in container and column appearance', function (array $appearance): void {
    $this->actingAs(User::where('email', 'admin@db-cms.io')->firstOrFail())
        ->postJson(route('admin.pages.store'), [
            'translations' => ['es' => ['title' => 'Safe section', 'slug' => 'safe-section']],
            'is_published' => true,
            'sections_json' => json_encode(['es' => [array_replace(['type' => 'rich_text', 'content' => 'Safe body'], $appearance)]], JSON_THROW_ON_ERROR),
        ])->assertUnprocessable();
    expect(Page::count())->toBe(0);
})->with([
    'color attribute break' => [['bg_color' => 'red;" ><span data-audit-injected="yes" x-data="{}" x-init="alert(1)">']],
    'animation attribute break' => [['animation' => 'fadeIn" x-data="{}" x-init="alert(1)']],
    'css declaration injection' => [['bg_color' => 'red; background-image:url(https://example.test/track)']],
    'column css declaration' => [['columns' => [['style' => ['padding' => '1px;background-image:url(https://example.test/track)'], 'blocks' => [['type' => 'rich_text', 'content' => 'Nested']]]]]],
]);

test('rendering persisted hostile appearance cannot produce active attributes or css', function (): void {
    $html = app(SectionRendererService::class)->renderSection([
        'type' => 'rich_text', 'content' => 'Safe body',
        'bg_color' => 'red;" ><span data-audit-injected="yes" x-data="{}" x-init="alert(1)">',
        'scroll_magic' => ['behavior' => 'none" x-data="{}" x-init="alert(1)', 'delay' => '0s" onclick="alert(1)'],
        'animation_duration' => '1s" onclick="alert(1)',
    ]);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-audit-injected or @x-init or @x-data or @onclick]')->length)->toBe(0)
        ->and($html)->toContain('Safe body')->not->toContain('background-image:url');
});

test('the visible animation delay wins over an older scroll settings copy', function (): void {
    $html = app(SectionRendererService::class)->renderSection([
        'type' => 'rich_text', 'content' => 'Content',
        'animation' => 'fadeIn',
        'animation_delay' => '1.5s',
        'scroll_magic' => ['delay' => '0.2s'],
    ]);
    expect($html)->toContain('data-scroll-delay="1.5s"')->not->toContain('data-scroll-delay="0.2s"');
});
