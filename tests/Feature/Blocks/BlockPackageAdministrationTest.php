<?php

declare(strict_types=1);

use DbCms\Core\Blocks\BlockPackageManager;
use DbCms\Core\Models\User;
use Tests\Support\CmsFixtures;

test('block package administration is available in either backoffice theme', function (string $theme): void {
    config(['cms.admin_theme' => $theme]);
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->actingAs(User::where('email', 'admin@db-cms.io')->firstOrFail());
    $this->get(route('admin.block-packages.index'))->assertOk()->assertSee('Fixture blocks')->assertSee('fixture/blocks')->assertDontSee('dbravoan/dbcms-blocks-basic')->assertDontSee('dbravoan/dbcms-blocks-charts');
    $this->post(route('admin.block-packages.toggle', 'fixture_blocks'), ['enabled' => false])->assertRedirect()->assertSessionHasNoErrors();
    expect(app(BlockPackageManager::class)->isEnabled('fixture_blocks'))->toBeFalse();
})->with(['dbcms', 'fixture-admin']);

test('users without block management permission cannot view or change package availability', function (): void {
    CmsFixtures::identities(['editor' => ['access:admin']]);
    $this->actingAs(User::where('email', 'editor@db-cms.io')->firstOrFail());
    $this->get(route('admin.block-packages.index'))->assertForbidden();
    $this->post(route('admin.block-packages.toggle', 'fixture_blocks'), ['enabled' => false])->assertForbidden();
});
