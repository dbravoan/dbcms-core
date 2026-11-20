<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Search\Models\SearchTerm;
use DbCms\Core\Search\Services\SearchAnalyticsService;
use DbCms\Core\Search\Services\SearchSettings;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use DbCms\Core\Services\Privacy\LegalPageService;
use DbCms\Core\Services\Privacy\PrivacyConfigurationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;
use Tests\Support\PrivacyFixtures;
use Tests\Support\SearchAnalyticsComposition;

uses(SearchAnalyticsComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->instance(InstallationEnvironmentServiceInterface::class, Mockery::mock(InstallationEnvironmentServiceInterface::class)->shouldReceive('write')->zeroOrMoreTimes()->getMock());
});

test('search privacy prune removes terms outside the configured retention window', function (): void {
    app(LegalPageService::class)->provision(searchTermsPrivacyCompany(30));

    $old = SearchTerm::query()->create(['term' => 'dato antiguo', 'locale' => 'es', 'market_code' => 'es', 'hits' => 1, 'results' => 0, 'zero_results' => 1, 'last_seen_at' => now()->subDays(45)]);
    $fresh = SearchTerm::query()->create(['term' => 'dato reciente', 'locale' => 'es', 'market_code' => 'es', 'hits' => 1, 'results' => 0, 'zero_results' => 1, 'last_seen_at' => now()->subDays(5)]);

    Artisan::call('search:privacy', ['action' => 'prune']);

    expect(SearchTerm::query()->whereKey($old->getKey())->exists())->toBeFalse()
        ->and(SearchTerm::query()->whereKey($fresh->getKey())->exists())->toBeTrue();
});

test('search privacy erase requires explicit verified ids', function (): void {
    $term = SearchTerm::query()->create(['term' => 'borrar', 'locale' => 'es', 'market_code' => 'es', 'hits' => 1, 'results' => 0, 'zero_results' => 1, 'last_seen_at' => now()]);

    Artisan::call('search:privacy', ['action' => 'erase', '--id' => [$term->getKey()]]);
    expect(SearchTerm::query()->whereKey($term->getKey())->exists())->toBeTrue();

    Artisan::call('search:privacy', ['action' => 'erase', '--id' => [$term->getKey()], '--verified' => true]);
    expect(SearchTerm::query()->whereKey($term->getKey())->exists())->toBeFalse();
});

test('query recording is disabled by default and requires reviewed processing to enable', function (): void {
    app(SearchAnalyticsService::class)->record('private query', 'es', 'es', 0);
    expect(SearchTerm::query()->count())->toBe(0);
    expect(fn () => app(CoreFeatureSettings::class)->set('search', 'track_terms', true))
        ->toThrow(ValidationException::class);
});

test('recording stops immediately when its processing policy is removed', function (): void {
    config(['cache.default' => 'array']);
    $settings = app(GeneralSettingsServiceInterface::class);
    $processing = $settings->get('legal.processing', []);
    $policy = PrivacyFixtures::processingFor([])[0];
    $policy['owner'] = 'search_terms';
    $settings->set('legal.processing', [...$processing, $policy], 'legal', 'json');
    app(CoreFeatureSettings::class)->set('search', 'track_terms', true);
    app(SearchAnalyticsService::class)->record('reviewed query', 'es', 'es', 1);
    $settings->set('legal.processing', $processing, 'legal', 'json');
    app(SearchAnalyticsService::class)->record('unreviewed query', 'es', 'es', 0);
    expect(SearchTerm::query()->where('term', 'reviewed query')->exists())->toBeTrue()
        ->and(SearchTerm::query()->where('term', 'unreviewed query')->exists())->toBeFalse();
});

test('query retention cannot be configured without a finite positive window', function (): void {
    expect(fn () => app(CoreFeatureSettings::class)->set('search', 'retention_days', '0'))
        ->toThrow(ValidationException::class);
});

function searchTermsPrivacyCompany(int $days): LegalCompanyDTO
{
    $company = PrivacyFixtures::company();
    $company['processing'][] = array_replace($company['processing'][0], [
        'owner' => 'search_terms', 'purpose' => 'Review requested search terms',
        'data' => 'Search queries and aggregated result counters',
        'retention' => $days.' days after the latest query', 'retention_days' => $days,
    ]);

    return LegalCompanyDTO::fromArray($company);
}

test('reviewed search retention overrides a stale technical default', function (): void {
    app(LegalPageService::class)->provision(searchTermsPrivacyCompany(30));
    expect(app(CoreFeatureSettings::class)->get('search', 'retention_days'))->toBe(30);
    app(GeneralSettingsServiceInterface::class)->set('search.retention_days', 90, 'search', 'number');
    expect(app(SearchSettings::class)->retentionDays())->toBe(30);
});

test('reducing the reviewed search window changes pruning through privacy configuration', function (): void {
    app(LegalPageService::class)->provision(searchTermsPrivacyCompany(30));
    $expired = SearchTerm::query()->create(['term' => 'expired by revision', 'locale' => 'es', 'market_code' => 'es', 'hits' => 1, 'results' => 0, 'last_seen_at' => now()->subDays(20)]);
    $retained = SearchTerm::query()->create(['term' => 'still retained', 'locale' => 'es', 'market_code' => 'es', 'hits' => 1, 'results' => 1, 'last_seen_at' => now()->subDays(10)]);
    $privacy = app(PrivacyConfigurationService::class);
    $privacy->configure(searchTermsPrivacyCompany(15), $privacy->revision());
    expect(app(SearchSettings::class)->retentionDays())->toBe(15)
        ->and(app(CoreFeatureSettings::class)->get('search', 'retention_days'))->toBe(15);

    Artisan::call('search:privacy', ['action' => 'prune']);
    expect(SearchTerm::query()->whereKey($expired->getKey())->exists())->toBeFalse()
        ->and(SearchTerm::query()->whereKey($retained->getKey())->exists())->toBeTrue();
});

test('withdrawing search processing keeps its last reviewed window for pruning retained terms', function (): void {
    config(['cache.default' => 'array']);
    app(LegalPageService::class)->provision(searchTermsPrivacyCompany(15));
    app(CoreFeatureSettings::class)->set('search', 'track_terms', true);
    $expired = SearchTerm::query()->create(['term' => 'retained before withdrawal', 'locale' => 'es', 'market_code' => 'es', 'hits' => 1, 'results' => 0, 'last_seen_at' => now()->subDays(20)]);
    $privacy = app(PrivacyConfigurationService::class);
    $privacy->configure(LegalCompanyDTO::fromArray(PrivacyFixtures::company()), $privacy->revision());
    app(SearchAnalyticsService::class)->record('blocked after withdrawal', 'es', 'es', 0);

    expect(app(LegalPageService::class)->processing('search_terms'))->toBeNull()
        ->and(app(SearchSettings::class)->retentionDays())->toBe(15)
        ->and(app(CoreFeatureSettings::class)->get('search', 'retention_days'))->toBe(15)
        ->and(SearchTerm::query()->where('term', 'blocked after withdrawal')->exists())->toBeFalse();
    Artisan::call('search:privacy', ['action' => 'prune']);
    expect(SearchTerm::query()->whereKey($expired->getKey())->exists())->toBeFalse();
});
