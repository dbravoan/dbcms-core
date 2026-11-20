<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Forms\Jobs\DeliverFormAction;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormAttachment;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Forms\Models\FormSubmissionAction;
use DbCms\Core\Forms\Services\FormActionService;
use DbCms\Core\Forms\Services\FormDefinitionService;
use DbCms\Core\Forms\Services\FormDomainService;
use DbCms\Core\Forms\Services\FormPrivacyService;
use DbCms\Core\Forms\Services\FormWebhookPolicy;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Cms\CookieConsentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\CmsFixtures;
use Tests\Support\FormsComposition;
use Tests\Support\PrivacyFixtures;

uses(FormsComposition::class);

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('local');
    CmsFixtures::localization(['es']);
    $this->form = CustomForm::create(['slug' => 'composable', 'is_active' => true, 'notification_email' => 'inbox@example.test']);
    $this->form->translations()->create(['locale' => 'es', 'name' => 'Solicitud', 'success_message' => 'Recibido']);
    foreach (['date' => 'day', 'time' => 'hour', 'url' => 'website', 'hidden' => 'source', 'file' => 'document'] as $type => $key) {
        $this->form->fields()->create(['field_key' => $key, 'type' => $type, 'is_required' => true]);
    }
    $this->input = ['day' => '2026-11-13', 'hour' => '09:45', 'website' => 'https://example.test/page', 'source' => 'campaign', 'document' => UploadedFile::fake()->createWithContent('request.txt', 'Private request')];
});

function composableLayout(): array
{
    return ['steps' => [
        ['key' => 'contact', 'labels' => ['es' => 'Contacto'], 'groups' => [
            ['key' => 'details', 'labels' => ['es' => 'Datos'], 'columns' => ['mobile' => 1, 'tablet' => 2, 'desktop' => 3], 'fields' => ['day', 'hour', 'website']],
        ]],
        ['key' => 'request', 'labels' => ['es' => 'Solicitud'], 'groups' => [
            ['key' => 'files', 'columns' => ['mobile' => 1, 'tablet' => 1, 'desktop' => 2], 'fields' => ['source', 'document']],
        ]],
    ]];
}

function configureFormReceiver(string $url = 'https://93.184.216.34/lead', ?string $purpose = null): void
{
    config(['forms_delivery.webhooks.support' => ['url' => $url, 'token' => 'private-server-token', 'fields' => ['source'], 'purpose' => $purpose]]);
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [...PrivacyFixtures::processingFor(['forms']), [...PrivacyFixtures::processingFor(['forms'])[0], 'owner' => 'forms_webhook_support']], 'legal', 'json');
}

it('validates all new primitives and stores uploaded bytes privately with an owned reference', function (): void {
    $response = $this->postJson('/forms/composable/submit', $this->input);
    $response->assertOk()->assertJsonPath('message', 'Recibido');
    $submission = FormSubmission::firstOrFail();
    $attachment = $submission->attachments()->firstOrFail();
    expect($submission->payload['day'])->toBe('2026-11-13')
        ->and($submission->payload['hour'])->toBe('09:45')
        ->and($submission->payload['document'])->toEqual(['attachment_id' => $attachment->id, 'filename' => 'request.txt'])
        ->and($attachment->bytes())->toBe('Private request')
        ->and($attachment->toArray())->not->toHaveKey('path');
    Storage::disk('local')->assertExists($attachment->path);
    $this->get(route('admin.forms.attachments.show', $attachment))->assertRedirect();
    CmsFixtures::identities(['admin' => [], 'viewer' => ['access:admin']]);
    $viewer = User::where('email', 'viewer@db-cms.io')->firstOrFail();
    $this->actingAs($viewer)->get(route('admin.forms.attachments.show', $attachment))->assertForbidden();
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->actingAs($admin)->get(route('admin.forms.attachments.show', $attachment))->assertOk()->assertDownload('request.txt');
});

it('retains saved submission actions when the caller supplies a partially selected form', function (): void {
    $this->form->update(['actions' => [['id' => 'thanks', 'type' => 'confirm']]]);
    $partial = CustomForm::query()->select('id', 'slug', 'is_active')->findOrFail($this->form->id);
    $submission = app(FormDomainService::class)->submitForm($partial, $this->input);
    expect($submission->actions()->where('action_key', 'thanks')->sole()->definition['type'])->toBe('confirm')
        ->and($partial->notification_email)->toBe('inbox@example.test');
});

it('rejects invalid date, time, URL and file values without partial storage', function (): void {
    $this->postJson('/forms/composable/submit', ['day' => '2026-02-31', 'hour' => '25:99', 'website' => 'javascript:alert(1)', 'source' => 'test', 'document' => 'not-an-upload'])
        ->assertUnprocessable()->assertJsonValidationErrors(['day', 'hour', 'website', 'document']);
    expect(FormSubmission::count())->toBe(0)->and(FormAttachment::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('writes canonical fields, layout and actions through the shared mutation and restores snapshots', function (): void {
    $layout = composableLayout();
    $actions = [['id' => 'thanks', 'type' => 'open_popup', 'target' => 'thanks']];
    app(EntityMutationService::class)->update('form', $this->form->id, ['attributes' => ['layout' => $layout, 'actions' => $actions]]);
    $this->form->refresh();
    expect($this->form->layout)->toEqual($layout)->and($this->form->actions)->toEqual($actions);
    $snapshots = app(ContentSnapshotService::class);
    $snapshot = $snapshots->capture($this->form);
    $this->form->update(['layout' => null, 'actions' => []]);
    $snapshots->restore($this->form, $snapshot);
    expect($this->form->fresh()->layout)->toEqual($layout)->and($this->form->fresh()->actions)->toEqual($actions);
    $bad = $layout;
    $bad['steps'][1]['groups'][0]['fields'][] = 'day';
    expect(fn () => app(EntityMutationService::class)->update('form', $this->form->id, ['attributes' => ['layout' => $bad]]))->toThrow(ValidationException::class);
    expect($this->form->fresh()->layout)->toEqual($layout);
});

it('renders steps and retains final validation across all steps', function (): void {
    $this->form->update(['layout' => composableLayout()]);
    $html = view('forms::components.form-engine', ['slug' => 'composable', 'locale' => 'es'])->render();
    expect($html)->toContain('data-cms-forms', 'data-form-step="0"', 'data-form-step="1"', 'enctype="multipart/form-data"', '--form-desktop:3', 'name="_token"', 'name="_hp_check"');
    unset($this->input['day']);
    $this->postJson('/forms/composable/submit', $this->input)->assertUnprocessable()->assertJsonValidationErrors('day');
    expect(FormSubmission::count())->toBe(0);
});

it('rejects unreviewed receivers, unsafe redirects, duplicate and missing layout references', function (): void {
    foreach (['//attacker.example/path', '/\\attacker.example', 'https://attacker.example', '/%2f%2fattacker.example', "/bad\npath"] as $target) {
        expect(fn () => app(FormDefinitionService::class)->validate(['actions' => [['id' => 'redirect', 'type' => 'redirect', 'target' => $target]]], [], $this->form))->toThrow(ValidationException::class);
    }
    expect(fn () => app(FormDefinitionService::class)->validate(['actions' => [['id' => 'receiver', 'type' => 'webhook', 'target' => 'unknown']]], [], $this->form))->toThrow(ValidationException::class);
    $layout = composableLayout();
    $layout['steps'][1]['groups'][0]['fields'] = ['unknown'];
    expect(fn () => app(FormDefinitionService::class)->validate(['layout' => $layout], ['day', 'hour', 'website', 'source', 'document'], $this->form))->toThrow(ValidationException::class);
});

it('records effects transactionally, delivers once, and returns browser actions only after success', function (): void {
    $this->form->update(['actions' => [['id' => 'email', 'type' => 'email'], ['id' => 'thanks', 'type' => 'open_popup', 'target' => 'thanks']]]);
    Mail::fake();
    $submission = app(FormDomainService::class)->submitForm($this->form, $this->input);
    Queue::assertPushed(DeliverFormAction::class, 2);
    Mail::assertNothingSent();
    $email = $submission->actions()->where('action_key', 'email')->firstOrFail();
    expect($email->status)->toBe('pending')->and($email->attempts)->toBe(0)
        ->and(app(FormActionService::class)->browserActions($submission))->toBe([['type' => 'open_popup', 'target' => 'thanks']]);
    app(FormActionService::class)->deliver($email->id);
    app(FormActionService::class)->deliver($email->id);
    expect($email->fresh()->status)->toBe('succeeded')->and($email->fresh()->attempts)->toBe(1);
});

it('rolls back submission action records and uploaded bytes together on failure', function (): void {
    Event::listen('eloquent.creating: '.FormSubmissionAction::class, static fn () => throw new RuntimeException('Rejected action'));
    expect(fn () => app(FormDomainService::class)->submitForm($this->form, $this->input))->toThrow(RuntimeException::class, 'Rejected action');
    expect(FormSubmission::count())->toBe(0)->and(FormAttachment::count())->toBe(0)
        ->and(FormSubmissionAction::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

it('pins reviewed public webhook receivers, minimizes payload and bounds retries without leaking secrets', function (): void {
    configureFormReceiver();
    $this->form->update(['actions' => [['id' => 'receiver', 'type' => 'webhook', 'target' => 'support']]]);
    $submission = app(FormDomainService::class)->submitForm($this->form, $this->input);
    $action = $submission->actions()->where('action_key', 'receiver')->firstOrFail();
    Http::fake(['*' => Http::response([], 503)]);
    for ($i = 0; $i < 3; $i++) {
        expect(fn () => app(FormActionService::class)->deliver($action->id))->toThrow(RuntimeException::class, 'Form action delivery failed.');
    }
    app(FormActionService::class)->deliver($action->id);
    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request->data() === ['fields' => ['source' => 'campaign']] && $request->hasHeader('Idempotency-Key', 'form-action-'.$action->id));
    expect($action->fresh()->status)->toBe('failed')->and($action->fresh()->attempts)->toBe(3)
        ->and(json_encode($action->fresh()->toArray()))->not->toContain('private-server-token');
});

it('blocks private-network webhooks and rechecks changed policy and withdrawn optional consent', function (): void {
    configureFormReceiver('https://127.0.0.1/lead');
    $definition = ['target' => 'support', 'configuration_revision' => app(FormWebhookPolicy::class)->revision('support')];
    Http::fake();
    expect(fn () => app(FormWebhookPolicy::class)->send($definition, ['source' => 'test'], 'private'))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
    configureFormReceiver('https://93.184.216.34/lead', 'marketing');
    $this->form->update(['actions' => [['id' => 'receiver', 'type' => 'webhook', 'target' => 'support']]]);
    $receipt = app(CookieConsentService::class)->record(['marketing' => true]);
    $definition = ['target' => 'support', 'configuration_revision' => app(FormWebhookPolicy::class)->revision('support'), 'consent_receipt_id' => $receipt['id']];
    DB::table('privacy_consents')->where('id', $receipt['id'])->update(['revoked_at' => now()]);
    expect(app(FormWebhookPolicy::class)->send($definition, ['source' => 'test'], 'withdrawn'))->toBeFalse();
    Http::assertNothingSent();
    config(['forms_delivery.webhooks.support.url' => 'https://93.184.216.34/changed']);
    expect(app(FormWebhookPolicy::class)->send($definition, ['source' => 'test'], 'changed'))->toBeFalse();
    Http::assertNothingSent();
});

it('exports attachment bytes and owns retained inactive data cleanup including action records', function (): void {
    $submission = app(FormDomainService::class)->submitForm($this->form, $this->input);
    $attachment = $submission->attachments()->firstOrFail();
    $this->form->update(['is_active' => false]);
    $export = app(FormPrivacyService::class)->export([$submission->id]);
    expect(base64_decode($export[0]['attachments'][0]['content_base64']))->toBe('Private request');
    $submission->forceFill(['created_at' => now()->subDays(31)])->save();
    expect(app(FormPrivacyService::class)->prune())->toBe(1)->and(FormAttachment::count())->toBe(0)->and(FormSubmissionAction::count())->toBe(0);
    Storage::disk('local')->assertMissing($attachment->path);
    expect($export[0]['attachments'][0])->not->toHaveKey('path');
});

it('shares the browser editor writer and requires forms permissions', function (): void {
    CmsFixtures::localization(['es']);
    CmsFixtures::identities(['admin' => [], 'viewer' => ['access:admin']]);
    $viewer = User::where('email', 'viewer@db-cms.io')->firstOrFail();
    $this->actingAs($viewer)->put(route('admin.forms.update', $this->form), ['layout' => composableLayout()])->assertForbidden();
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->actingAs($admin)->get(route('admin.forms.edit', $this->form))->assertOk()->assertSee('Pasos y disposición')->assertSee('Después del envío');
    $this->actingAs($admin)->put(route('admin.forms.update', $this->form), [
        'translations' => ['es' => ['name' => 'Solicitud']], 'layout' => json_encode(composableLayout()),
        'actions' => json_encode([['id' => 'thanks', 'type' => 'redirect', 'target' => '/gracias']]),
        'is_active' => true,
    ])->assertRedirect();
    expect($this->form->fresh()->layout)->toEqual(composableLayout())
        ->and($this->form->fresh()->actions[0]['target'])->toBe('/gracias');
});

it('protects a submission while delivery is in progress and skips work after disabling forms', function (): void {
    $submission = app(FormDomainService::class)->submitForm($this->form, $this->input);
    $action = $submission->actions()->firstOrFail();
    $action->update(['status' => 'processing']);
    expect(fn () => app(FormPrivacyService::class)->erase([$submission->id]))->toThrow(ValidationException::class);
    expect($submission->fresh())->not->toBeNull();
    $action->update(['status' => 'pending']);
    $this->form->update(['is_active' => false]);
    app(FormActionService::class)->deliver($action->id);
    expect($action->fresh()->status)->toBe('skipped');
    expect(fn () => app(FormDomainService::class)->submitForm($this->form, $this->input))->toThrow(ValidationException::class);
});
