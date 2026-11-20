<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InstallerRuntime;
use Tests\TestCase;

require_once __DIR__.'/Support/McpIdentity.php';

/*
|--------------------------------------------------------------------------
| Test Case Bindings
|--------------------------------------------------------------------------
*/

uses(TestCase::class)->in('Feature', 'Unit');
// Installer requests reconnect MySQL and execute DDL: they cannot share a
// transaction fixture. All other feature paths keep RefreshDatabase by default.
$transactionalFeatures = array_values(array_filter(
    glob(__DIR__.'/Feature/*') ?: [],
    static fn (string $path): bool => basename($path) !== 'Install',
));
uses(RefreshDatabase::class)->in('Unit', ...$transactionalFeatures);
uses(InstallerRuntime::class)->in('Feature/Install');

// Architecture tests boot the app (to inspect Artisan/console metadata) but do
// not touch the database.
uses(TestCase::class)->in('Architecture');

/*
|--------------------------------------------------------------------------
| Custom Pest Expectations & Helpers
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidJsonLd', function () {
    return $this->toBeArray()
        ->toHaveKey('@context')
        ->and($this->value['@context'])->toBe('https://schema.org');
});
