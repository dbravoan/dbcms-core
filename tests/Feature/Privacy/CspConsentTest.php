<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Services\Cms\CookieConsentService;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;
use Illuminate\Http\Request;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();

    config(['privacy.external_services' => [
        'video' => [
            'provider' => 'Video provider',
            'purpose' => 'Optional embedded video',
            'origins' => ['https://video.example.test'],
        ],
    ]]);

    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [[
        'owner' => 'external_video',
        'purpose' => 'Optional embedded video',
        'legal_basis' => 'Consent',
        'data' => 'Browser connection data',
        'retention' => '30 days',
        'retention_days' => 30,
        'recipients' => 'Video provider',
        'transfers' => 'None',
    ]], 'legal', 'json');
});

test('optional CSP origins are hidden until an external-content receipt exists', function (): void {
    $policy = app(ExternalContentPolicy::class);

    expect($policy->connectOrigins())->toBe([])
        ->and($policy->frameOrigins())->toBe([]);

    $id = app(CookieConsentService::class)->record(['external' => true])['id'];
    app()->instance('request', Request::create('/', 'GET', [], [CookieConsentService::COOKIE => $id]));

    expect($policy->connectOrigins())->toContain('https://video.example.test')
        ->and($policy->frameOrigins())->toContain('https://video.example.test');
});
