<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es'], ['es']);
});

test('public write endpoints are throttled per IP once the limit is exceeded', function (): void {
    config(['cms.public_writes_per_minute' => 3]);

    $post = fn () => $this->post('/geo/set-preference', [
        'market' => 'es',
        'locale' => 'es',
        'target_url' => '/es/es/',
    ]);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $post()->assertRedirect();
    }

    $response = $post();

    $response->assertStatus(429);
    expect($response->headers->get('Retry-After'))->not->toBeNull();
});

test('the throttle counter is keyed by client IP', function (): void {
    config(['cms.public_writes_per_minute' => 1]);

    $payload = ['market' => 'es', 'locale' => 'es', 'target_url' => '/es/es/'];

    $this->post('/geo/set-preference', $payload)->assertRedirect();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->post('/geo/set-preference', $payload)
        ->assertRedirect();
});

test('read requests do not consume the public write budget', function (): void {
    config(['cms.public_writes_per_minute' => 1]);

    $this->get('/definitely-not-a-route')->assertNotFound();
    $this->get('/definitely-not-a-route')->assertNotFound();

    $this->post('/geo/set-preference', [
        'market' => 'es',
        'locale' => 'es',
        'target_url' => '/es/es/',
    ])->assertRedirect();
});
