<?php

declare(strict_types=1);

use DbCms\Core\DTOs\Content\EntityMutationData;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionEditorService;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Sections\SectionSyncService;
use DbCms\Core\Services\Capabilities\ContentSectionActions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    app()->instance('current.market', Market::query()->where('code', 'es')->firstOrFail());
    app()->setLocale('es');
    $this->actingAs(User::query()->where('email', 'admin@db-cms.io')->firstOrFail());
});

it('keeps untouched English text with its section identity when moving a Spanish root', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ]]);
    $firstId = $initial['sections'][0]['id'];
    $actions->sectionsPatch(['type' => 'page', 'id' => $page->id, 'version' => $initial['version'], 'locale' => 'es', 'operations' => [
        ['op' => 'move', 'node_id' => (string) $firstId, 'index' => 1],
    ]]);
    $result = $actions->sectionsGet(['type' => 'page', 'id' => $page->id]);
    $firstOriginal = collect($result['sections'])->firstWhere('id', $firstId);
    expect($firstOriginal['translations']['en']['text'])->toBe('English A');
});

it('removes a Spanish root through the shared writer without duplicate identity failure', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ]]);
    $actions->sectionsPatch(['type' => 'page', 'id' => $page->id, 'version' => $initial['version'], 'locale' => 'es', 'operations' => [
        ['op' => 'remove', 'node_id' => (string) $initial['sections'][0]['id']],
    ]]);
    $result = $actions->sectionsGet(['type' => 'page', 'id' => $page->id]);
    expect($result['version'])->not->toBe($initial['version']);
    $original = collect($result['sections'])->firstWhere('id', $initial['sections'][0]['id']);
    expect($original['translations'])->not->toHaveKey('es')
        ->and($original['translations']['en']['text'])->toBe('English A');
});

it('changes optimistic version when global content changes under a regional locale override', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Global before']],
    ]]);
    $section = $page->sections()->firstOrFail();
    $section->translations()->create(['locale' => 'es', 'market_id' => Market::where('code', 'es')->value('id'), 'content_json' => ['type' => 'heading', 'text' => 'Regional unchanged']]);
    $version = $actions->sectionsGet(['type' => 'page', 'id' => $page->id])['version'];
    $section->translations()->where('locale', 'es')->whereNull('market_id')->update(['content_json' => json_encode(['type' => 'heading', 'text' => 'Global concurrent change'])]);
    expect($actions->sectionsGet(['type' => 'page', 'id' => $page->id])['version'])->not->toBe($version);
});

it('shares availability within a real canonical section render', function (): void {
    $payload = ['type' => 'text', 'columns' => [['blocks' => array_fill(0, 100, ['type' => 'heading', 'text' => 'Static node'])]]];
    DB::enableQueryLog();
    DB::flushQueryLog();
    $html = app(SectionRendererService::class)->renderSection($payload, Market::where('code', 'es')->firstOrFail(), 'es');
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect(substr_count($html, 'Static node'))->toBe(100);
    $availability = collect($queries)->filter(static fn (array $query): bool => str_contains($query['query'], 'from `block_packages`'));
    expect($availability)->toHaveCount(1);
});

it('uses the edited language for shared order without swapping other translations', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ]]);
    $firstId = $initial['sections'][0]['id'];
    $actions->sectionsPatch(['type' => 'page', 'id' => $page->id, 'version' => $initial['version'], 'locale' => 'en', 'operations' => [
        ['op' => 'move', 'node_id' => (string) $firstId, 'index' => 1],
    ]]);
    $result = $actions->sectionsGet(['type' => 'page', 'id' => $page->id]);
    expect($result['sections'][1]['id'])->toBe($firstId)
        ->and($result['sections'][1]['translations']['es']['text'])->toBe('Español A')
        ->and($result['sections'][1]['translations']['en']['text'])->toBe('English A');
});

it('inserts an English root in the requested order while preserving Spanish identities and translations', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ]]);
    $ids = array_column($initial['sections'], 'id');
    $actions->sectionsPatch(['type' => 'page', 'id' => $page->id, 'version' => $initial['version'], 'locale' => 'en', 'operations' => [
        ['op' => 'insert', 'index' => 1, 'node' => ['id' => 'new-English-root', 'type' => 'heading', 'text' => 'English inserted']],
    ]]);
    $result = $actions->sectionsGet(['type' => 'page', 'id' => $page->id])['sections'];
    expect($result[0]['id'])->toBe($ids[0])
        ->and($result[1]['translations']['en']['text'])->toBe('English inserted')
        ->and($result[1]['translations'])->not->toHaveKey('es')
        ->and($result[2]['id'])->toBe($ids[1]);
    foreach ([0 => 'A', 2 => 'B'] as $index => $suffix) {
        expect($result[$index]['translations']['es']['text'])->toBe('Español '.$suffix)
            ->and($result[$index]['translations']['en']['text'])->toBe('English '.$suffix);
    }
});

it('removes only the English translation and retains the Spanish section after the ordered English roots', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ]]);
    $ids = array_column($initial['sections'], 'id');
    $actions->sectionsPatch(['type' => 'page', 'id' => $page->id, 'version' => $initial['version'], 'locale' => 'en', 'operations' => [
        ['op' => 'remove', 'node_id' => (string) $ids[0]],
    ]]);
    $result = $actions->sectionsGet(['type' => 'page', 'id' => $page->id])['sections'];
    expect(array_column($result, 'id'))->toBe([$ids[1], $ids[0]])
        ->and($result[0]['translations']['es']['text'])->toBe('Español B')
        ->and($result[0]['translations']['en']['text'])->toBe('English B')
        ->and($result[1]['translations']['es']['text'])->toBe('Español A')
        ->and($result[1]['translations'])->not->toHaveKey('en');
});

it('ignores JSON locale key order and uses the configured active default unless order locale is explicit', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ]]);
    $ids = array_column($initial['sections'], 'id');
    $payload = app(SectionEditorService::class)->payload($page->fresh());
    $payload['en'] = array_reverse($payload['en']);
    foreach ([['en' => $payload['en'], 'es' => $payload['es']], ['es' => $payload['es'], 'en' => $payload['en']]] as $sections) {
        $result = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => $sections]);
        expect(array_column($result['sections'], 'id'))->toBe($ids);
    }
    $explicit = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => $payload, 'order_locale' => 'en']);
    expect(array_column($explicit['sections'], 'id'))->toBe(array_reverse($ids));
    Language::query()->update(['is_default' => false]);
    Language::where('code', 'en')->firstOrFail()->update(['is_default' => true]);
    $configured = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => $payload]);
    expect(array_column($configured['sections'], 'id'))->toBe(array_reverse($ids));
    Language::query()->update(['is_default' => false]);
    $fallback = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => ['es' => $payload['es'], 'en' => $payload['en']]]);
    expect(array_column($fallback['sections'], 'id'))->toBe(array_reverse($ids));
    foreach ($configured['sections'] as $section) {
        $suffix = $section['id'] === $ids[0] ? 'A' : 'B';
        expect($section['translations']['es']['text'])->toBe('Español '.$suffix)
            ->and($section['translations']['en']['text'])->toBe('English '.$suffix);
    }
});

it('transports ordering metadata through the named editorial request and form DTO without a model attribute', function (): void {
    $page = CmsFixtures::home();
    $actions = app(ContentSectionActions::class);
    $initial = $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => [
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ]]);
    $ids = array_column($initial['sections'], 'id');
    $payload = app(SectionEditorService::class)->payload($page->fresh());
    $payload['en'] = array_reverse($payload['en']);
    $input = ['translations' => ['es' => ['title' => 'Updated page']], 'sections_json' => json_encode($payload, JSON_THROW_ON_ERROR), 'sections_order_locale' => 'en'];
    $arguments = EntityMutationData::fromForm($input)->arguments;
    expect($arguments['sections_order_locale'])->toBe('en')
        ->and($arguments['attributes'])->not->toHaveKey('sections_order_locale');
    $this->put(route('admin.pages.update', $page), $input)->assertRedirect(route('admin.pages.index'))->assertSessionHasNoErrors();
    $this->get(route('admin.pages.edit', $page))->assertOk()->assertSee('name="sections_order_locale"', false);
    $result = $actions->sectionsGet(['type' => 'page', 'id' => $page->id]);
    expect(array_column($result['sections'], 'id'))->toBe(array_reverse($ids))
        ->and($result['sections'][1]['translations']['es']['text'])->toBe('Español A')
        ->and($result['sections'][1]['translations']['en']['text'])->toBe('English A');
    $invalid = $input;
    $invalid['sections_order_locale'] = ['en'];
    $this->put(route('admin.pages.update', $page), $invalid)->assertSessionHasErrors('sections_order_locale');
    expect(fn () => $actions->sectionsSync(['type' => 'page', 'id' => $page->id, 'sections' => $payload, 'order_locale' => 'inactive']))->toThrow(ValidationException::class);
});

it('carries filter ordering metadata through its local request validation and shared section writer', function (): void {
    $filter = FilterPage::create(['module' => 'blog', 'locale' => 'es', 'signature' => 'category=example', 'path' => 'example', 'title' => 'Filter', 'is_indexable' => true]);
    $sections = app(SectionSyncService::class);
    $sections->sync($filter, json_encode([
        'es' => [['type' => 'heading', 'text' => 'Español A'], ['type' => 'heading', 'text' => 'Español B']],
        'en' => [['type' => 'heading', 'text' => 'English A'], ['type' => 'heading', 'text' => 'English B']],
    ], JSON_THROW_ON_ERROR));
    $initial = $filter->sections()->get()->modelKeys();
    $payload = app(SectionEditorService::class)->payload($filter->fresh());
    $payload['en'] = array_reverse($payload['en']);
    $this->put(route('admin.seo.filters.update', $filter), ['title' => 'Filter', 'is_indexable' => true, 'intro_blocks_json' => json_encode($payload, JSON_THROW_ON_ERROR), 'sections_order_locale' => 'en'])
        ->assertRedirect(route('admin.seo.filters.index'))->assertSessionHasNoErrors();
    expect($filter->sections()->get()->modelKeys())->toBe(array_reverse($initial));
    $result = app(SectionEditorService::class)->payload($filter->fresh());
    expect($result['es'][1]['text'])->toBe('Español A')
        ->and($result['en'][1]['text'])->toBe('English A');
});
