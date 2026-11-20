<?php

declare(strict_types=1);

use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use Tests\Support\CmsFixtures;
use Tests\Support\FormsComposition;

uses(FormsComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en']);
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('a market referenced by a form submission cannot be removed', function (): void {
    $market = Market::create([
        'code' => 'zz',
        'name' => 'With submitted form',
        'currency' => 'EUR',
        'default_locale' => 'en',
        'is_active' => true,
    ]);

    FormSubmission::create([
        'custom_form_id' => CustomForm::create(['slug' => 'market-reference', 'is_active' => true])->id,
        'payload' => ['foo' => 'bar'],
        'locale' => 'es',
        'market_id' => $market->id,
        'ip_address' => '127.0.0.1',
        'is_reviewed' => false,
    ]);

    $this->actingAs($this->admin, 'web')->delete('/admin/markets/'.$market->id)
        ->assertSessionHas('error');

    expect(Market::find($market->id))->not->toBeNull();
});
