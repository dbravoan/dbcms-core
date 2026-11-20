<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Services\Cms\CookieConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    $policies = PrivacyFixtures::processingFor([]);
    $external = $policies[0];
    $external['owner'] = 'external_test-video';
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...$policies, $external], 'legal', 'json');
    config(['privacy.external_services' => ['test-video' => ['provider' => 'Test video provider', 'purpose' => 'Requested video playback', 'origins' => ['https://video.example.test']]]]);
});

it('starts denied, records no network identity and requires a current policy revision', function (): void {
    $state = $this->getJson('/privacy/consent')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
    expect($state['configured'])->toBeFalse()->and($state['preferences']['external'])->toBeFalse()->and($state['csrf'])->not->toBeEmpty();
    $this->postJson('/privacy/consent', ['revision' => str_repeat('0', 64), 'preferences' => ['external' => true]])->assertUnprocessable();
    expect(DB::table('privacy_consents')->count())->toBe(0);
    $saved = $this->postJson('/privacy/consent', ['revision' => $state['revision'], 'preferences' => ['necessary' => false, 'external' => true, 'injected' => true]])->assertOk()->assertCookie(CookieConsentService::COOKIE);
    expect($saved->json('preferences'))->toBe(['necessary' => true, 'external' => true]);
    $record = (array) DB::table('privacy_consents')->first();
    expect($record)->not->toHaveKeys(['ip_address', 'user_agent']);
    $cookie = collect($saved->headers->getCookies())->first(fn ($cookie): bool => $cookie->getName() === CookieConsentService::COOKIE);
    expect($cookie->isHttpOnly())->toBeTrue();
});

it('rejects forged, expired, revoked and outdated receipts', function (): void {
    $consent = app(CookieConsentService::class);
    $state = $consent->record(['external' => true]);
    $request = Request::create('/', cookies: [CookieConsentService::COOKIE => $state['id']]);
    expect($consent->hasConsent('external', $request))->toBeTrue();
    DB::table('privacy_consents')->where('id', $state['id'])->update(['revoked_at' => now()]);
    expect($consent->hasConsent('external', $request))->toBeFalse();
    DB::table('privacy_consents')->where('id', $state['id'])->update(['revoked_at' => null, 'expires_at' => now()->subSecond()]);
    expect($consent->hasGivenConsent($request))->toBeFalse();
    DB::table('privacy_consents')->where('id', $state['id'])->update(['expires_at' => now()->addDay()]);
    app(GeneralSettingsServiceInterface::class)->set('legal.company_name', 'Changed controller', 'legal');
    expect($consent->hasGivenConsent($request))->toBeFalse();
    expect($consent->hasGivenConsent(Request::create('/', cookies: [CookieConsentService::COOKIE => '{"external":true}'])))->toBeFalse();
});

it('withdraws a previous grant before creating its replacement refusal', function (): void {
    $consent = app(CookieConsentService::class);
    $state = $consent->record(['external' => true]);
    request()->cookies->set(CookieConsentService::COOKIE, $state['id']);
    $replacement = $consent->record(['external' => false]);
    expect(DB::table('privacy_consents')->where('id', $state['id'])->value('revoked_at'))->not->toBeNull();
    expect($consent->hasConsent('external', Request::create('/', cookies: [CookieConsentService::COOKIE => $replacement['id']])))->toBeFalse();
});

it('discloses only necessary storage when no optional service is configured', function (): void {
    config(['privacy.external_services' => []]);
    expect(array_keys(app(CookieConsentService::class)->getDisclosedCategories()))->toBe(['necessary']);
    $this->getJson('/privacy/consent')->assertOk()->assertJsonPath('preferences', ['necessary' => true]);
});

it('blocks undeclared media hosts and permits optional media only for a current receipt', function (): void {
    $policy = $this->getJson('/privacy/consent')->assertOk()->headers->get('Content-Security-Policy');
    expect($policy)->toContain("img-src 'self' data: blob:;")
        ->toContain("media-src 'self';")
        ->not->toContain("img-src 'self' data: blob: https:");

    $receipt = app(CookieConsentService::class)->record(['external' => true]);
    $policy = $this->withCredentials()->withUnencryptedCookie(CookieConsentService::COOKIE, $receipt['id'])
        ->getJson('/privacy/consent')->assertOk()->headers->get('Content-Security-Policy');
    expect($policy)->toContain("img-src 'self' data: blob: https://video.example.test;")
        ->toContain("media-src 'self' https://video.example.test;");

    DB::table('privacy_consents')->where('id', $receipt['id'])->update(['revoked_at' => now()]);
    $policy = $this->getJson('/privacy/consent')->assertOk()->headers->get('Content-Security-Policy');
    expect($policy)->toContain("media-src 'self';");
});

it('requires reviewed necessary asset declarations and discloses their actual destinations', function (): void {
    config(['privacy.asset_services' => ['cdn' => ['provider' => 'Necessary CDN', 'purpose' => 'Deliver site media', 'origins' => ['https://cdn.example.test']]]]);
    $consent = app(CookieConsentService::class);
    $before = $consent->revision();
    $policy = $this->getJson('/privacy/consent')->assertOk()->headers->get('Content-Security-Policy');
    expect($policy)->not->toContain('https://cdn.example.test');

    $settings = app(GeneralSettingsServiceInterface::class);
    $entries = $settings->get('legal.processing');
    $cdn = $entries[0];
    $cdn['owner'] = 'assets_cdn';
    $settings->set('legal.processing', [...$entries, $cdn], 'legal', 'json');
    $policy = $this->getJson('/privacy/consent')->assertOk()->headers->get('Content-Security-Policy');
    expect($policy)->toContain("media-src 'self' https://cdn.example.test;")
        ->and($consent->getDisclosedCategories()['necessary']['description'])->toContain('Necessary CDN', 'https://cdn.example.test')
        ->and($consent->revision())->not->toBe($before);

    $reviewed = $consent->revision();
    config(['privacy.asset_services.cdn.origins' => ['https://replacement.example.test']]);
    expect($consent->revision())->not->toBe($reviewed);
});
