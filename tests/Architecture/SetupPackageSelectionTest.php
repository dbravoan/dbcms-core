<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('resolves initial themes by option and input order without writing the project', function (): void {
    $environment = app()->environmentFilePath();
    $before = file_get_contents($environment);
    $selection = [
        'theme' => ['fixture/fixture-theme', 'fixture/fixture-alternate'],
        'package' => ['fixture/fixture-alternate', 'fixture/admin-theme'],
        'block-package' => ['fixture/blocks'],
    ];

    expect(Artisan::call('cms:setup', ['--resolve-packages' => json_encode($selection, JSON_THROW_ON_ERROR)]))->toBe(0);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR))->toBe([
        'theme' => 'fixture-theme', 'admin_theme' => 'fixture-admin', 'modules' => [], 'blocks' => ['fixture_blocks'],
    ])->and(file_get_contents($environment))->toBe($before);

    $selection['package'][] = 'fixture/fixture-theme';
    unset($selection['theme']);
    expect(Artisan::call('cms:setup', ['--resolve-packages' => json_encode($selection, JSON_THROW_ON_ERROR)]))->toBe(0);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['theme'])->toBe('fixture-alternate');
});

it('validates the Composer type even for an inactive theme', function (): void {
    $selection = ['theme' => ['fixture/fixture-theme', 'fixture/admin-theme']];
    expect(Artisan::call('cms:setup', ['--resolve-packages' => json_encode($selection, JSON_THROW_ON_ERROR)]))->toBe(0);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['error'])
        ->toContain('fixture/admin-theme', '--theme', 'dbcms-theme');
});

it('requires an explicit choice when multiple transitive themes are installed', function (): void {
    expect(Artisan::call('cms:setup', ['--resolve-packages' => '{}']))->toBe(0);
    expect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['error'])
        ->toContain('--theme', 'fixture/fixture-theme', 'fixture/fixture-alternate');
});
