<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;

test('redirected validation feedback stays visible in the native and inherited administrative layouts', function (string $theme): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    Route::middleware('web')->post('/__fixture/reject', static function (): never {
        throw ValidationException::withMessages(['privacy' => 'Revisa el tratamiento antes de guardar.']);
    });
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->actingAs($admin)->from(route('admin.pages.index'))->post('/__fixture/reject')
        ->assertRedirect(route('admin.pages.index'))->assertSessionHasErrors('privacy');
    $this->withCookie((string) config('session.cookie'), session()->getId())->get(route('admin.pages.index'))->assertOk()->assertSee('Revisa el tratamiento antes de guardar.');
})->with(['dbcms', 'fixture-admin']);
