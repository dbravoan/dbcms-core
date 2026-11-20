<?php

declare(strict_types=1);

namespace Tests\Support;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;

final class CmsFixtures
{
    /** @param list<string> $locales
     * @param  list<string>  $markets
     */
    public static function localization(array $locales = ['es', 'en'], array $markets = ['es']): void
    {
        foreach ($markets as $marketCode) {
            $market = Market::firstOrCreate(['code' => $marketCode], ['name' => $marketCode, 'currency' => 'EUR', 'default_locale' => $locales[0], 'is_active' => true, 'is_default' => $marketCode === $markets[0]]);
            foreach ($locales as $locale) {
                $language = Language::firstOrCreate(['code' => $locale], ['name' => $locale, 'native_name' => $locale, 'direction' => 'ltr', 'is_active' => true, 'is_default' => $locale === $locales[0]]);
                $market->languages()->syncWithoutDetaching([$language->id => ['is_default' => $locale === $locales[0]]]);
            }
        }
    }

    /** @param list<string> $locales
     * @param  list<string>  $markets
     */
    public static function home(array $locales = ['es'], array $markets = ['es']): Page
    {
        $page = Page::create(['template' => 'default', 'layout' => 'default', 'is_published' => true]);
        foreach ($locales as $locale) {
            $page->translations()->create(['locale' => $locale, 'title' => 'Fixture home', 'slug' => 'home']);
        }
        $page->markets()->sync(Market::query()->whereIn('code', $markets)->pluck('id')->all());

        return $page;
    }

    /** @param array<string,list<string>> $roles */
    public static function identities(array $roles = ['admin' => []]): void
    {
        foreach ($roles as $slug => $permissions) {
            $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
            foreach ($permissions as $permission) {
                $grant = Permission::firstOrCreate(['slug' => $permission], ['name' => $permission]);
                $role->permissions()->syncWithoutDetaching([$grant->id]);
            }
            $user = User::firstOrCreate(['email' => $slug.'@db-cms.io'], ['name' => ucfirst($slug), 'password' => 'secret123']);
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
