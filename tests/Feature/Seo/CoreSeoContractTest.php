<?php

declare(strict_types=1);

use Tests\Support\CmsFixtures;

test('a core-only home never advertises an unavailable blog search action', function (): void {
    CmsFixtures::localization(['es'], ['es']);
    CmsFixtures::home();
    config(['cms.page_cache.enabled' => false]);

    $response = $this->get('/es/es/')->assertOk();
    $metadata = $response->viewData('seoMeta');

    expect($metadata->schema)->not->toHaveKey('potentialAction');
    $this->get('/es/es/blog')->assertNotFound();
});
