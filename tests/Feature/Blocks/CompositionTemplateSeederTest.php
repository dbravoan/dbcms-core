<?php

declare(strict_types=1);

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Database\Seeders\CompositionTemplateEnglish;
use DbCms\Core\Database\Seeders\CompositionTemplateSeeder;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionAppearance;
use DbCms\Core\Sections\SectionLayout;
use DbCms\Core\Services\Capabilities\ContentSectionActions;
use Illuminate\Support\Arr;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
});

it('seeds published composition templates built only from available generic blocks', function (): void {
    $this->seed(CompositionTemplateSeeder::class);

    $slugs = [
        'plantilla-hero',
        'plantilla-cta',
        'plantilla-caracteristicas',
        'plantilla-faq',
        'plantilla-planes',
        'plantilla-testimonio',
        'plantilla-galeria',
        'plantilla-estadisticas',
        'plantilla-contacto',
        'plantilla-video',
        'plantilla-pestanas',
        'plantilla-carrusel',
        'plantilla-aviso',
        'plantilla-equipo',
    ];

    $registry = app(BlockRegistryInterface::class);
    foreach ($slugs as $slug) {
        $template = BlockComponent::query()->whereHas(
            'translations',
            fn ($query) => $query->where('locale', 'es')->where('slug', $slug)
        )->first();

        expect($template)->not->toBeNull($slug)
            ->and($template?->is_published)->toBeTrue($slug)
            ->and($template?->sections)->not->toBeEmpty($slug);

        foreach ($template->sections as $section) {
            $content = (array) $section->translations()->where('locale', 'es')->value('content_json');
            $payload = SectionLayout::resolve((array) $section->styling_json, $content);
            $columns = SectionLayout::columns($payload);
            expect($columns)->not->toBeEmpty("{$slug} must declare columns")
                ->and($payload['columns_layout'] ?? null)->not->toBeNull("{$slug} must declare columns_layout");

            foreach (SectionLayout::blocks($payload) as $block) {
                $type = (string) ($block['type'] ?? '');
                expect($registry->isAvailable($type))->toBeTrue("{$slug}:{$type}");
            }
        }
    }

    $hero = BlockComponent::query()->whereHas(
        'translations',
        fn ($query) => $query->where('locale', 'es')->where('slug', 'plantilla-hero')
    )->first();
    expect($hero?->sections)->toHaveCount(2);

    $heroOpening = $hero->sections->sortBy('order')->first();
    $heroPayload = SectionLayout::resolve(
        (array) $heroOpening->styling_json,
        (array) $heroOpening->translations()->where('locale', 'es')->value('content_json')
    );
    expect($heroPayload['columns_layout'] ?? null)->toBe('1-2')
        ->and(SectionLayout::columns($heroPayload))->toHaveCount(2)
        ->and($heroPayload['bg_type'] ?? null)->toBe('image')
        ->and($heroPayload['bg_behavior'] ?? null)->toBe('parallax')
        ->and($heroPayload['animation'] ?? null)->toBe('fadeInUp')
        ->and(data_get($heroPayload, 'appearance.extent'))->toBe('viewport');

    $leftBlocks = SectionLayout::columns($heroPayload)[0]['blocks'];
    $rightBlocks = SectionLayout::columns($heroPayload)[1]['blocks'];
    expect(collect($leftBlocks)->pluck('type')->all())->toContain('heading', 'button')
        ->and(collect($rightBlocks)->pluck('type')->all())->toContain('image')
        ->and($leftBlocks[0]['id'] ?? null)->not->toBeNull();

    expect(
        Page::query()->whereHas(
            'translations',
            fn ($query) => $query->where('locale', 'es')->where(function ($inner): void {
                $inner->where('slug', 'like', 'ejemplo-%')->orWhere('slug', 'like', 'plantilla-%');
            })
        )->count()
    )->toBe(0);
});

it('seeds every template in Spanish and English with filled, valid copy', function (): void {
    $this->seed(CompositionTemplateSeeder::class);
    $registry = app(BlockRegistryInterface::class);
    $templates = BlockComponent::query()->with(['translations', 'sections.translations'])->get();
    expect($templates)->toHaveCount(count(CompositionTemplateEnglish::TEMPLATES));

    foreach ($templates as $template) {
        $slug = (string) $template->translations->firstWhere('locale', 'es')?->slug;
        $english = $template->translations->firstWhere('locale', 'en');
        expect($english?->title)->toBe(CompositionTemplateEnglish::TEMPLATES[$slug]['title'] ?? null, $slug)
            ->and($template->sections)->not->toBeEmpty($slug);

        foreach ($template->sections as $section) {
            foreach (['es', 'en'] as $locale) {
                $content = (array) $section->translations->firstWhere('locale', $locale)?->content_json;
                expect($content)->not->toBeEmpty("{$slug}:{$locale}");
                $payload = SectionLayout::resolve((array) $section->styling_json, $content);
                $blocks = SectionLayout::blocks($payload);
                expect($blocks)->not->toBeEmpty("{$slug}:{$locale}");
                foreach ($blocks as $block) {
                    $registry->validate((string) $block['type'], $block);
                }
                // The page writer rejects any appearance value its sanitizer would change.
                SectionAppearance::validate($payload);
                foreach (SectionLayout::columns($payload) as $column) {
                    SectionAppearance::validate((array) ($column['style'] ?? []));
                }
                if ($locale === 'en') {
                    $spanish = array_intersect(Arr::flatten($content), array_keys(CompositionTemplateEnglish::STRINGS));
                    expect($spanish)->toBe([], "{$slug} keeps Spanish copy in English");
                }
            }
        }
    }
});

it('keeps the same column, block and nested row identities across template languages', function (): void {
    $this->seed(CompositionTemplateSeeder::class);
    $identities = static fn (array $content): array => array_filter(
        Arr::dot($content),
        static fn (int|string $path): bool => str_ends_with((string) $path, '.id'),
        ARRAY_FILTER_USE_KEY,
    );

    foreach (BlockComponent::query()->with('sections.translations')->get() as $template) {
        foreach ($template->sections as $section) {
            $spanish = (array) $section->translations->firstWhere('locale', 'es')?->content_json;
            $english = (array) $section->translations->firstWhere('locale', 'en')?->content_json;

            expect($identities($spanish))->not->toBeEmpty()
                ->and($identities($english))->toBe($identities($spanish));
        }
    }
});

it('retrieves both template translations through the same node identity', function (): void {
    $this->seed(CompositionTemplateSeeder::class);
    CmsFixtures::identities();
    $this->actingAs(User::query()->where('email', 'admin@db-cms.io')->firstOrFail());
    $hero = BlockComponent::query()->whereHas(
        'translations',
        fn ($query) => $query->where('locale', 'es')->where('slug', 'plantilla-hero')
    )->firstOrFail();
    $opening = $hero->sections()->orderBy('order')->firstOrFail();
    $spanish = (array) $opening->translations()->where('locale', 'es')->value('content_json');
    $heading = collect($spanish['columns'][0]['blocks'])->firstWhere('type', 'heading');
    $args = ['type' => 'block_component', 'id' => $hero->id, 'node_id' => $heading['id']];
    $actions = app(ContentSectionActions::class);

    $nodes = $actions->sectionsGet($args)['nodes'];

    expect($nodes)->toHaveKeys(['es', 'en'])
        ->and($nodes['es'])->toHaveCount(1)
        ->and($nodes['en'])->toHaveCount(1)
        ->and($nodes['es'][0]['id'])->toBe($heading['id'])
        ->and($nodes['en'][0]['id'])->toBe($heading['id'])
        ->and($nodes['es'][0]['text'])->toBe($heading['text'])
        ->and($nodes['en'][0]['text'])->toBe(CompositionTemplateEnglish::STRINGS[$heading['text']])
        ->and($actions->sectionsGet($args + ['locale' => 'en'])['nodes'])->toBe(['en' => $nodes['en']]);
});
