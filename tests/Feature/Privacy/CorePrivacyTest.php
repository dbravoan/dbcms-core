<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\DTOs\Auth\RegisterUserDTO;
use DbCms\Core\DTOs\Install\LegalCompanyDTO;
use DbCms\Core\Models\CapabilityAuditLog;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Auth\UserAuthService;
use DbCms\Core\Services\Capabilities\CapabilityAuditLogger;
use DbCms\Core\Services\Cms\ModuleNotificationService;
use DbCms\Core\Services\Privacy\AccountPrivacyService;
use DbCms\Core\Services\Privacy\CorePrivacyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\PrivacyFixtures;

it('requires a finite core policy and a separate declaration before public registration', function (): void {
    $company = PrivacyFixtures::company();
    unset($company['processing'][0]['retention_days']);
    expect(fn () => LegalCompanyDTO::fromArray($company))->toThrow(ValidationException::class);
    $dto = new RegisterUserDTO('Requested account', 'register@example.test', 'Password-123');
    expect(fn () => app(UserAuthService::class)->registerUser($dto))->toThrow(ValidationException::class);
    expect(User::query()->where('email', $dto->email)->exists())->toBeFalse();
    $accounts = PrivacyFixtures::processingFor([])[0];
    $accounts['owner'] = 'accounts';
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...PrivacyFixtures::processingFor([]), $accounts], 'legal', 'json');
    expect(fn () => app(UserAuthService::class)->registerUser($dto))->toThrow(ValidationException::class);
    $accounts['manual_review'] = true;
    unset($accounts['retention_days']);
    $accounts['retention'] = 'Until the account relationship ends, subject to reviewed restrictions';
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...PrivacyFixtures::processingFor([]), $accounts], 'legal', 'json');
    expect(app(UserAuthService::class)->registerUser($dto)->email)->toBe($dto->email);
});

it('records security outcomes without copying submitted personal data or network identity', function (): void {
    $user = User::factory()->create();
    $record = app(CapabilityAuditLogger::class)->log(['user' => $user, 'tool' => 'cms.forms.submit', 'args' => ['email' => 'private@example.test', 'message' => 'Private health details'], 'error' => 'Failure for private@example.test', 'ip' => '192.0.2.1']);
    expect($record->user_id)->toBe($user->id)->and($record->args)->toBe([])->and($record->ip)->toBeNull()->and($record->error)->not->toContain('private@example.test');
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [], 'legal', 'json');
    expect(fn () => app(CapabilityAuditLogger::class)->log(['tool' => 'cms.forms.submit']))->toThrow(ValidationException::class);
    expect(fn () => app(ModuleNotificationService::class)->notify('core', 'info', 'Title', 'Message', sendEmail: false))->toThrow(ValidationException::class);
});

it('prunes expired core records and native expired credentials while preserving current and sending records', function (): void {
    DB::table('sessions')->insert([
        ['id' => 'expired-session', 'payload' => '', 'last_activity' => now()->subMinutes((int) config('session.lifetime') + 1)->timestamp],
        ['id' => 'current-session', 'payload' => '', 'last_activity' => now()->timestamp],
    ]);
    DB::table('password_reset_tokens')->insert(['email' => 'expired@example.test', 'token' => 'secret', 'created_at' => now()->subDay()]);
    $old = CapabilityAuditLog::query()->forceCreate(['tool' => 'old', 'created_at' => now()->subDays(31)]);
    $current = CapabilityAuditLog::query()->create(['tool' => 'current']);
    $notification = app(ModuleNotificationService::class)->notify('core', 'info', 'Expired', 'Operational message', sendEmail: false);
    $notification->forceFill(['created_at' => now()->subDays(31)])->save();
    $sending = app(ModuleNotificationService::class)->notify('core', 'info', 'Sending', 'Operational message', sendEmail: false);
    $sending->forceFill(['created_at' => now()->subDays(31)])->save();
    DB::table('notification_email_deliveries')->insert(['notification_id' => $sending->id, 'status' => 'sending', 'created_at' => now()->subDays(31)]);
    $client = (string) Str::uuid();
    DB::table('oauth_access_tokens')->insert([
        ['id' => 'expired-token', 'client_id' => $client, 'revoked' => false, 'expires_at' => now()->subDay()],
        ['id' => 'valid-token', 'client_id' => $client, 'revoked' => false, 'expires_at' => now()->addDay()],
    ]);
    $original = storage_path();
    $temporary = sys_get_temp_dir().'/dbcms-privacy-prune-'.Str::uuid();
    mkdir($temporary, 0700, true);
    app()->useStoragePath($temporary);
    try {
        $result = app(CorePrivacyService::class)->prune();
        $this->artisan('privacy:prune')->assertExitCode(1);
    } finally {
        app()->useStoragePath($original);
        File::deleteDirectory($temporary);
    }
    expect($result['deliveries_requiring_review'])->toBe(1);
    $this->assertDatabaseMissing('sessions', ['id' => 'expired-session']);
    $this->assertDatabaseHas('sessions', ['id' => 'current-session']);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'expired@example.test']);
    $this->assertDatabaseMissing('capability_audit_logs', ['id' => $old->id]);
    $this->assertDatabaseHas('capability_audit_logs', ['id' => $current->id]);
    $this->assertDatabaseMissing('system_notifications', ['id' => $notification->id]);
    $this->assertDatabaseHas('system_notifications', ['id' => $sending->id]);
    $this->assertDatabaseMissing('oauth_access_tokens', ['id' => 'expired-token']);
    $this->assertDatabaseHas('oauth_access_tokens', ['id' => 'valid-token']);
});

it('protects the last human administrator and erases core copies transactionally after export', function (): void {
    $role = Role::query()->create(['slug' => 'admin', 'name' => 'Admin']);
    $user = User::factory()->create(['email' => 'erase@example.test']);
    $user->load('roles'); // A previously loaded empty relation must not bypass the current-role guard.
    $user->roles()->attach($role);
    $service = User::factory()->create();
    $service->forceFill(['is_service_account' => true])->save();
    $service->roles()->attach($role);
    expect(fn () => $user->delete())->toThrow(ValidationException::class);
    expect($user->fresh())->not->toBeNull();
    $another = User::factory()->create();
    $another->roles()->attach($role);
    DB::table('sessions')->insert(['id' => 'session-secret', 'user_id' => $user->id, 'payload' => 'private-session-payload', 'last_activity' => now()->timestamp]);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset-secret', 'created_at' => now()]);
    $log = app(CapabilityAuditLogger::class)->log(['user' => $user, 'tool' => 'cms.read']);
    $notice = app(ModuleNotificationService::class)->notify('core', 'info', 'Private', 'Contact details', sendEmail: false, userId: $user->id);
    $privacy = app(AccountPrivacyService::class);
    $json = json_encode($privacy->export($user->id), JSON_THROW_ON_ERROR);
    expect($json)->toContain('erase@example.test')->not->toContain('session-secret')->not->toContain('private-session-payload')->not->toContain('reset-secret')->not->toContain($user->password);
    $privacy->erase($user->id);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseMissing('system_notifications', ['id' => $notice->id]);
    expect($log->fresh()->user_id)->toBeNull();
});

it('expires only standard dated logs in an ordinary disposable log directory', function (): void {
    $original = storage_path();
    $temporary = sys_get_temp_dir().'/dbcms-privacy-logs-'.Str::uuid();
    mkdir($temporary.'/logs', 0700, true);
    $oldName = 'laravel-'.now()->subDays(31)->format('Y-m-d').'.log';
    $newName = 'laravel-'.now()->format('Y-m-d').'.log';
    file_put_contents($temporary.'/logs/'.$oldName, 'old personal log details');
    file_put_contents($temporary.'/logs/'.$newName, 'current log');
    file_put_contents($temporary.'/logs/custom-2000-01-01.log', 'project owned');
    file_put_contents($temporary.'/logs/laravel-2025-02-31.log', 'invalid date');
    file_put_contents($temporary.'/outside.log', 'external target');
    symlink($temporary.'/outside.log', $temporary.'/logs/laravel-2000-01-01.log');
    app()->useStoragePath($temporary);
    try {
        expect(app(CorePrivacyService::class)->prune()['local_daily_logs'])->toBe(1);
        expect(file_exists($temporary.'/logs/'.$oldName))->toBeFalse();
        expect(file_exists($temporary.'/logs/'.$newName))->toBeTrue();
        expect(file_exists($temporary.'/logs/custom-2000-01-01.log'))->toBeTrue();
        expect(file_exists($temporary.'/logs/laravel-2025-02-31.log'))->toBeTrue();
        expect(file_get_contents($temporary.'/outside.log'))->toBe('external target');
    } finally {
        app()->useStoragePath($original);
        File::deleteDirectory($temporary);
    }
});
