<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Models\Language;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Cms\SiteSettingsService;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;
use Tests\Support\PrivacyFixtures;

it('requires factual information and an explicit review', function (): void {
    expect(fn () => LegalCompanyDTO::fromArray([]))->toThrow(ValidationException::class);
    $company = PrivacyFixtures::company();
    unset($company['reviewed']);
    expect(fn () => LegalCompanyDTO::fromArray($company))->toThrow(ValidationException::class);
    $company = PrivacyFixtures::company() + ['commercial_activity' => true];
    unset($company['tax_id']);
    expect(fn () => LegalCompanyDTO::fromArray($company))->toThrow(ValidationException::class);
});

it('provisions legal pages without blocks and preserves edited titles', function (): void {
    Language::query()->create(['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true, 'is_default' => true]);
    $legal = app(LegalPageService::class);
    $legal->provision(LegalCompanyDTO::fromArray(PrivacyFixtures::company()));
    $page = Page::query()->where('template', 'blade:legal.privacy')->firstOrFail();
    expect($page->sections()->exists())->toBeFalse()->and($page->is_published)->toBeTrue();
    $page->translations()->where('locale', 'es')->update(['title' => 'Reviewed policy title']);
    $legal->provision(LegalCompanyDTO::fromArray(PrivacyFixtures::company()));
    expect($page->translations()->where('locale', 'es')->value('title'))->toBe('Reviewed policy title');
    expect($legal->supportsLocale('es'))->toBeTrue()->and($legal->supportsLocale('en'))->toBeTrue()->and($legal->supportsLocale('ca'))->toBeTrue()->and($legal->supportsLocale('fr'))->toBeTrue()->and($legal->supportsLocale('ja'))->toBeFalse();
    expect(fn () => Language::query()->create(['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'is_active' => true]))->toThrow(ValidationException::class);
});

it('prepares legal translations before a supported new locale becomes public', function (): void {
    Language::query()->create(['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true]);
    app(LegalPageService::class)->provision(LegalCompanyDTO::fromArray(PrivacyFixtures::company()));
    Language::query()->create(['code' => 'ca', 'name' => 'Catalan', 'native_name' => 'Català', 'is_active' => true]);
    $page = Page::query()->where('template', 'blade:legal.privacy')->firstOrFail();
    expect($page->translations()->where('locale', 'ca')->value('title'))->toBe('Política de privacitat');
});

it('protects required legal pages across shared mutations and canonical snapshot restoration', function (): void {
    Language::query()->create(['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true]);
    app(LegalPageService::class)->provision(LegalCompanyDTO::fromArray(PrivacyFixtures::company()));
    $page = Page::query()->where('template', 'blade:legal.privacy')->firstOrFail();
    $mutations = app(EntityMutationService::class);
    expect(fn () => $mutations->update('page', $page->id, ['attributes' => ['is_published' => false]]))->toThrow(ValidationException::class);
    expect(fn () => $mutations->update('page', $page->id, ['attributes' => ['template' => 'default']]))->toThrow(ValidationException::class);
    expect(fn () => $mutations->delete('page', $page->id))->toThrow(ValidationException::class);
    $snapshots = app(ContentSnapshotService::class);
    $snapshot = $snapshots->capture($page->fresh());
    $snapshot['attributes']['is_published'] = 0;
    $snapshot['translation_records'][0]['title'] = 'Historical unpublished title';
    expect(fn () => $snapshots->restore($page, $snapshot))->toThrow(ValidationException::class);
    expect($page->fresh()->is_published)->toBeTrue()->and($page->fresh()->translations->first()->title)->toBe('Política de privacidad');
});

it('refuses clearing required company settings or replacing protected legal links', function (): void {
    Language::query()->create(['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'is_active' => true]);
    app(LegalPageService::class)->provision(LegalCompanyDTO::fromArray(PrivacyFixtures::company()));
    $settings = app(SiteSettingsService::class);
    expect(fn () => $settings->update(['legal.company_name' => '', 'general.site_name' => 'Should roll back']))->toThrow(ValidationException::class);
    expect(fn () => $settings->update(['gdpr.page_privacy' => 'https://example.test/arbitrary']))->toThrow(ValidationException::class);
    expect(setting('legal.company_name'))->toBe('Example Test SL');
    $settings->update(['legal.company_name' => 'Updated Test SL']);
    expect(setting('legal.company_name'))->toBe('Updated Test SL');
});

it('renders localized core legal pages with escaped company data and no block package', function (): void {
    CmsFixtures::localization(['es', 'ca'], ['es']);
    $company = PrivacyFixtures::company();
    $company['company_name'] = 'Test <script>alert(1)</script>';
    app(LegalPageService::class)->provision(LegalCompanyDTO::fromArray($company));
    $this->withoutVite();
    $this->get('/es/es/privacidad')->assertOk()->assertSee('Política de privacidad')->assertSee('Test &lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get('/es/ca/privacitat')->assertOk()->assertSee('Política de privacitat');
});
