<?php

declare(strict_types=1);

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Content\Pages\Contracts\PageDomainServiceInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Search\PageSearchSource;
use DbCms\Core\Content\Pages\Services\PageEditorialPreview;
use DbCms\Core\Content\Pages\Services\PageViewRegistry;
use DbCms\Core\Contracts\Services\PublishedSectionTextInterface;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionSyncService;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\View\Components\CmsBlock;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en'], ['es']);
    $this->market = Market::where('code', 'es')->sole();
    View::addNamespace('test-pages', PACKAGE_ROOT.'/tests/Fixtures/views');
    config(['cms.page_views' => ['example' => 'test-pages::code-page'], 'cms.page_cache.enabled' => false]);
    $this->withoutVite();
});

function bladePageFixture(string $template = 'blade:example'): Page
{
    return Page::withoutEvents(function () use ($template): Page {
        $page = Page::create(['template' => $template, 'is_published' => true, 'published_at' => now()]);
        $page->translations()->create(['locale' => 'es', 'title' => 'Code page', 'slug' => 'code-page', 'meta_description' => 'Public metadata']);
        $section = $page->sections()->create(['type' => 'rich_text', 'is_active' => true, 'status' => 'published', 'styling_json' => []]);
        $section->translations()->create(['locale' => 'es', 'content_json' => ['content' => 'DORMANT PRIVATE TEXT']]);

        return $page->refresh();
    });
}

test('registered Blade pages render without fetching dormant sections and retain publication guards', function (): void {
    $page = bladePageFixture();
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $this->get('/es/es/code-page')->assertOk()->assertSee('Blade content')->assertDontSee('DORMANT PRIVATE TEXT');
    expect(app(PageDomainServiceInterface::class)->editData($page)['sectionsData'])->toBe([]);
    expect(implode("\n", $queries))->not->toContain('`page_sections`')->not->toContain('`section_translations`');
    $this->get('/es/en/code-page')->assertNotFound();
    $page->updateQuietly(['is_published' => false]);
    $this->get('/es/es/code-page')->assertNotFound();
});

test('code-owned Blade routes use explicit public locale and market context without a CMS page', function (): void {
    Route::middleware('cms.front')->get('/__code/preview/{market}/{locale}', static fn () => view('test-pages::code-route'));
    config(['cms.page_cache.enabled' => true]);
    $this->get('/__code/preview/es/es')->assertOk()->assertSee('Code-owned route preview')->assertSee('lang="es"', false)->assertSee('data-market="es"', false)->assertSee('name="_token"', false);
    $this->get('/__code/preview/es/en')->assertOk()->assertSee('lang="en"', false);
    $this->get('/__code/preview/es/fr')->assertNotFound();
    $this->get('/__code/preview/unknown/es')->assertNotFound();
    expect(Page::query()->exists())->toBeFalse();
});

test('Blade page responses stay live even when the public page cache is enabled', function (): void {
    $page = bladePageFixture();
    config(['cms.page_cache.enabled' => true]);
    $this->get('/es/es/code-page')->assertOk()->assertSee('Code page')->assertHeaderMissing('X-Page-Cache');
    // Deliberately bypass observers: the template must not depend on cache invalidation.
    DB::table('page_translations')->where('page_id', $page->id)->update(['title' => 'Fresh code page']);
    $this->get('/es/es/code-page')->assertOk()->assertSee('Fresh code page')->assertHeaderMissing('X-Page-Cache');
});

test('Blade page metadata can change while section writes and arbitrary views are rejected', function (): void {
    $page = bladePageFixture();
    $mutations = app(EntityMutationService::class);
    $mutations->update('page', $page->id, ['translations' => ['es' => ['title' => 'Updated metadata']]]);
    expect($page->fresh()->translations()->first()->title)->toBe('Updated metadata');
    expect(fn () => $mutations->update('page', $page->id, ['sections_json' => ['es' => []]]))->toThrow(ValidationException::class);
    expect(fn () => app(SectionSyncService::class)->sync($page, '{"es":[]}'))->toThrow(ValidationException::class);
    expect(fn () => $mutations->update('page', $page->id, ['attributes' => ['template' => 'blade:../../admin.users']]))->toThrow(ValidationException::class);
    expect($page->fresh()->sections()->count())->toBe(1);
});

test('switching presentation preserves canonical section snapshots and supports restoration', function (): void {
    $page = bladePageFixture('default');
    $snapshots = app(ContentSnapshotService::class);
    $original = $snapshots->capture($page);
    app(EntityMutationService::class)->update('page', $page->id, ['attributes' => ['template' => 'blade:example']]);
    $blade = $snapshots->capture($page->refresh());
    expect($blade['snapshot_version'])->toBe(3)->and($blade['section_records'])->toEqual($original['section_records']);
    $snapshots->restore($page, $original);
    expect($page->refresh()->template)->toBe('default')->and($snapshots->hash($snapshots->capture($page)))->toBe($snapshots->hash($original));
});

test('visual Blade values survive partial updates and restore with revisions', function (): void {
    config(['cms.page_views' => [
        'example' => [
            'view' => 'test-pages::code-page',
            'fields' => [
                'heading' => ['label' => 'Heading', 'type' => 'rich_text', 'default' => ['format' => 'html', 'content' => '<p>Default heading</p>']],
                'message' => [],
            ],
        ],
        'no-fields' => 'test-pages::code-page',
    ]]);
    $page = bladePageFixture();
    $mutations = app(EntityMutationService::class);
    $snapshots = app(ContentSnapshotService::class);
    $legacy = $snapshots->capture($page);
    $url = '/es/es/code-page';
    $this->get($url)->assertOk()->assertSee('Default heading');

    $change = fn (mixed $data) => $mutations->update('page', $page->id, ['translations' => ['es' => ['template_data' => $data]]]);
    $literal = '<p><b>Hola</b> {{ ejemplo }}</p>';
    $change(['heading' => ['format' => 'html', 'content' => $literal]]);
    $change(['message' => 'Dos líneas']);
    $values = ['heading' => ['format' => 'html', 'content' => $literal], 'message' => 'Dos líneas'];
    expect($page->translations()->sole()->template_data)->toBe($values);
    $this->get($url)->assertOk()->assertSee($literal, false)->assertSee('Dos líneas');

    foreach (['not a map', ['unregistered' => 'Text'], ['heading' => ['nested']], ['heading' => str_repeat('á', 16001)], ['heading' => true]] as $invalid) {
        expect(fn () => $change($invalid))->toThrow(ValidationException::class);
    }
    expect(fn () => $mutations->update('page', $page->id, ['attributes' => ['template_data' => ['heading' => 'Untranslated']]]))->toThrow(ValidationException::class);
    $withValues = $snapshots->capture($page->refresh());
    expect($page->translations()->sole()->template_data)->toBe($values);
    $mutations->update('page', $page->id, ['attributes' => ['template' => 'blade:no-fields']]);
    expect($page->translations()->sole()->template_data)->toBeNull();
    $snapshots->restore($page, $withValues);
    expect($page->translations()->sole()->template_data)->toBe($values);
    $snapshots->restore($page, $legacy);
    expect($page->translations()->sole()->template_data)->toBeNull()
        ->and($snapshots->hash($snapshots->capture($page)))->toBe($snapshots->hash($legacy));
    $preview = app(PageEditorialPreview::class)->render($snapshots->hydrate($page, $withValues), new PublicContentContext($this->market, 'es'));
    expect($preview->data['templateData']['heading']->toHtml())->toBe($literal)
        ->and($preview->data['templateData']['message'])->toBe('Dos líneas');
    $this->get($url)->assertOk()->assertSee('Default heading')->assertDontSee('Dos líneas');
});

test('the page administration saves and clears declared Blade fields', function (): void {
    config(['cms.page_views' => ['example' => [
        'view' => 'test-pages::code-page',
        'fields' => [
            'heading' => ['label' => 'Heading'],
            'message' => ['type' => 'rich_text', 'default' => ['format' => 'html', 'content' => 'Initial message']],
        ],
    ]]]);
    CmsFixtures::identities();
    $page = bladePageFixture();
    $this->actingAs(User::where('email', 'admin@db-cms.io')->sole());
    $this->get(route('admin.pages.edit', $page))->assertOk()
        ->assertSee('name="translations[es][template_data][heading]"', false)
        ->assertSee('name="translations[en][template_data][message][content]"', false)
        ->assertSee('data-rich-text-editor', false);
    $this->put(route('admin.pages.update', $page), [
        'template' => 'blade:example',
        'translations' => [
            'es' => ['title' => 'Code page', 'slug' => 'code-page', 'template_data' => ['heading' => 'Editado en el panel', 'message' => ['format' => 'markdown', 'content' => '**Markdown**']]],
            'en' => ['title' => '', 'template_data' => ['heading' => '', 'message' => ['format' => 'html', 'content' => '']]],
        ],
        'is_published' => '1',
    ])->assertRedirect(route('admin.pages.index'))->assertSessionHasNoErrors();
    expect($page->translations()->sole()->template_data)->toBe(['heading' => 'Editado en el panel', 'message' => ['format' => 'markdown', 'content' => '**Markdown**']]);
});

test('localized Blade fields resolve per language and market and keep older revisions compatible', function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'eu']);
    config(['cms.page_views' => [
        'example' => [
            'view' => 'test-pages::code-page',
            'fields' => [
                'heading' => ['default' => 'Default heading'],
                'message' => ['default' => 'Default message'],
            ],
        ],
        'second' => ['view' => 'test-pages::code-page', 'fields' => ['heading' => []]],
    ]]);
    $page = bladePageFixture();
    $writer = app(EntityMutationService::class);
    $writer->update('page', $page->id, ['translations' => ['en' => ['title' => 'English page', 'slug' => 'english-page']]]);
    $snapshots = app(ContentSnapshotService::class);
    $legacy = $snapshots->capture($page->refresh());
    expect(array_key_exists('template_data', $legacy['translation_records'][0]))->toBeFalse();
    $this->get('/es/en/english-page')->assertOk()->assertSee('Default heading');
    $eu = Market::where('code', 'eu')->sole();
    $page->translations()->create(['locale' => 'es', 'market_id' => $eu->id, 'title' => 'Regional page', 'slug' => 'regional-page', 'template_data' => ['message' => 'Mensaje regional']]);

    $updated = $writer->update('page', $page->id, ['translations' => [
        'es' => ['template_data' => ['heading' => 'Encabezado en castellano', 'message' => 'Mensaje en castellano']],
        'en' => ['template_data' => ['heading' => 'English headline']],
    ]]);
    expect($updated['translations']['es']['template_data'])->toBe(['heading' => 'Encabezado en castellano', 'message' => 'Mensaje en castellano']);
    $writer->update('page', $page->id, ['translations' => ['en' => ['template_data' => ['message' => 'Second English field']]]]);
    $this->get('/es/es/code-page')->assertOk()->assertSee('Encabezado en castellano')->assertSee('Mensaje en castellano')->assertDontSee('English headline');
    $this->get('/es/en/english-page')->assertOk()->assertSee('English headline')->assertSee('Second English field')->assertDontSee('Encabezado en castellano');
    $this->get('/eu/es/regional-page')->assertOk()->assertSee('Encabezado en castellano')->assertSee('Mensaje regional')->assertDontSee('Mensaje en castellano');
    $localized = $snapshots->capture($page->refresh());
    $writer->update('page', $page->id, ['translations' => ['en' => ['template_data' => ['heading' => null]]]]);
    $this->get('/es/en/english-page')->assertOk()->assertSee('Default heading')->assertSee('Second English field');

    $writer->update('page', $page->id, ['attributes' => ['template' => 'blade:second']]);
    expect($page->translations()->whereNotNull('template_data')->exists())->toBeFalse();
    $snapshots->restore($page, $legacy);
    expect($snapshots->hash($snapshots->capture($page)))->toBe($snapshots->hash($legacy));
    $preview = app(PageEditorialPreview::class)->render($snapshots->hydrate($page, $localized), new PublicContentContext($eu, 'es'));
    expect($preview->data['templateData'])->toBe(['heading' => 'Encabezado en castellano', 'message' => 'Mensaje regional']);
});

test('Blade pages expose metadata without dormant section text in search and corpus', function (): void {
    $page = bladePageFixture();
    expect(app(PublishedSectionTextInterface::class)->text($page, $this->market, 'es'))->toBe('');
    $documents = iterator_to_array(app(PageSearchSource::class)->documentsFor($page->id));
    expect($documents)->not->toBeEmpty();
    foreach ($documents as $document) {
        expect($document->body)->toBe('');
    }
    $this->get('/es/es/llms-full.txt')->assertOk()->assertSee('Public metadata')->assertDontSee('DORMANT PRIVATE TEXT');
});

test('visual Blade fields keep editorial formatting and filter untrusted markup across write and read', function (): void {
    config(['cms.page_views' => ['example' => [
        'view' => 'test-pages::code-page',
        'fields' => ['article' => ['label' => 'Artículo', 'type' => 'rich_text', 'default' => ['format' => 'html', 'content' => '<p>Introducción</p>']]],
    ]]]);
    $page = bladePageFixture();
    $writer = app(EntityMutationService::class);
    $dirty = '<p style="position: absolute" onclick="test">Hola <strong>equipo</strong> {{ ejemplo }}</p><script>contenido no admitido</script>';
    $clean = '<p>Hola <strong>equipo</strong> {{ ejemplo }}</p>';
    $updated = $writer->update('page', $page->id, ['translations' => [
        'es' => ['template_data' => ['article' => ['format' => 'html', 'content' => $dirty]]],
        'en' => ['title' => 'English article', 'template_data' => ['article' => ['format' => 'markdown', 'content' => '*Welcome*']]],
    ]]);
    expect($updated['translations']['es']['template_data']['article'])->toBe(['format' => 'html', 'content' => $clean]);
    $this->get('/es/es/code-page')->assertOk()->assertSee($clean, false)->assertDontSee('contenido no admitido');
    $this->get('/es/en/english-article')->assertOk()->assertSee('<p><em>Welcome</em></p>', false)->assertDontSee('Hola');

    // Import paths and old snapshots still pass through the display boundary.
    $page->translations()->where('locale', 'es')->sole()->update(['template_data' => ['article' => ['format' => 'html', 'content' => $dirty]]]);
    $this->get('/es/es/code-page')->assertOk()->assertSee($clean, false)->assertDontSee('contenido no admitido');
    CmsFixtures::identities();
    $this->actingAs(User::where('email', 'admin@db-cms.io')->sole())->get(route('admin.pages.edit', $page))->assertOk()
        ->assertSee('data-rich-text-editor', false)
        ->assertSee($clean)
        ->assertDontSee('contenido no admitido');
    $writer->update('page', $page->id, ['translations' => ['es' => ['template_data' => ['article' => null]]]]);
    $this->get('/es/es/code-page')->assertOk()->assertSee('<p>Introducción</p>', false);
});

test('reserved page keys cannot be replaced and missing registered views are rejected', function (): void {
    config(['cms.page_views' => ['legal.notice' => 'test-pages::code-page']]);
    expect(fn () => app(PageViewRegistry::class)->all())->toThrow(LogicException::class);
    config(['cms.page_views' => ['missing' => 'test-pages::missing']]);
    expect(fn () => app(PageViewRegistry::class)->resolve('blade:missing'))->toThrow(ValidationException::class);
});

test('Blade block components validate payload and respect availability and public context', function (): void {
    $block = new class extends AbstractBlockType
    {
        public function getType(): string
        {
            return 'blade_probe';
        }

        public function getName(): string
        {
            return 'Probe';
        }

        public function getIcon(): string
        {
            return '';
        }

        public function getCategory(): string
        {
            return 'content';
        }

        public function getDefaultPayload(): array
        {
            return [];
        }

        public function getEditorSchema(): array
        {
            return ['count' => ['type' => 'integer', 'rules' => ['required', 'integer', 'between:1,2']]];
        }

        public function render(array $payload, ?Market $market = null, ?string $locale = null): string
        {
            return '<span>'.$market?->code.':'.$locale.':'.$payload['count'].'</span>{{ 6 * 7 }}';
        }
    };
    app(BlockRegistryInterface::class)->register($block);
    app()->instance('current.market', $this->market);
    app()->setLocale('es');
    expect(Blade::render('<x-cms::block type="blade_probe" :payload="[\'count\' => 2]" />'))->toContain('<span>es:es:2</span>');
    expect(Blade::render('<x-cms::block type="blade_probe" :payload="[\'count\' => 2]" />'))->toContain('{{ 6 * 7 }}');
    expect(fn () => (new CmsBlock('blade_probe', ['count' => 3]))->render())->toThrow(ValidationException::class);
    expect(Blade::render('<x-cms::block type="absent_block" />'))->toBe('');
    $this->market->update(['is_active' => false]);
    expect(Blade::render('<x-cms::block type="blade_probe" :payload="[\'count\' => 1]" />'))->toBe('');
});
