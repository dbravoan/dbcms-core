<?php

declare(strict_types=1);

use DbCms\Core\Database\Seeders\CmsFoundationSeeder;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

test('the foundation seeder provisions a passport personal access client', function (): void {
    $this->seed(CmsFoundationSeeder::class);
    expect(Client::query()->where('grant_types', 'like', '%personal_access%')->exists())->toBeTrue();
});

test('foundation ignores revoked and unrelated provider clients and preserves a usable client', function (): void {
    $repository = app(ClientRepository::class);
    $provider = (string) config('auth.guards.api.provider');
    $revoked = $repository->createPersonalAccessGrantClient('Revoked client', $provider);
    $revoked->update(['revoked' => true]);
    $foreign = $repository->createPersonalAccessGrantClient('Other provider', 'foreign-users');
    $this->seed(CmsFoundationSeeder::class);
    $usable = $repository->personalAccessClient($provider);
    expect($usable->id)->not->toBe($foreign->id)->not->toBe($revoked->id);
    expect($usable->revoked)->toBeFalse();
    $secret = $usable->secret;
    $this->seed(CmsFoundationSeeder::class);
    expect($repository->personalAccessClient($provider)->id)->toBe($usable->id);
    expect($usable->fresh()->secret)->toBe($secret);
});

test('foundation preserves market configuration and reconciles the customized default pivot', function (): void {
    $this->seed(CmsFoundationSeeder::class);
    $market = Market::where('code', 'es')->firstOrFail();
    Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true]);
    $market->update(['name' => 'My market', 'currency' => 'USD', 'default_locale' => 'en', 'is_active' => false]);
    $this->seed(CmsFoundationSeeder::class);
    expect($market->fresh()->name)->toBe('My market');
    expect($market->fresh()->currency)->toBe('USD');
    expect($market->fresh()->is_active)->toBeFalse();
    expect($market->languages()->wherePivot('is_default', true)->pluck('code')->all())->toBe(['en']);
});
