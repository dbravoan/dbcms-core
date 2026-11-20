<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\BrandServiceInterface;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('the shared brand name is the brand identity, kept apart from the site name', function (): void {
    config(['cms.name' => 'SiteSentinel']);

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin, 'web')->get(route('admin.dashboard'));

    $response->assertOk();

    $brandName = app(BrandServiceInterface::class)->current()->name;

    expect($response->viewData('cmsSiteName'))->toBe('SiteSentinel')
        ->and($response->viewData('cmsBrandName'))->toBe($brandName)
        ->and($response->viewData('cmsBrandName'))->not->toBe('SiteSentinel');
});
