<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\CapabilityExecutor;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Menu;
use DbCms\Core\Models\MenuItem;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Capabilities\ActorContextFactory;
use DbCms\Core\Services\Capabilities\ResourceCrudService;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Support\PrivacyFixtures;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->admin->roles()->attach($role->getKey());
    $this->actingAs($this->admin);
    $this->language = Language::firstOrCreate(['code' => 'es'], ['name' => 'Español', 'native_name' => 'Español', 'direction' => 'ltr', 'is_default' => true, 'is_active' => true]);
    $this->market = Market::firstOrCreate(['code' => 'es'], ['name' => 'España', 'currency' => 'EUR', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
});

test('resource executor and web both protect default markets and languages', function (): void {
    foreach (['market' => $this->market, 'language' => $this->language] as $key => $model) {
        $this->delete(route($key === 'market' ? 'admin.markets.destroy' : 'admin.languages.destroy', $model))->assertSessionHas('error');
        mcpTestBindIdentity($this->admin);
        $actor = app(ActorContextFactory::class)->fromRequest(request(), 'mcp');
        $result = app(CapabilityExecutor::class)->execute('cms_resource_delete', ['resource' => $key, 'id' => $model->getKey()], $actor, approved: true);
        expect($result)->toHaveKey('validation');
        expect($model->fresh())->not->toBeNull();
    }
});

test('resource mutations preserve the bundled locale code used by regional legal translations', function (): void {
    app(LegalPageService::class)->provision(LegalCompanyDTO::fromArray(PrivacyFixtures::company()));
    $resources = app(ResourceCrudService::class);
    $language = $resources->create('language', ['code' => 'zh-cn', 'name' => 'Chinese', 'native_name' => '中文']);
    $market = $resources->create('market', [
        'code' => 'APAC',
        'name' => 'Asia-Pacific',
        'currency' => 'CNY',
        'default_locale' => 'zh-cn',
        'language_ids' => [$language['id']],
    ]);

    expect($language['code'])->toBe('zh-CN')
        ->and($market['code'])->toBe('apac')
        ->and($market['default_locale'])->toBe('zh-CN');

    $this->post(route('admin.languages.toggle', $language['id']))->assertSessionHas('success');
    expect(Language::findOrFail($language['id'])->is_active)->toBeFalse();
    $this->post(route('admin.languages.toggle', $language['id']))->assertSessionHas('success');
    expect(Language::findOrFail($language['id'])->is_active)->toBeTrue();
});

test('resource deletion cannot cascade into content or remove a referenced locale', function (): void {
    $language = Language::create(['code' => 'fr', 'name' => 'Français', 'native_name' => 'Français', 'direction' => 'ltr', 'is_default' => false, 'is_active' => true]);
    $market = Market::create(['code' => 'fr', 'name' => 'France', 'currency' => 'EUR', 'default_locale' => 'fr', 'is_default' => false, 'is_active' => true]);
    $this->delete(route('admin.languages.destroy', $language))->assertSessionHas('error');
    $menu = Menu::create(['slug' => 'used-market', 'name' => 'Used', 'location' => 'header']);
    $menu->markets()->attach($market->getKey());
    foreach (['language' => $language, 'market' => $market] as $key => $model) {
        $this->delete(route($key === 'market' ? 'admin.markets.destroy' : 'admin.languages.destroy', $model))->assertSessionHas('error');
        mcpTestBindIdentity($this->admin);
        $actor = app(ActorContextFactory::class)->fromRequest(request(), 'mcp');
        expect(app(CapabilityExecutor::class)->execute('cms_resource_delete', ['resource' => $key, 'id' => $model->getKey()], $actor, approved: true))->toHaveKey('validation');
        try {
            app(ResourceCrudService::class)->delete($key, $model->getKey());
            $this->fail('A referenced resource must remain.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->not->toBeEmpty();
        }
        expect($model->fresh())->not->toBeNull();
    }
    expect($menu->fresh())->not->toBeNull();
});

test('web and resources reject self deletion and user creation without a password', function (): void {
    $this->delete(route('admin.users.destroy', $this->admin))->assertSessionHasErrors('error');
    $this->post(route('admin.users.store'), ['name' => 'Web no password', 'email' => 'web-no-password@example.test', 'role_id' => Role::where('slug', 'admin')->firstOrFail()->getKey()])->assertSessionHasErrors('password');
    expect(User::where('email', 'web-no-password@example.test')->exists())->toBeFalse();
    try {
        app(ResourceCrudService::class)->delete('user', $this->admin->getKey());
        $this->fail('Self deletion must fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('error');
    }
    try {
        app(ResourceCrudService::class)->create('user', ['name' => 'No password', 'email' => 'no-password@example.test', 'role_id' => Role::where('slug', 'admin')->firstOrFail()->getKey()]);
        $this->fail('A password is required.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('password');
    }
    expect(User::where('email', 'no-password@example.test')->exists())->toBeFalse();
    $created = app(ResourceCrudService::class)->create('user', ['name' => 'Valid', 'email' => 'valid@example.test', 'password' => 'LongPassword123!', 'role_id' => Role::where('slug', 'admin')->firstOrFail()->getKey()]);
    $user = User::findOrFail($created['id']);
    expect(Hash::check('LongPassword123!', $user->password))->toBeTrue();
    expect($user->hasRole('admin'))->toBeTrue();
    expect($created)->not->toHaveKey('password');
});

test('menu resource creation persists translations and requires the default locale title', function (): void {
    $menu = Menu::create(['slug' => 'main', 'name' => 'Main', 'location' => 'header']);
    $attributes = ['menu_id' => $menu->getKey(), 'type' => 'custom', 'url' => '/about'];
    try {
        app(ResourceCrudService::class)->create('menu_item', $attributes);
        $this->fail('The default locale title is required.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('translations.es.title');
    }
    expect(MenuItem::where('menu_id', $menu->getKey())->exists())->toBeFalse();
    $created = app(ResourceCrudService::class)->create('menu_item', $attributes + ['translations' => ['es' => ['title' => 'Acerca de']]]);
    expect(MenuItem::findOrFail($created['id'])->translations->first()->title)->toBe('Acerca de');
    $this->post(route('admin.menus.store-item', $menu), ['type' => 'custom', 'url' => '/contact', 'translations' => ['es' => ['title' => 'Contacto']]])->assertSessionHas('success');
    expect(MenuItem::where('url', '/contact')->firstOrFail()->translations->first()->title)->toBe('Contacto');
});
