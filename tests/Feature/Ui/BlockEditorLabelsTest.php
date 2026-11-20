<?php

declare(strict_types=1);

use DbCms\Core\Models\Language;
use Illuminate\Support\Facades\Blade;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
});

/** @return array<string, mixed> */
function blockEditorRuntimeOptions(string $html): array
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8">'.preg_replace('/(\s)@([a-z][a-z0-9.:-]*)=/', '$1x-on:$2=', $html));
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $node = (new DOMXPath($dom))->query('//*[@x-data][starts-with(@x-data, "blockManager(")]')->item(0);
    expect($node)->toBeInstanceOf(DOMElement::class);
    $xData = $node instanceof DOMElement ? $node->getAttribute('x-data') : '';
    // The options object is the last Js::from() argument: JSON.parse('<json string>').
    // Located by position: the payload is too large for a backtracking regex.
    $start = strrpos($xData, "JSON.parse('");
    $end = strrpos($xData, "')");
    expect($start)->toBeInt()->and($end)->toBeGreaterThan((int) $start);
    $encoded = substr($xData, (int) $start + 12, (int) $end - (int) $start - 12);
    $options = json_decode((string) json_decode('"'.$encoded.'"', flags: JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

    return is_array($options) ? $options : [];
}

test('the block manager hands the active locale editor labels to the JS runtime', function (string $locale, string $library, string $waves): void {
    app()->setLocale($locale);
    $languages = Language::where('is_active', true)->orderByDesc('is_default')->get();
    $options = blockEditorRuntimeOptions(Blade::render('<x-admin.block-manager :sections="[]" :languages="$languages" />', compact('languages')));

    expect($options)->toHaveKey('labels')
        ->and($options['labels'])->toBe(__('admin.block_editor_js'))
        ->and($options['labels']['media_library'])->toBe($library)
        ->and($options['labels']['divider_waves'])->toBe($waves);
})->with([
    'es' => ['es', 'Biblioteca multimedia', 'Olas suaves'],
    'en' => ['en', 'Media library', 'Soft waves'],
]);

test('editor label groups keep identical keys and placeholders in es and en', function (): void {
    $es = trans('admin.block_editor_js', [], 'es');
    $en = trans('admin.block_editor_js', [], 'en');
    expect($es)->toBeArray()->and($en)->toBeArray();
    /** @var array<string, string> $es */
    /** @var array<string, string> $en */
    expect(array_keys($en))->toBe(array_keys($es));
    foreach ($es as $key => $value) {
        preg_match_all('/:[a-z_]+/', $value, $esPlaceholders);
        preg_match_all('/:[a-z_]+/', $en[$key], $enPlaceholders);
        expect($enPlaceholders[0])->toBe($esPlaceholders[0], $key);
    }
});
