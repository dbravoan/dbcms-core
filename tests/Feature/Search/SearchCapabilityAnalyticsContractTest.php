<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\SearchAnalyticsInterface;
use DbCms\Core\Search\Models\SearchTerm;
use Tests\Support\SearchAnalyticsComposition;

uses(SearchAnalyticsComposition::class);

test('core search analytics preserve their authorized contract without optional modules', function (): void {
    SearchTerm::create(['term' => 'calidad', 'locale' => 'es', 'market_code' => 'es', 'hits' => 4, 'results' => 2]);
    SearchTerm::create(['term' => 'quality', 'locale' => 'en', 'market_code' => 'eu', 'hits' => 7, 'results' => 3]);
    $analytics = app(SearchAnalyticsInterface::class);
    expect($analytics->topTerms('es', 10))->toBe([['term' => 'calidad', 'hits' => 4, 'results' => 2]]);
    expect($analytics->topTerms(null, 10))->toHaveCount(2);
});
