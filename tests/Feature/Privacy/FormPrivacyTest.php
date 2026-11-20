<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Forms\Services\FormDomainService;
use DbCms\Core\Forms\Services\FormPrivacyService;
use DbCms\Core\Models\Setting;
use DbCms\Core\Services\Privacy\LegalPageService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Validation\ValidationException;
use Tests\Support\FormsComposition;

uses(FormsComposition::class);

it('requires configured retention before collection and minimizes network metadata', function (): void {
    $form = CustomForm::query()->create(['slug' => 'privacy-contact', 'is_active' => true]);
    FormField::query()->create(['custom_form_id' => $form->id, 'field_key' => 'email', 'type' => 'email', 'is_required' => true, 'order' => 1]);
    $submission = app(FormDomainService::class)->submitForm($form, ['email' => 'person@example.test']);
    expect($submission->ip_address)->toBeNull()->and($submission->user_agent)->toBeNull();
    app(GeneralSettingsServiceInterface::class)->set('legal.processing', [], 'legal', 'json');
    expect(fn () => app(FormDomainService::class)->submitForm($form, ['email' => 'person@example.test']))->toThrow(ValidationException::class);
    expect(FormSubmission::query()->count())->toBe(1);
});

it('retains fresh submissions and allows rights operations on inactive form data', function (): void {
    $form = CustomForm::query()->create(['slug' => 'privacy-contact', 'is_active' => false]);
    $old = FormSubmission::query()->forceCreate(['custom_form_id' => $form->id, 'payload' => ['email' => 'old@example.test'], 'created_at' => now()->subDays(31)]);
    $recent = FormSubmission::query()->create(['custom_form_id' => $form->id, 'payload' => ['email' => 'recent@example.test']]);
    $privacy = app(FormPrivacyService::class);
    expect($privacy->prune())->toBe(1)->and(FormSubmission::query()->find($old->id))->toBeNull();
    expect($privacy->export([$recent->id])[0]['payload'])->toBe(['email' => 'recent@example.test']);
    expect($privacy->erase([$recent->id]))->toBe(1);
});

it('reloads the persisted processing policy at the start of a worker job', function (): void {
    $legal = app(LegalPageService::class);
    expect($legal->canCollect('forms'))->toBeTrue();
    Setting::setValue('legal.processing', [], 'legal', 'json');

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([]);
    event(new JobProcessing('database', $job));

    expect($legal->canCollect('forms'))->toBeFalse();
});
