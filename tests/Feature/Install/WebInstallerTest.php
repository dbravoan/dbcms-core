<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Database\Seeders\CmsFoundationSeeder;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Install\BrandRegistry;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\CmsFixtures;
use Tests\Support\PrivacyFixtures;

/** The package inventory is an external boundary; no vendor directory is renamed. */
function withInstallerBrandsHidden(array $excluded, Closure $callback): void
{
    $themes = app(AdminThemeServiceInterface::class);
    $available = array_diff_key($themes->getAvailableThemes(), array_flip($excluded));
    $catalogue = Mockery::mock($themes);
    $catalogue->shouldReceive('getAvailableThemes')->andReturn($available);
    app()->instance(AdminThemeServiceInterface::class, $catalogue);
    try {
        $callback();
    } finally {
        app()->instance(AdminThemeServiceInterface::class, $themes);
    }
}

beforeEach(function (): void {
    $this->physicalBrands = app(BrandRegistry::class)->brandIdentifiers();
    $this->physicalThemes = app(BrandRegistry::class)->frontThemeIdentifiers();
    CmsFixtures::localization(['es']);
    sort($this->physicalBrands);
    sort($this->physicalThemes);
    expect($this->physicalBrands)->not->toBeEmpty();
    expect($this->physicalThemes)->not->toBeEmpty();
    $this->selectedBrand = in_array('fixture-admin', $this->physicalBrands, true) ? 'fixture-admin' : $this->physicalBrands[0];
    $this->selectedTheme = in_array('fixture-theme', $this->physicalThemes, true) ? 'fixture-theme' : $this->physicalThemes[0];
    $this->singleBrand = in_array(config('cms.admin_theme'), $this->physicalBrands, true) ? config('cms.admin_theme') : $this->physicalBrands[0];
    config(['cms.admin_theme' => $this->singleBrand, 'cms.theme' => $this->selectedTheme]);
});

test('installer step 1 exposes server readiness and the database setup link', function (): void {
    config(['cms.name' => 'Laravel']);
    $response = $this->get(route('install.step1'));

    $response->assertOk()->assertViewIs('install.step1_requirements')
        ->assertSee('>_dbcms')
        ->assertSee('| dbCMS</title>', false)
        ->assertSee('¿Qué es dbCMS')
        ->assertDontSee('¿Qué es Laravel');
    $requirements = $response->viewData('requirements');
    $permissions = $response->viewData('permissions');
    expect($requirements)->toBeArray()->not->toBeEmpty();
    expect($permissions)->toBeArray()->not->toBeEmpty();
    foreach ([...array_values($requirements), ...array_values($permissions)] as $passed) {
        expect($passed)->toBeBool();
    }
    expect($response->viewData('canProceed'))->toBe(! in_array(false, [...$requirements, ...$permissions], true));
    $response->assertSee('href="'.route('install.step2').'"', false);
});

test('admin theme assets can be served before installation', function (): void {
    $this->get('/theme-assets/fixture-admin/fixture.svg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeaderMissing('Set-Cookie');
    $this->get('/theme-assets/fixture-admin/not-found.png')->assertNotFound();
});

test('installer step 2 tests database connection via ajax with auto-create support', function (): void {
    $response = $this->postJson(route('install.test-db'), [
        'host' => env('DB_HOST', 'mysql'),
        'port' => (string) env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME', 'sail'),
        'password' => env('DB_PASSWORD', 'secret'),
        'auto_create' => true,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
});

test('installer step 2 saves database credentials and redirects to step 3', function (): void {
    $response = $this->post(route('install.save-db'), [
        'host' => env('DB_HOST', 'mysql'),
        'port' => (string) env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME', 'sail'),
        'password' => env('DB_PASSWORD', 'secret'),
    ]);

    $response->assertRedirect(route('install.step3'));
    $stored = Dotenv\Dotenv::parse(File::get(app()->environmentFilePath()));
    expect($stored['DB_DATABASE'])->toBe(env('DB_DATABASE'))
        ->and(DB::connection('mysql')->getDatabaseName())->toBe(env('DB_DATABASE'));
});

test('installer step 3 runs provisioning migrations and seeds cleanly', function (): void {
    $response = $this->postJson(route('install.run-migrations'), [
        'with_demo' => false,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    $response->assertJsonStructure(['success', 'message', 'logs']);
});

test('installer offers demonstration data only when a valid project seeder is available', function (): void {
    config(['cms.demo_seeder' => null]);
    $this->get(route('install.step3'))
        ->assertOk()
        ->assertDontSee('Instalación con datos de demostración')
        ->assertSee('Instalación Limpia');

    config(['cms.demo_seeder' => stdClass::class]);
    $this->get(route('install.step3'))->assertOk()->assertDontSee('Instalación con datos de demostración');

    config(['cms.demo_seeder' => CmsFoundationSeeder::class]);
    $this->get(route('install.step3'))
        ->assertOk()
        ->assertSee('Instalación con datos de demostración')
        ->assertSee('Instalación Limpia');
});

test('installer step 4 creates primary admin, custom admin slug and seals installation lock', function (): void {
    config(['session.cookie' => 'installer-test-session']);

    $response = $this->post(route('install.store-admin'), [
        'company' => PrivacyFixtures::company(),
        'name' => 'Installer Admin',
        'email' => 'master@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'David Bravo',
        'admin_path' => 'dbadmin',
    ]);

    $response->assertRedirect(route('install.step5'));

    expect(User::where('email', 'master@db-cms.io')->exists())->toBeTrue();
    expect(File::exists(storage_path('installed')))->toBeTrue();
    $stored = Dotenv\Dotenv::parse(File::get(app()->environmentFilePath()));
    expect($stored['SESSION_COOKIE'])->toBe('installer-test-session')
        ->and($stored['APP_NAME'])->toBe('David Bravo');

    $loginUrl = url('/'.config('cms.admin_path').'/login');
    $this->get(route('install.step5'))
        ->assertOk()
        ->assertViewIs('install.step5_complete')
        ->assertViewHas('loginUrl', $loginUrl)
        ->assertSee('>_dbcms')
        ->assertSee('| dbCMS</title>', false)
        ->assertSee('¡David Bravo ha quedado instalado con éxito!')
        ->assertSee('href="'.$loginUrl.'"', false);
});

test('the site name field replaces the Laravel default and preserves the project name', function (string $configuredName, string $expectedName): void {
    config(['cms.name' => $configuredName]);

    $this->get(route('install.step4'))
        ->assertOk()
        ->assertViewHas('defaultSiteName', $expectedName);
})->with([
    'Laravel default' => ['Laravel', 'dbCMS'],
    'project name' => ['Área Bravo', 'Área Bravo'],
]);

test('installer step 4 exposes the brand and front theme selectors', function (): void {
    $response = $this->get(route('install.step4'));

    $response->assertStatus(200);
    $response->assertSee('Marca del CMS', false);
    $response->assertSee('name="brand"', false);
    $response->assertSee('name="front_theme"', false);
    $response->assertSee('name="app_url"', false);
    expect(array_column($response->viewData('installBrands'), 'identifier'))->toBe($this->physicalBrands);
    expect(array_column($response->viewData('frontThemes'), 'identifier'))->toBe($this->physicalThemes);
    foreach ([...$this->physicalBrands, ...$this->physicalThemes] as $identifier) {
        $response->assertSee('value="'.$identifier.'"', false);
    }
});

test('installer auto-selects a single brand without showing a selector', function (): void {
    withInstallerBrandsHidden(array_values(array_diff($this->physicalBrands, [$this->singleBrand])), function (): void {
        $response = $this->get(route('install.step4'));

        $response->assertStatus(200);
        // No radio selector, but the single brand is still submitted.
        $response->assertDontSee('type="radio" name="brand"', false);
        $response->assertSee('type="hidden" name="brand" value="'.$this->singleBrand.'"', false);
        $response->assertSee('Única marca', false);
    });
});

test('installer accepts submission without a brand when only one is available', function (): void {
    withInstallerBrandsHidden(array_values(array_diff($this->physicalBrands, [$this->singleBrand])), function (): void {
        $response = $this->post(route('install.store-admin'), [
            'company' => PrivacyFixtures::company(),
            'name' => 'Single Brand Admin',
            'email' => 'single-admin@db-cms.io',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
            'site_name' => 'Single Brand',
            'admin_path' => 'panel',
        ]);

        $response->assertRedirect(route('install.step5'));

        $lock = json_decode((string) File::get(storage_path('installed')), true);
        expect($lock['brand'])->toBe($this->singleBrand);
    });
});

test('installer persists the selected brand and front theme in the install lock', function (): void {
    $response = $this->post(route('install.store-admin'), [
        'company' => PrivacyFixtures::company(),
        'name' => 'Brand Admin',
        'email' => 'brand-admin@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'Corporate Platform',
        'admin_path' => 'panel',
        'brand' => $this->selectedBrand,
        'front_theme' => $this->selectedTheme,
    ]);

    $response->assertRedirect(route('install.step5'));
    $response->assertSessionHas('install.brand', $this->selectedBrand);

    $lock = json_decode((string) File::get(storage_path('installed')), true);
    expect($lock['brand'])->toBe($this->selectedBrand);
    expect($lock['front_theme'])->toBe($this->selectedTheme);
});

test('installer rejects an unknown brand', function (): void {
    $response = $this->post(route('install.store-admin'), [
        'company' => PrivacyFixtures::company(),
        'name' => 'Bad Brand',
        'email' => 'bad-brand@db-cms.io',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
        'site_name' => 'Bad Brand',
        'admin_path' => 'panel',
        'brand' => 'not-a-real-brand',
    ]);

    $response->assertSessionHasErrors('brand');
});

test('installer completion requires the installation lock even with a completion session', function (bool $justInstalled): void {
    $this->withSession(['install.just_installed' => $justInstalled])
        ->get(route('install.step5'))
        ->assertRedirect(route('install.step1'));
})->with([false, true]);

test('installer groups one final post into site administrator controller and privacy panels without invented personal data', function (): void {
    $response = $this->get(route('install.step4'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $forms = $xpath->query('//form[@id="install-form"]');

    expect($forms)->not->toBeFalse()
        ->and($forms->length)->toBe(1)
        ->and($forms->item(0)->getAttribute('method'))->toBe('POST')
        ->and($forms->item(0)->getAttribute('action'))->toBe(route('install.store-admin'));

    $fields = [
        'site' => ['site_name', 'app_url', 'admin_path', 'brand', 'front_theme'],
        'admin' => ['name', 'email', 'password', 'password_confirmation'],
        'controller' => ['company[company_name]', 'company[tax_id]', 'company[activity]', 'company[address]', 'company[country]', 'company[contact_email]', 'company[privacy_email]', 'company[commercial_activity]', 'company[registry_required]', 'company[commercial_registry]', 'company[dpo_required]', 'company[dpo_contact]'],
        'privacy' => ['company[hosting_provider]', 'company[hosting_country]', 'company[reviewed]'],
    ];
    expect($xpath->query('//*[@data-install-step]')->length)->toBe(4);
    foreach ($fields as $step => $names) {
        $panels = $xpath->query('//*[@data-install-step="'.$step.'"]');
        expect($panels->length)->toBe(1);
        $panel = $panels->item(0);
        // JavaScript enhances navigation; without it each panel remains available.
        expect($panel->hasAttribute('hidden'))->toBeFalse()
            ->and($panel->hasAttribute('x-cloak'))->toBeFalse();
        foreach ($names as $name) {
            expect($xpath->query('.//*[@name="'.$name.'"]', $panel)->length)->toBeGreaterThan(0);
        }
    }
    foreach (['name', 'email', 'password', 'password_confirmation'] as $name) {
        expect($xpath->query('//input[@name="'.$name.'"]')->item(0)->getAttribute('value'))->toBe('');
    }
    expect($xpath->query('//*[@id="install-finish" and @type="submit"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="install-next" and @type="button"]')->length)->toBe(1)
        ->and($xpath->query('//*[@id="install-previous" and @type="button"]')->length)->toBe(1);
});

test('installer accepts an individual with NIF without requiring company registry or a DPO', function (): void {
    $company = array_replace(PrivacyFixtures::company(), [
        'company_name' => 'Persona de prueba',
        'tax_id' => '12345678Z',
        'commercial_activity' => true,
        'registry_required' => false,
        'dpo_required' => false,
    ]);

    $this->post(route('install.store-admin'), [
        'company' => $company,
        'name' => 'Administrador de prueba',
        'email' => 'individual@example.test',
        'password' => 'installer-test-password',
        'password_confirmation' => 'installer-test-password',
        'site_name' => 'Sitio de prueba',
        'app_url' => 'https://example.test',
        'admin_path' => 'panel',
    ])->assertSessionHasNoErrors()->assertRedirect(route('install.step5'));

    $stored = app(LegalPageService::class)->company();
    expect($stored['tax_id'])->toBe('12345678Z')
        ->and($stored['registry_required'])->toBeFalse()
        ->and($stored['commercial_registry'])->toBe('')
        ->and($stored['dpo_required'])->toBeFalse()
        ->and($stored['dpo_contact'])->toBe('')
        ->and(User::query()->where('email', 'individual@example.test')->exists())->toBeTrue()
        ->and(File::exists(storage_path('installed')))->toBeTrue();
});

test('installer enforces only the legal details selected by the controller and preserves non-secret input on error', function (string $flag, string $field): void {
    $company = array_replace(PrivacyFixtures::company(), [$flag => true, $field => '']);

    $this->from(route('install.step4'))->post(route('install.store-admin'), [
        'company' => $company,
        'name' => 'Administrador de prueba',
        'email' => 'conditional@example.test',
        'password' => 'installer-test-password',
        'password_confirmation' => 'installer-test-password',
        'site_name' => 'Sitio de prueba',
        'admin_path' => 'panel',
    ])->assertRedirect(route('install.step4'))
        ->assertSessionHasErrors($field)
        ->assertSessionHasInput('company.company_name', $company['company_name'])
        ->assertSessionHasInput('company.'.$flag, true)
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');

    expect(User::query()->where('email', 'conditional@example.test')->exists())->toBeFalse()
        ->and(File::exists(storage_path('installed')))->toBeFalse();
})->with([
    'economic activity requires a NIF or CIF' => ['commercial_activity', 'tax_id'],
    'mandatory registry requires its details' => ['registry_required', 'commercial_registry'],
    'mandatory DPO requires a contact' => ['dpo_required', 'dpo_contact'],
]);
