<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\Support\PassportTestKeys;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    PassportTestKeys::configure();
    app(ClientRepository::class)->createPersonalAccessGrantClient('Testing Client');
});

test('users can register via api and receive 201 response', function (): void {
    $policies = PrivacyFixtures::processingFor([]);
    $accounts = $policies[0];
    $accounts['owner'] = 'accounts';
    $accounts['manual_review'] = true;
    unset($accounts['retention_days']);
    $accounts['retention'] = 'Until the account relationship ends, subject to reviewed restrictions';
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...$policies, $accounts], 'legal', 'json');
    $payload = [
        'name' => 'Alice Agent',
        'email' => 'alice@example.com',
        'password' => 'SecurePassw0rd!123',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Alice Agent')
        ->assertJsonPath('data.email', 'alice@example.com');

    $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
});

test('users can authenticate and receive a valid oauth2 access token', function (): void {
    $user = User::factory()->create([
        'email' => 'bob@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'bob@example.com',
        'password' => 'password123',
        'scopes' => ['read:content'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
            'expires_at',
        ]);
});

test('authenticated users can revoke active passport tokens on logout', function (): void {
    $user = User::factory()->create();
    Passport::actingAs($user, ['read:content']);

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Tokens revoked successfully.']);
});
