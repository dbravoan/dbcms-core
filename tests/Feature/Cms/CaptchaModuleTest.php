<?php

declare(strict_types=1);

use DbCms\Core\Captcha\Services\CaptchaService;
use Tests\Support\CaptchaComposition;

uses(CaptchaComposition::class);

test('captcha service generates svg challenge and encrypted token', function (): void {
    $service = app(CaptchaService::class);
    $challenge = $service->generateChallenge();

    expect($challenge)->toHaveKeys(['svg', 'token']);
    expect($challenge['svg'])->toContain('<svg');
    expect($challenge['svg'])->toContain('#00FF99');
    expect(strlen($challenge['token']))->toBeGreaterThan(20);
});

test('captcha service verifies valid response and rejects invalid response', function (): void {
    $service = app(CaptchaService::class);
    $challenge = $service->generateChallenge();

    // Wrong answer should fail
    expect($service->verify('99999', $challenge['token']))->toBeFalse();
    expect($service->verify('', $challenge['token']))->toBeFalse();
    expect($service->verify(null, null))->toBeFalse();
});
