<?php

declare(strict_types=1);

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Models\Market;
use Tests\Support\CmsFixtures;

test('local block actions keep the explicit market and locale while external and anchor links stay intact', function (string $type): void {
    CmsFixtures::localization(['es', 'en'], ['eu', 'uk']);
    app()->setLocale('es');
    $market = Market::where('code', 'uk')->firstOrFail();
    $block = app(BlockRegistryInterface::class)->get($type);
    expect($block)->not->toBeNull();
    $urls = ['/modules', 'https://example.test/path?a=1&b=2', '//example.test/path', '#details', ''];

    foreach ($urls as $url) {
        if ($type === 'carousel') {
            $payload = ['items' => [['children' => [['type' => 'button', 'label' => 'Open', 'url' => $url]]]]];
        } else {
            $payload = ['label' => 'Open', 'url' => $url];
        }

        $html = $block->render($payload, $market, 'en');
        if ($url === '') {
            expect($html)->not->toContain('href=');

            continue;
        }

        $dom = new DOMDocument;
        @$dom->loadHTML($html !== '' ? $html : '<div></div>');
        $hrefs = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $hrefs[] = $anchor->getAttribute('href');
        }

        expect($hrefs)->toBe([$url === '/modules' ? url('/uk/en/modules') : $url]);
    }
})->with(['button', 'carousel']);
