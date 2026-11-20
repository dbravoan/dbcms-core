<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Contracts\Services\InstallationEnvironmentServiceInterface;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Privacy\LegalPageService;
use DbCms\Core\Services\Privacy\PrivacyConfigurationService;
use Tests\Support\CmsFixtures;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    CmsFixtures::localization(['es']);
    CmsFixtures::identities(['admin' => [], 'author' => []]);
    $this->withoutVite();
    $this->instance(InstallationEnvironmentServiceInterface::class, Mockery::mock(InstallationEnvironmentServiceInterface::class)->shouldReceive('write')->zeroOrMoreTimes()->getMock());
    app(LegalPageService::class)->provision(LegalCompanyDTO::fromArray(PrivacyFixtures::company()));
});

function privacyAdmin(): User
{
    return User::where('email', 'admin@db-cms.io')->firstOrFail();
}

test('the admin can configure audit privacy while preserving existing processing', function (): void {
    $service = app(PrivacyConfigurationService::class);
    $company = app(LegalPageService::class)->company();
    $original = $company['processing'][0];
    $company['processing'][] = collect(PrivacyFixtures::processingFor(['content_audit']))->firstWhere('owner', 'content_audit');
    $company['reviewed'] = true;

    $this->actingAs(privacyAdmin())->get(route('admin.settings.privacy', ['owner' => 'content_audit']))
        ->assertOk()->assertSee('content_audit')->assertViewHas('company', fn (array $values): bool => collect($values['processing'])->contains('owner', 'content_audit') && collect($values['processing'])->contains('owner', 'core'));
    $this->actingAs(privacyAdmin())->put(route('admin.settings.privacy.update'), ['revision' => $service->revision(), 'company' => $company])
        ->assertRedirect(route('admin.settings.privacy'))->assertSessionHasNoErrors();

    $saved = app(LegalPageService::class)->company()['processing'];
    expect($saved[0])->toEqual($original)
        ->and(app(LegalPageService::class)->canCollect('content_audit', true))->toBeTrue()
        ->and(config('logging.channels.daily.days'))->toBe(30);
});

test('privacy updates require explicit review', function (): void {
    $company = app(LegalPageService::class)->company();
    $company['company_name'] = 'Unreviewed';
    $this->actingAs(privacyAdmin())->put(route('admin.settings.privacy.update'), ['revision' => app(PrivacyConfigurationService::class)->revision(), 'company' => $company])
        ->assertSessionHasErrors('reviewed');
    expect(app(LegalPageService::class)->company()['company_name'])->not->toBe('Unreviewed');
});

test('stale privacy updates do not overwrite later changes', function (): void {
    $company = app(LegalPageService::class)->company();
    $company['reviewed'] = true;
    $revision = app(PrivacyConfigurationService::class)->revision();
    app(GeneralSettingsServiceInterface::class)->set('legal.address', 'Updated elsewhere', 'legal');
    $this->actingAs(privacyAdmin())->put(route('admin.settings.privacy.update'), ['revision' => $revision, 'company' => $company])
        ->assertSessionHasErrors('revision');
    expect(app(LegalPageService::class)->company()['address'])->toBe('Updated elsewhere');
});

test('privacy configuration requires the settings permission', function (): void {
    $author = User::where('email', 'author@db-cms.io')->firstOrFail();
    $this->actingAs($author)->get(route('admin.settings.privacy'))->assertRedirect();
    $this->actingAs($author)->put(route('admin.settings.privacy.update'), [])->assertRedirect();
});
