<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DbCms\Core\Services\Install\BrandRegistry;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('source-catalogue')]
class BrandRegistryTest extends TestCase
{
    public function test_it_discovers_on_disk_brands_and_front_themes(): void
    {
        $registry = app(BrandRegistry::class);

        expect($registry->brandIdentifiers())
            ->toContain('dbcms')
            ->toContain('fixture-admin');

        $termo = $registry->find('fixture-admin');
        expect($termo)->not->toBeNull();
        expect($termo['name'])->toBe('Fixture Admin');
        expect($termo['accent'])->toBe('#123ABC');
        expect($termo['mode'])->toBe('light');
        expect($termo['edition'])->toBeNull();
        expect((string) $termo['logo'])->toContain('fixture.svg');

        $dbcms = $registry->find('dbcms');
        expect($dbcms)->not->toBeNull();
        expect($dbcms['name'])->toBe('dbCMS');
        expect($dbcms['accent'])->toBe('#00FF99');
        expect($dbcms['logo'])->toBeNull();

        expect($registry->frontThemeIdentifiers())->toContain('fixture-theme');
    }

    public function test_it_exposes_a_neutral_fallback_and_rejects_unknown_brands(): void
    {
        $registry = app(BrandRegistry::class);

        $neutral = $registry->find(BrandRegistry::NEUTRAL_IDENTIFIER);
        expect($neutral)->not->toBeNull();
        expect($neutral['identifier'])->toBe('neutral');
        expect($neutral['accent'])->toBe('#38BDF8');
        expect($neutral['logo'])->toBeNull();

        expect($registry->find('not-a-real-brand'))->toBeNull();
    }
}
