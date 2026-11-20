<?php

declare(strict_types=1);

use DbCms\Core\Captcha\Services\CaptchaService;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Forms\Blocks\CustomFormBlock;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\CustomFormTranslation;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Services\Cms\CookieConsentService;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\CmsFixtures;
use Tests\Support\FormsCaptchaComposition;
use Tests\Support\PrivacyFixtures;

uses(RefreshDatabase::class, FormsCaptchaComposition::class);

function contactFormWithCaptcha(): CustomForm
{
    $form = CustomForm::create(['slug' => 'contacto', 'is_active' => true]);

    CustomFormTranslation::create([
        'custom_form_id' => $form->id,
        'locale' => 'es',
        'name' => 'Contacto',
        'submit_button_text' => 'Enviar',
    ]);

    FormField::create(['custom_form_id' => $form->id, 'field_key' => 'nombre', 'type' => 'text', 'is_required' => true, 'order' => 1]);
    FormField::create(['custom_form_id' => $form->id, 'field_key' => 'captcha', 'type' => 'captcha', 'is_required' => true, 'order' => 2]);

    return $form;
}

function setCaptchaSettings(array $settings): void
{
    $processing = (array) setting('legal.processing', []);
    if (! collect($processing)->contains('owner', 'captcha')) {
        $captcha = array_values(array_filter(PrivacyFixtures::processingFor([], ['captcha']), static fn (array $entry): bool => $entry['owner'] === 'captcha'));
        app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...$processing, ...$captcha], 'legal', 'json');
    }
    $service = app(CoreFeatureSettings::class);

    foreach ($settings as $key => $value) {
        $service->set('captcha', $key, $value);
    }
}

beforeEach(function (): void {
    CmsFixtures::localization(['es']);
    view()->share('errors', new ViewErrorBag);
});

test('the internal driver is the safe default and needs no keys', function (): void {
    $service = app(CaptchaService::class);

    expect($service->driver())->toBe(CaptchaService::DRIVER_INTERNAL)
        ->and($service->activeDrivers())->toBe([CaptchaService::DRIVER_INTERNAL])
        ->and($service->requiredInputs())->toBe(['captcha_response'])
        ->and($service->challenge()['internal'])->not->toBeNull();
});

test('an external driver without keys falls back to the internal challenge', function (): void {
    setCaptchaSettings(['driver' => 'recaptcha']);

    $service = app(CaptchaService::class);

    expect($service->driver())->toBe(CaptchaService::DRIVER_INTERNAL)
        ->and($service->challenge()['provider'])->toBeNull();
});

test('the recaptcha driver remains inert and keeps its local alternative', function (): void {
    setCaptchaSettings(['driver' => 'recaptcha', 'site_key' => 'SITE-KEY', 'secret_key' => 'SECRET']);
    contactFormWithCaptcha();

    $service = app(CaptchaService::class);

    expect($service->driver())->toBe(CaptchaService::DRIVER_RECAPTCHA)
        ->and($service->requiredInputs())->toBe(['captcha_response']);

    $html = (new CustomFormBlock)->render(['form_slug' => 'contacto'], null, 'es');

    expect($html)->toContain('g-recaptcha-response')
        ->and($html)->toContain('google.com/recaptcha/api.js?render=SITE-KEY')
        ->and($html)->toContain('name="captcha_response"')->toContain('data-cms-consent="external"')->toContain('<template>');
});

test('the turnstile driver renders the cloudflare widget', function (): void {
    setCaptchaSettings(['driver' => 'turnstile', 'site_key' => 'SITE-KEY', 'secret_key' => 'SECRET']);
    contactFormWithCaptcha();

    $service = app(CaptchaService::class);

    expect($service->driver())->toBe(CaptchaService::DRIVER_TURNSTILE)
        ->and($service->requiredInputs())->toBe(['captcha_response']);

    $html = (new CustomFormBlock)->render(['form_slug' => 'contacto'], null, 'es');

    expect($html)->toContain('cf-turnstile')
        ->and($html)->toContain('challenges.cloudflare.com/turnstile/v0/api.js');
});

test('the recaptcha score threshold decides the outcome', function (): void {
    setCaptchaSettings(['driver' => 'recaptcha', 'site_key' => 'SITE', 'secret_key' => 'SECRET']);
    request()->cookies->set('cms_cookie_consent', app(CookieConsentService::class)->record(['external' => true])['id']);
    $service = app(CaptchaService::class);

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::sequence()
            ->push(['success' => true, 'score' => 0.9])
            ->push(['success' => true, 'score' => 0.1])
            ->push(['success' => false])
            ->push(['success' => true, 'score' => 0.6]),
    ]);

    expect($service->verifyExternal('recaptcha', 'token'))->toBeTrue();
    expect($service->verifyExternal('recaptcha', 'token'))->toBeFalse();
    expect($service->verifyExternal('recaptcha', 'token'))->toBeFalse();

    setCaptchaSettings(['min_score' => '0.8']);
    expect($service->verifyExternal('recaptcha', 'token'))->toBeFalse();
});

test('turnstile verifies through the cloudflare endpoint', function (): void {
    setCaptchaSettings(['driver' => 'turnstile', 'site_key' => 'SITE', 'secret_key' => 'SECRET']);
    request()->cookies->set('cms_cookie_consent', app(CookieConsentService::class)->record(['external' => true])['id']);
    $service = app(CaptchaService::class);

    Http::fake([
        'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::sequence()
            ->push(['success' => true])
            ->push(['success' => false]),
    ]);

    expect($service->verifyExternal('turnstile', 'token'))->toBeTrue();
    expect($service->verifyExternal('turnstile', 'token'))->toBeFalse();
});

test('the both driver passes when either provider verifies', function (): void {
    setCaptchaSettings(['driver' => 'both', 'site_key' => 'SITE', 'secret_key' => 'SECRET']);
    request()->cookies->set('cms_cookie_consent', app(CookieConsentService::class)->record(['external' => true])['id']);
    $service = app(CaptchaService::class);

    expect($service->activeDrivers())->toBe([CaptchaService::DRIVER_INTERNAL, CaptchaService::DRIVER_RECAPTCHA]);

    // The internal challenge is checked first and short-circuits the provider.
    $internal = $service->generateChallenge();
    preg_match('/(\d+)\s*\+\s*(\d+)/', $internal['svg'], $m);

    Http::fake([
        'www.google.com/recaptcha/api/siteverify' => Http::sequence()
            ->push(['success' => true, 'score' => 0.9])
            ->push(['success' => false]),
    ]);

    expect($service->verifyRequest([
        'captcha_response' => (string) ((int) $m[1] + (int) $m[2]),
        '_captcha_token' => $internal['token'],
    ]))->toBeTrue();

    // Google alone is enough.
    expect($service->verifyRequest(['g-recaptcha-response' => 'google-token']))->toBeTrue();

    // Neither provider verifies.
    expect($service->verifyRequest([
        'captcha_response' => 'bad',
        '_captcha_token' => $internal['token'],
        'g-recaptcha-response' => 'google-token',
    ]))->toBeFalse();
});

test('a submission is validated against the active external driver', function (): void {
    setCaptchaSettings(['driver' => 'recaptcha', 'site_key' => 'SITE', 'secret_key' => 'SECRET']);
    contactFormWithCaptcha();

    // Without a token the submission is rejected.
    $this->post('/forms/contacto/submit', ['nombre' => 'Ana'])
        ->assertSessionHasErrors(['captcha_response']);

    expect(FormSubmission::count())->toBe(0);

    // With a valid token it goes through.
    Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true, 'score' => 0.9])]);

    $receipt = app(CookieConsentService::class)->record(['external' => true]);
    $this->withUnencryptedCookie('cms_cookie_consent', $receipt['id'])->post('/forms/contacto/submit', ['nombre' => 'Ana', 'g-recaptcha-response' => 'token'])
        ->assertSessionHasNoErrors();

    expect(FormSubmission::count())->toBe(1);
});

test('the csp allows the external provider frames only while its driver is active', function (): void {
    CmsFixtures::localization();
    CmsFixtures::home();
    setCaptchaSettings(['driver' => 'recaptcha', 'site_key' => 'SITE', 'secret_key' => 'SECRET']);

    $policy = (string) $this->get('/es/es')->assertOk()->headers->get('Content-Security-Policy');

    expect($policy)->toContain("frame-src 'self' https://www.google.com")
        ->and($policy)->toContain('connect-src')->toContain('https://www.google.com');

    setCaptchaSettings(['driver' => 'internal']);

    $internalPolicy = (string) $this->get('/es/es')->assertOk()->headers->get('Content-Security-Policy');

    expect($internalPolicy)->not->toContain('https://www.google.com')
        ->and($internalPolicy)->not->toContain('challenges.cloudflare.com');
});

test('external verification sends nothing before consent and the local fallback still works', function (): void {
    setCaptchaSettings(['driver' => 'recaptcha', 'site_key' => 'SITE', 'secret_key' => 'SECRET']);
    Http::fake();
    $service = app(CaptchaService::class);
    expect($service->verifyExternal('recaptcha', 'token'))->toBeFalse();
    $challenge = $service->generateChallenge();
    preg_match('/(\d+)\s*\+\s*(\d+)/', $challenge['svg'], $matches);
    expect($service->verifyRequest(['captcha_response' => (string) ((int) $matches[1] + (int) $matches[2]), '_captcha_token' => $challenge['token']]))->toBeTrue();
    Http::assertNothingSent();
});
