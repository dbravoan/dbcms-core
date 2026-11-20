<?php

declare(strict_types=1);

use DbCms\Core\Models\User;
use Illuminate\Support\Facades\File;
use Tests\Support\PrivacyFixtures;

test('cli command cms:install executes unattended installation with options', function (): void {
    $company = app()->environmentPath().'/company.json';
    $companyData = PrivacyFixtures::company();
    $companyData['company_name'] = 'Titular CLI Español';
    File::put($company, json_encode($companyData, JSON_THROW_ON_ERROR));
    $this->withPendingProjectReadme(function (string $path) use ($company): void {
        $exitCode = $this->artisan('cms:install', [
            '--company-file' => $company,
            '--db-host' => env('DB_HOST', 'mysql'),
            '--db-port' => (string) env('DB_PORT', '3306'),
            '--db-database' => env('DB_DATABASE'),
            '--db-username' => env('DB_USERNAME', 'sail'),
            '--db-password' => env('DB_PASSWORD', 'secret'),
            '--without-demo' => true,
            '--admin-name' => 'CLI Sysadmin',
            '--admin-email' => 'cli-admin@db-cms.io',
            '--admin-password' => 'secret12345',
            '--admin-path' => 'control',
            '--site-name' => 'dbCMS CLI Platform',
            '--force' => true,
        ])->run();

        expect($exitCode)->toBe(0)
            ->and(File::get($path))->toContain('dbCMS CLI Platform', 'Titular CLI Español', 'Texto del proyecto.')
            ->not->toContain('cli-admin@db-cms.io', 'secret12345');
    });
    expect(User::where('email', 'cli-admin@db-cms.io')->exists())->toBeTrue();
    expect(File::exists(storage_path('installed')))->toBeTrue();

    $lockContent = json_decode((string) File::get(storage_path('installed')), true);
    expect($lockContent['admin_email'])->toBe('cli-admin@db-cms.io');
    expect($lockContent['admin_path'])->toBe('control');
});

test('cli command cms:install aborts if already installed without --force', function (): void {
    File::put(storage_path('installed'), json_encode(['installed' => true]));

    $exitCode = $this->artisan('cms:install')->run();

    expect($exitCode)->toBe(1);
});
