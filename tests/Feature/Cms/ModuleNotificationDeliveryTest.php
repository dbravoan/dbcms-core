<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\ModuleNotificationServiceInterface;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Services\FormActionService;
use DbCms\Core\Jobs\SendModuleNotificationEmailJob;
use DbCms\Core\Mail\ModuleNotificationMail;
use DbCms\Core\Models\NotificationEmailDelivery;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\SystemNotification;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Cms\AdminThemeService;
use DbCms\Core\Services\Cms\NotificationRecipientResolverService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FormsComposition;

uses(FormsComposition::class);

beforeEach(function (): void {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => 'mail.test', 'port' => 2525]]);
    Mail::fake();
    $this->recipient = User::factory()->create();
    $this->recipient->assignRole(Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']));
    $this->notifier = app(ModuleNotificationServiceInterface::class);
});

test('module notification dispatches a mailable to its target when SMTP is configured', function (): void {
    $notification = $this->notifier->notify('forms', 'lead', 'A new lead', 'Contact received', userId: $this->recipient->id);
    Mail::assertSent(ModuleNotificationMail::class, fn ($mail) => $mail->hasTo($this->recipient->email));
    expect(NotificationEmailDelivery::where('notification_id', $notification->id)->value('status'))->toBe('sent');
});

test('core form notifications keep the initial inbox private and email requires configuration', function (): void {
    $form = CustomForm::query()->create(['slug' => 'contact', 'is_active' => true, 'notification_email' => null]);
    $actions = app(FormActionService::class);
    $submission = $form->submissions()->create(['payload' => ['message' => 'Contact request'], 'locale' => 'es']);
    $actions->record($form, $submission, []);
    $actions->deliver($submission->actions()->where('action_key', '_notification')->firstOrFail()->id);
    expect(SystemNotification::where('module', 'forms')->count())->toBe(1)
        ->and(NotificationEmailDelivery::count())->toBe(0);
    Mail::assertNothingSent();

    $form->update(['notification_email' => 'inbox@example.test']);
    $next = $form->submissions()->create(['payload' => ['message' => 'Configured request'], 'locale' => 'es']);
    $actions->record($form, $next, []);
    $actions->deliver($next->actions()->where('action_key', '_notification')->firstOrFail()->id);
    expect(NotificationEmailDelivery::count())->toBe(1);
    Mail::assertSent(ModuleNotificationMail::class);
});

test('private deleted recipient never falls back to an administrator', function (): void {
    expect(fn () => $this->notifier->notify('forms', 'lead', 'Private lead', 'Private', userId: 999999))->toThrow(ModelNotFoundException::class);
    Mail::assertNothingSent();
    expect(NotificationEmailDelivery::count())->toBe(0);
});

test('deleting a notification owner does not turn their private inbox into global content', function (): void {
    $private = $this->notifier->notify('forms', 'lead', 'Private lead', 'Private', sendEmail: false, userId: $this->recipient->id);
    $other = User::factory()->create();
    $other->assignRole('admin');
    $this->recipient->delete();
    $this->actingAs($other);
    expect($private->fresh())->toBeNull()->and($this->notifier->getUnreadCount())->toBe(0);
    expect(app(NotificationRecipientResolverService::class)->recipients($private))->toHaveCount(0);
});

test('email opt out is respected and does not remove the inapp notification', function (): void {
    app(NotificationRecipientResolverService::class)->setPreference($this->recipient, '*', false);
    $notification = $this->notifier->notify('forms', 'lead', 'A new lead', 'Contact received', userId: $this->recipient->id);
    Mail::assertNothingSent();
    expect($notification->is_visible)->toBeTrue();
});

test('recipient permissions are checked again when queued delivery executes', function (): void {
    Bus::fake([SendModuleNotificationEmailJob::class]);
    $notification = $this->notifier->notify('forms', 'lead', 'A new lead', 'Contact received', userId: $this->recipient->id);
    $delivery = NotificationEmailDelivery::where('notification_id', $notification->id)->firstOrFail();
    $this->recipient->roles()->detach();
    (new SendModuleNotificationEmailJob($delivery->id))->handle(app(NotificationRecipientResolverService::class));
    Mail::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('skipped');
});

test('email only notification is excluded from inapp inbox', function (): void {
    $notification = $this->notifier->notify('forms', 'lead', 'A new lead', 'Contact received', userId: $this->recipient->id, inApp: false);
    $this->actingAs($this->recipient);
    expect($this->notifier->getUnreadCount())->toBe(0)->and($notification->is_visible)->toBeFalse();
    Mail::assertSent(ModuleNotificationMail::class);
});

test('rollback never sends module mail or preserves deliveries', function (): void {
    DB::beginTransaction();
    $this->notifier->notify('forms', 'lead', 'Rollback', 'Private', userId: $this->recipient->id);
    Mail::assertNothingSent();
    DB::rollBack();
    Mail::assertNothingSent();
    expect(NotificationEmailDelivery::count())->toBe(0);
});

test('SMTP error is recorded and repeated job does not repeat an uncertain send', function (): void {
    Bus::fake([SendModuleNotificationEmailJob::class]);
    $notification = $this->notifier->notify('forms', 'lead', 'Failure', 'Private', userId: $this->recipient->id);
    $delivery = NotificationEmailDelivery::where('notification_id', $notification->id)->firstOrFail();
    Mail::shouldReceive('to')->once()->with($this->recipient->email)->andThrow(new RuntimeException('SMTP unavailable'));
    $job = new SendModuleNotificationEmailJob($delivery->id);
    expect(fn () => $job->handle(app(NotificationRecipientResolverService::class)))->toThrow(RuntimeException::class, 'SMTP unavailable');
    expect($delivery->fresh()->status)->toBe('failed')->and($delivery->fresh()->failure_code)->toBe(RuntimeException::class);
    $job->handle(app(NotificationRecipientResolverService::class));
});

test('notification email preferences change only the current session user', function (): void {
    $other = User::factory()->create();
    $this->actingAs($this->recipient)->postJson('/admin/notifications/email-preference', ['enabled' => false])->assertOk()->assertJson(['email_enabled' => false]);
    expect(app(NotificationRecipientResolverService::class)->emailEnabled($this->recipient))->toBeFalse()
        ->and(app(NotificationRecipientResolverService::class)->emailEnabled($other))->toBeTrue();
    $this->postJson('/admin/notifications/email-preference', ['enabled' => true, 'user_id' => $other->id])->assertUnprocessable();
    $this->getJson('/admin/notifications')->assertOk()->assertJson(['email_enabled' => false]);
});

test('notification email preference component renders in both backoffice themes', function (string $brand): void {
    app(AdminThemeService::class)->setActiveTheme($brand);
    $this->actingAs($this->recipient)->get('/admin')->assertOk()->assertSee('notification-email-preference')->assertSee(__('mail.module_notification.email_preference'));
})->with(['dbcms', 'fixture-admin']);

test('non delivery transports never record a successful email even when mail is faked', function (string $transport): void {
    Bus::fake([SendModuleNotificationEmailJob::class]);
    $notification = $this->notifier->notify('forms', 'lead', 'Transport', 'Local', userId: $this->recipient->id);
    $delivery = NotificationEmailDelivery::where('notification_id', $notification->id)->firstOrFail();
    config(['mail.default' => 'qa_local', 'mail.mailers.qa_local' => ['transport' => $transport]]);
    expect(fn () => (new SendModuleNotificationEmailJob($delivery->id))->handle(app(NotificationRecipientResolverService::class)))->toThrow(RuntimeException::class, 'A real email transport is not configured.');
    expect($delivery->fresh()->status)->toBe('failed')->and($delivery->fresh()->sent_at)->toBeNull();
    Mail::assertNothingSent();
})->with(['array', 'log']);

test('mail transport failure after commit preserves the saved notification without claiming mail success', function (): void {
    config(['mail.default' => 'qa_log', 'mail.mailers.qa_log' => ['transport' => 'log']]);
    $notification = $this->notifier->notify('forms', 'lead', 'Committed notice', 'Received', userId: $this->recipient->id);
    expect($notification->fresh())->not->toBeNull()
        ->and(NotificationEmailDelivery::where('notification_id', $notification->id)->value('status'))->toBe('failed');
    Mail::assertNothingSent();
});
