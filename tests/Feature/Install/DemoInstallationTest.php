<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\InstallationAdminServiceInterface;
use DbCms\Core\Contracts\Services\InstallationProvisioningServiceInterface;
use DbCms\Core\DTOs\Install\InstallationAdminDTO;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Permission;
use DbCms\Core\Models\Role;
use Tests\Support\DemoSiteSeeder;
use Tests\Support\PrivacyFixtures;

test('demo installation provisions content, roles and legal translations', function (): void {
    config(['cms.demo_seeder' => DemoSiteSeeder::class]);
    $result = app(InstallationProvisioningServiceInterface::class)->provision(true);

    expect($result->success)->toBeTrue($result->message);
    expect(Page::query()->whereHas('translations', fn ($query) => $query->where('slug', 'fixture-demonstration'))->exists())->toBeTrue();

    expect(Role::query()->whereIn('slug', ['admin', 'editor', 'author', 'marketing', 'viewer'])->count())->toBe(5)
        ->and(Permission::query()->where('slug', 'access:admin')->exists())->toBeTrue()
        ->and(Permission::query()->where('slug', 'use:ai')->exists())->toBeTrue();

    app(InstallationAdminServiceInterface::class)->finalize(new InstallationAdminDTO(
        name: 'Demo Installer Admin',
        email: 'demo-installer@db-cms.io',
        password: 'secret12345',
        siteName: 'Demo installation',
        adminPath: 'admin',
        brand: 'dbcms',
        frontTheme: 'fixture-theme',
        company: LegalCompanyDTO::fromArray(PrivacyFixtures::company()),
    ));

    $legal = Page::query()->where('template', 'blade:legal.privacy')->firstOrFail();
    foreach (Language::query()->where('is_active', true)->pluck('code') as $locale) {
        expect($legal->translations()->where('locale', $locale)->exists())->toBeTrue("Missing legal translation for {$locale}");
    }

    expect(Role::query()->where('slug', 'admin')->firstOrFail()->permissions()->where('slug', 'access:admin')->exists())->toBeTrue();
    $contact = Page::query()->whereHas('translations', static fn ($query) => $query->where('locale', 'es')->where('slug', 'contacto'))->sole();
    expect((int) setting('forms.contact_page_id'))->toBe($contact->id)
        ->and(CustomForm::query()->where('slug', 'contact')->count())->toBe(1)
        ->and(CustomForm::query()->where('slug', 'contacto')->exists())->toBeFalse();
});
