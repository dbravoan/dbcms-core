<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Content\Pages\Models\SectionTranslation;
use DbCms\Core\Content\Pages\Services\PageEditorialPreview;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\CmsFixtures;

test('page preview uses snapshot translations and sections with market and language isolation', function (): void {
    CmsFixtures::localization(['es', 'en']);
    $market = Market::where('code', 'es')->firstOrFail();
    $page = Page::create(['template' => 'default', 'layout' => 'app', 'is_published' => true]);
    PageTranslation::create(['page_id' => $page->id, 'locale' => 'es', 'title' => 'Vigente', 'slug' => 'vigente']);
    PageTranslation::create(['page_id' => $page->id, 'locale' => 'es', 'market_id' => $market->id, 'title' => 'Regional', 'slug' => 'regional']);
    $section = PageSection::create(['page_id' => $page->id, 'type' => 'rich_text', 'order' => 1, 'is_active' => true, 'status' => 'published']);
    SectionTranslation::create(['page_section_id' => $section->id, 'locale' => 'es', 'content_json' => ['title' => 'Bloque vigente']]);
    $snapshots = app(ContentSnapshotService::class);
    $snapshot = $snapshots->capture($page);
    foreach ($snapshot['translation_records'] as &$translation) {
        if ($translation['market_id'] !== null) {
            $translation['title'] = 'Regional pendiente';
        }
    }
    unset($translation);
    $snapshot['section_records'][0]['translations'][0]['content_json'] = json_encode(['title' => 'Bloque pendiente'], JSON_THROW_ON_ERROR);
    $draft = $snapshots->hydrate($page, $snapshot);
    $renderer = Mockery::mock(SectionRendererService::class);
    $renderer->shouldReceive('renderSection')->once()->andReturnUsing(static function (Model $section, Market $market, string $locale): string {
        return (string) PublicTranslation::resolve($section, $locale, $market)?->content_json['title'];
    });
    app()->instance(SectionRendererService::class, $renderer);
    $preview = app(PageEditorialPreview::class)->render($draft, new PublicContentContext($market, 'es'));
    expect($preview->data['translation']->title)->toBe('Regional pendiente')
        ->and($preview->data['renderedSections'])->toBe(['Bloque pendiente'])
        ->and(PageTranslation::where('market_id', $market->id)->firstOrFail()->title)->toBe('Regional')
        ->and(app(PageEditorialPreview::class)->render($draft, new PublicContentContext($market, 'en'))->viewName)->toBeNull();
});

test('editorial previews keep public presentation but omit session controls', function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $user = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->actingAs($user, 'web');
    expect(view('components.frontoffice-admin-bar')->render())->toContain('cms-frontoffice-admin-bar-root');
    expect(view('themes::components.cookie-consent-modal')->render())->toContain('data-consent-endpoint');
    request()->attributes->set('cms.editorial_preview', true);
    foreach (['components.frontoffice-admin-bar', 'themes::components.cookie-consent-modal', 'themes::components.visitor-geo-modal'] as $view) {
        expect(trim(view($view)->render()))->toBe('');
    }
});
