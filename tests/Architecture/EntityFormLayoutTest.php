<?php

declare(strict_types=1);

/**
 * Enforces the canonical entity-form layout: every localized + market-aware
 * entity form must expose the language tabs (with flags) before the market
 * picker, so the user always finds them in the same place regardless of theme.
 */

/**
 * @return array<string, string> relative file => label
 */
function entityFormFiles(): array
{
    return [
        'resources/views/admin/pages/_form.blade.php' => 'Pages',
        'resources/views/forms/admin/_form.blade.php' => 'Forms',
    ];
}

test('every entity form renders language tabs before the market picker', function (): void {
    foreach (entityFormFiles() as $relative => $label) {
        $path = PACKAGE_ROOT.'/'.$relative;
        expect(file_exists($path))->toBeTrue("Missing entity form [{$relative}].");

        $content = (string) file_get_contents($path);

        $hasTabs = str_contains($content, 'locale-tabs');
        $hasMarket = str_contains($content, 'market-picker');

        expect($hasTabs)->toBeTrue("{$label} form must use <x-admin.locale-tabs>.");
        expect($hasMarket)->toBeTrue("{$label} form must use <x-admin.market-picker>.");

        $tabsPos = strpos($content, 'locale-tabs');
        $marketPos = strpos($content, 'market-picker');

        expect($tabsPos)->toBeLessThan(
            $marketPos,
            "{$label} form must render the language tabs before the market picker."
        );
    }
});

test('entity forms do not hardcode a language flag map', function (): void {
    foreach (entityFormFiles() as $relative => $label) {
        $content = (string) file_get_contents(PACKAGE_ROOT.'/'.$relative);

        // Flags come from the backoffice (shared by AdminLayoutComposer / locale-tabs).
        expect(str_contains($content, '$flags = ['))
            ->toBeFalse("{$label} form must not hardcode a flag map.");
    }
});
