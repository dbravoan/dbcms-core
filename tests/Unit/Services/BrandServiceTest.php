<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DbCms\Core\Contracts\Services\BrandServiceInterface;
use DbCms\Core\DTOs\BrandIdentity;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('source-catalogue')]
class BrandServiceTest extends TestCase
{
    private BrandServiceInterface $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->brand = app(BrandServiceInterface::class);
    }

    public function test_it_resolves_the_active_theme_from_config(): void
    {
        Config::set('cms.admin_theme', 'fixture-admin');

        expect($this->brand->activeTheme())->toBe('fixture-admin')
            ->and($this->brand->current()->theme)->toBe('fixture-admin');
    }

    public function test_it_reads_the_brand_from_the_theme_manifest(): void
    {
        Config::set('cms.admin_theme', 'fixture-admin');

        $brand = $this->brand->current();

        expect($brand)->toBeInstanceOf(BrandIdentity::class);
        expect($brand->name)->toBe('Fixture Admin');
        expect($brand->tagline)->toBe('External appearance');
        expect($brand->edition)->toBeNull();
        expect($brand->accent)->toBe('#123ABC');
        expect($brand->mode)->toBe('light');
        expect($brand->logo)->toBe('fixture.svg');
        expect($brand->valid)->toBeTrue();
    }

    public function test_it_rejects_an_uninstalled_admin_theme(): void
    {
        Config::set('cms.admin_theme', 'does-not-exist');

        expect(fn () => $this->brand->activeTheme())->toThrow(\DomainException::class, 'not installed');
    }

    public function test_environment_tagline_overrides_the_manifest(): void
    {
        Config::set('cms.admin_theme', 'dbcms');
        Config::set('cms.tagline', 'Custom Env Tagline');

        expect($this->brand->current()->tagline)->toBe('Custom Env Tagline');
    }

    public function test_dbcms_declares_no_edition_but_keeps_its_accent(): void
    {
        Config::set('cms.admin_theme', 'dbcms');
        Config::set('cms.tagline', null);

        $brand = $this->brand->current();

        expect($brand->edition)->toBeNull();
        expect($brand->accent)->toBe('#00FF99');
        expect($brand->mode)->toBe('dark');
        expect($brand->tagline)->toBe('Arquitectura Modular y Agéntica');
    }

    public function test_for_theme_resolves_any_theme_identity(): void
    {
        $brand = $this->brand->forTheme('fixture-admin');

        expect($brand->theme)->toBe('fixture-admin')
            ->and($brand->name)->toBe('Fixture Admin');

        expect(fn () => $this->brand->forTheme('nope'))->toThrow(\DomainException::class, 'not installed');
    }
}
