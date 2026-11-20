<?php

declare(strict_types=1);

use DbCms\Core\Filters\FilterDimension;
use DbCms\Core\Filters\FilterPathParser;
use DbCms\Core\Filters\FilterSet;
use DbCms\Core\Filters\FilterUrlBuilder;
use DbCms\Core\Models\Market;

function v2Dimensions(): array
{
    return [
        new FilterDimension('category', 'terms', false, 10, true, implicit: true, vocabulary: static fn (): array => [
            'tecnologia' => ['label' => 'Tecnología', 'key' => 1],
            'noticias' => ['label' => 'Noticias', 'key' => 2],
        ]),
        new FilterDimension('tag', 'terms', true, 20, true, vocabulary: static fn (): array => [
            'laravel' => ['label' => 'Laravel', 'key' => 11],
            'vue' => ['label' => 'Vue', 'key' => 12],
            'aceite-de-oliva' => ['label' => 'Aceite de oliva', 'key' => 13],
        ]),
        new FilterDimension('search', 'text', false, 30),
        new FilterDimension('sort', 'enum', false, 40, true, options: [
            'recientes' => ['es' => 'Recientes', 'en' => 'Newest'],
            'antiguos' => ['es' => 'Antiguos', 'en' => 'Oldest'],
        ]),
        new FilterDimension('featured', 'bool', false, 45, true, markers: ['es' => 'destacados', 'en' => 'featured']),
        new FilterDimension('date_from', 'date', false, 50, true, bound: 'from', markers: ['es' => 'desde', 'en' => 'from']),
        new FilterDimension('date_to', 'date', false, 60, true, bound: 'to', markers: ['es' => 'hasta', 'en' => 'to']),
        new FilterDimension('price', 'range', false, 70, true, marketCurrency: true, markers: ['es' => 'precio', 'en' => 'price']),
    ];
}

function v2Builder(): FilterUrlBuilder
{
    return app(FilterUrlBuilder::class);
}

function v2Parser(): FilterPathParser
{
    return app(FilterPathParser::class);
}

function v2Market(): ?Market
{
    return Market::query()->where('code', 'es')->first();
}

test('the canonical path is markerless and round-trips', function (string $label, array $values, ?string $expected): void {
    $set = new FilterSet('blog', 'es', 'es', $values);
    $canonical = v2Builder()->canonicalPath($set, v2Dimensions(), 'es', v2Market());

    expect($canonical)->toBe($expected);

    if ($canonical !== null && $canonical !== '') {
        $reparsed = v2Parser()->parse('blog', 'es', 'es', $canonical, v2Dimensions());

        expect($reparsed?->signature())->toBe($set->signature());
    }
})->with([
    'single term' => ['tag', ['tag' => ['laravel']], 'laravel'],
    'multiple terms join with +' => ['tag', ['tag' => ['vue', 'laravel']], 'laravel+vue'],
    'hyphenated value stays intact' => ['tag', ['tag' => ['aceite-de-oliva']], 'aceite-de-oliva'],
    'implicit category is a bare segment' => ['category', ['category' => ['tecnologia']], 'tecnologia'],
    'category + tag' => ['combo', ['category' => ['tecnologia'], 'tag' => ['laravel']], 'tecnologia/laravel'],
    'category and reserved sort' => ['combo', ['category' => ['tecnologia'], 'sort' => 'recientes'], 'tecnologia/recientes'],
    'reserved enum token' => ['sort', ['sort' => 'recientes'], 'recientes'],
    'reserved flag token' => ['featured', ['featured' => true], 'destacados'],
    'range reads naturally' => ['price', ['price' => '100-200'], 'entre-100-200-eur'],
    'open lower bound' => ['price', ['price' => '100-'], 'desde-100-eur'],
    'open upper bound' => ['price', ['price' => '-200'], 'hasta-200-eur'],
    'date from' => ['date', ['date_from' => '2026-01-01'], 'desde-2026-01-01'],
    'date range' => ['dates', ['date_from' => '2026-01-01', 'date_to' => '2026-06-30'], 'desde-2026-01-01/hasta-2026-06-30'],
    'search term' => ['search', ['search' => 'mi-termino'], 'mi-termino'],
    'full combination' => ['combo', ['category' => ['tecnologia'], 'tag' => ['laravel', 'vue'], 'sort' => 'recientes'], 'tecnologia/laravel+vue/recientes'],
]);

test('connectors and values are translated for hreflang', function (): void {
    $dimensions = v2Dimensions();

    $dates = new FilterSet('blog', 'es', 'en', ['date_from' => '2026-01-01', 'date_to' => '2026-06-30']);
    expect(v2Builder()->translatedPath($dates, $dimensions, 'es', 'en', v2Market()))
        ->toBe('from-2026-01-01/to-2026-06-30');

    $price = new FilterSet('blog', 'es', 'en', ['price' => '100-200']);
    expect(v2Builder()->translatedPath($price, $dimensions, 'es', 'en', v2Market()))
        ->toBe('between-100-200-eur');
});

test('unknown and ambiguous values never resolve', function (): void {
    // Hyphens are part of one vocabulary key, never guessed separators.
    $exact = [
        new FilterDimension('tag', 'terms', true, 20, true, vocabulary: static fn (): array => [
            'laravel' => ['label' => 'Laravel', 'key' => 1],
            'vue' => ['label' => 'Vue', 'key' => 2],
            'laravel-vue' => ['label' => 'Laravel Vue', 'key' => 3],
        ]),
    ];

    expect(v2Parser()->parse('blog', 'es', 'es', 'laravel-vue', $exact)?->list('tag'))->toBe(['laravel-vue']);

    // Multiple possible hyphen splits do not invent an undeclared vocabulary key.
    $ambiguous = [
        new FilterDimension('tag', 'terms', true, 20, true, vocabulary: static fn (): array => [
            'a' => ['label' => 'A', 'key' => 1],
            'b' => ['label' => 'B', 'key' => 2],
            'c' => ['label' => 'C', 'key' => 3],
            'a-b' => ['label' => 'A B', 'key' => 4],
            'b-c' => ['label' => 'B C', 'key' => 5],
        ]),
    ];

    expect(v2Parser()->parse('blog', 'es', 'es', 'a-b-c', $ambiguous))->toBeNull();

    // Unknown term becomes a search when the module declares a text dimension.
    expect(v2Parser()->parse('blog', 'es', 'es', 'no-existe', v2Dimensions())?->string('search'))->toBe('no-existe');
});

test('the SEO limits are enforced', function (): void {
    $dimensions = v2Dimensions();

    expect(v2Parser()->parse('blog', 'es', 'es', str_repeat('x', 90), $dimensions))->toBeNull();

    $tooManyValues = new FilterSet('blog', 'es', 'es', ['tag' => ['laravel', 'vue', 'aceite-de-oliva']]);
    expect(v2Builder()->canonicalPath($tooManyValues, $dimensions, 'es', v2Market()))->not->toBeNull();

    $tooManyDimensions = new FilterSet('blog', 'es', 'es', [
        'category' => ['tecnologia'], 'tag' => ['laravel'], 'sort' => 'recientes', 'date_from' => '2026-01-01',
    ]);
    expect(v2Builder()->canonicalPath($tooManyDimensions, $dimensions, 'es', v2Market()))->toBeNull();
});
