<?php

declare(strict_types=1);

use DbCms\Core\Filters\FilterIntroRenderer;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;

test('filter editor roundtrip preserves untranslated and unavailable sections without inventing locale content', function (): void {
    foreach (['es', 'en'] as $locale) {
        Language::firstOrCreate(['code' => $locale], ['name' => $locale, 'native_name' => $locale, 'is_active' => true, 'is_default' => $locale === 'es']);
    }
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']));
    $page = FilterPage::create(['module' => 'blog', 'locale' => 'es', 'signature' => 'category=example', 'path' => 'example', 'title' => 'Before', 'is_indexable' => true]);
    $section = $page->sections()->create(['type' => 'retired-block', 'order' => 0, 'is_active' => true, 'status' => 'published']);
    $translation = $section->translations()->create(['locale' => 'es', 'content_json' => ['type' => 'retired-block', 'title' => 'Preserved']]);
    $stored = $translation->content_json;
    $text = $page->sections()->create(['type' => 'rich_text', 'order' => 1, 'is_active' => true, 'status' => 'published']);
    $text->translations()->create(['locale' => 'es', 'content_json' => ['type' => 'rich_text', 'title' => 'Sólo español', 'content' => 'Contenido exacto español']]);

    $response = $this->actingAs($admin)->get(route('admin.seo.filters.index', ['edit' => $page->id]));
    $response->assertOk();
    $form = $response->viewData('form');
    expect($form['sections']['en'])->toBe([])
        ->and($form['sections']['es'][0]['id'])->toBe($section->id);

    $this->put(route('admin.seo.filters.update', $page), [
        'title' => 'After',
        'is_indexable' => true,
        'intro_blocks_json' => json_encode($form['sections'], JSON_THROW_ON_ERROR),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($page->fresh()->title)->toBe('After')
        ->and($section->fresh())->not->toBeNull()
        ->and($translation->fresh()->content_json)->toBe($stored)
        ->and($section->translations()->where('locale', 'en')->exists())->toBeFalse()
        ->and(app(FilterIntroRenderer::class)->render($page->fresh(), null, 'es'))->toContain('Contenido exacto español')
        ->and(app(FilterIntroRenderer::class)->render($page->fresh(), null, 'en'))->toBe('')
        ->and(app(FilterIntroRenderer::class)->text($page->fresh(), null, 'en'))->toBe('');
});
