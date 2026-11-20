<?php

declare(strict_types=1);

use DbCms\Core\Models\User;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;
use Tests\Support\FormsCaptchaComposition;

uses(RefreshDatabase::class, FormsCaptchaComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('the form builder offers the local captcha field as part of core', function (): void {

    expect(app(ModuleManager::class)->discoverModules())->not->toHaveKey('captcha');
    $response = $this->actingAs($this->admin, 'web')->get('/admin/forms/create');

    $response->assertOk();
    $response->assertSee('&quot;key&quot;:&quot;captcha&quot;', false);
});
