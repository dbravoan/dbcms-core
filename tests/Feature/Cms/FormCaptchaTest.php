<?php

declare(strict_types=1);

use DbCms\Core\Captcha\Services\CaptchaService;
use DbCms\Core\Forms\Blocks\CustomFormBlock;
use DbCms\Core\Forms\Http\Controllers\Admin\Concerns\ValidatesFormPayload;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\CustomFormTranslation;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\Models\FormFieldTranslation;
use DbCms\Core\Forms\Models\FormSubmission;
use DbCms\Core\Forms\Services\FormSchemaService;
use DbCms\Core\Forms\Support\FieldTypeOptions;
use DbCms\Core\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\CmsFixtures;
use Tests\Support\FormsCaptchaComposition;

uses(RefreshDatabase::class, FormsCaptchaComposition::class);

function makeContactForm(bool $withCaptcha = true): CustomForm
{
    $form = CustomForm::create(['slug' => 'contacto', 'is_active' => true]);

    CustomFormTranslation::create([
        'custom_form_id' => $form->id,
        'locale' => 'es',
        'name' => 'Formulario de Contacto',
        'submit_button_text' => 'Enviar',
        'success_message' => '¡Gracias!',
    ]);

    FormField::create([
        'custom_form_id' => $form->id,
        'field_key' => 'nombre',
        'type' => 'text',
        'is_required' => true,
        'order' => 1,
    ]);

    if ($withCaptcha) {
        $captcha = FormField::create([
            'custom_form_id' => $form->id,
            'field_key' => 'captcha',
            'type' => 'captcha',
            'is_required' => true,
            'order' => 2,
        ]);

        FormFieldTranslation::create([
            'form_field_id' => $captcha->id,
            'locale' => 'es',
            'label' => 'Verificación de seguridad',
        ]);
    }

    return $form;
}

beforeEach(function (): void {
    CmsFixtures::localization(['es']);
    view()->share('errors', new ViewErrorBag);
});

test('the builder offers the core captcha type without an installable module', function (): void {
    expect(app(ModuleManager::class)->discoverModules())->not->toHaveKey('captcha');
    expect(collect(app(FieldTypeOptions::class)->list())->pluck('key')->all())->toContain('captcha');
});

test('saving a captcha field normalizes it (fixed key, always required, no rules)', function (): void {
    $form = makeContactForm(false);

    app(FormSchemaService::class)->syncFields($form, [
        [
            'field_key' => 'lo-que-sea',
            'type' => 'captcha',
            'is_required' => false,
            'validation_rules' => 'nullable|string',
            'translations' => ['es' => ['label' => 'Verificación', 'placeholder' => '', 'options' => '']],
        ],
    ]);

    $field = $form->fresh()->fields->firstOrFail();

    expect($field->field_key)->toBe('captcha')
        ->and($field->type)->toBe('captcha')
        ->and($field->is_required)->toBeTrue()
        ->and($field->validation_rules)->toBeNull();
});

test('a form cannot carry more than one captcha field', function (): void {
    $rules = (new class
    {
        use ValidatesFormPayload {
            formValidationRules as public;
        }
    })->formValidationRules();

    $payload = [
        'translations' => ['es' => ['name' => 'Contacto']],
        'fields' => [
            ['field_key' => 'a', 'type' => 'captcha'],
            ['field_key' => 'b', 'type' => 'captcha'],
        ],
    ];

    expect(Validator::make($payload, $rules)->errors()->has('fields'))->toBeTrue();

    $payload['fields'] = [['field_key' => 'a', 'type' => 'captcha'], ['field_key' => 'b', 'type' => 'text']];

    expect(Validator::make($payload, $rules)->errors()->has('fields'))->toBeFalse();
});

test('the public form renders the captcha only when the form declares the field', function (): void {
    $form = makeContactForm(true);

    $html = (new CustomFormBlock)->render(['form_slug' => 'contacto'], null, 'es');

    expect($html)->toContain('name="captcha_response"')
        ->and($html)->toContain('_captcha_token')
        ->and($html)->toContain('Verificación de seguridad');

    // Same slug, no captcha field declared.
    $form->fields()->delete();
    $form->delete();
    makeContactForm(false);

    $withoutField = (new CustomFormBlock)->render(['form_slug' => 'contacto'], null, 'es');

    expect($withoutField)->not->toContain('name="captcha_response"');
});

test('submitting without a captcha is rejected when the form declares the field', function (): void {
    makeContactForm(true);

    $response = $this->post('/forms/contacto/submit', ['nombre' => 'Ana']);

    $response->assertSessionHasErrors(['captcha_response']);

    expect(FormSubmission::count())->toBe(0);
});

test('submitting with a valid captcha is accepted and never persists the challenge', function (): void {
    makeContactForm(true);

    $challenge = app(CaptchaService::class)->generateChallenge();
    preg_match('/(\d+)\s*\+\s*(\d+)/', $challenge['svg'], $matches);
    $answer = (string) ((int) $matches[1] + (int) $matches[2]);

    $response = $this->post('/forms/contacto/submit', [
        'nombre' => 'Ana',
        '_captcha_token' => $challenge['token'],
        'captcha_response' => $answer,
    ]);

    $response->assertSessionHasNoErrors();

    $submission = FormSubmission::firstOrFail();

    expect($submission->payload)->toHaveKey('nombre')
        ->and($submission->payload)->not->toHaveKey('captcha_response')
        ->and($submission->payload)->not->toHaveKey('_captcha_token');
});

test('the core captcha is enforced when no captcha module is installed', function (): void {
    expect(app(ModuleManager::class)->isModuleEnabled('captcha'))->toBeFalse();
    makeContactForm(true);
    $html = (new CustomFormBlock)->render(['form_slug' => 'contacto'], null, 'es');
    expect($html)->toContain('name="captcha_response"');
    $this->post('/forms/contacto/submit', ['nombre' => 'Ana'])->assertSessionHasErrors(['captcha_response']);
    expect(FormSubmission::count())->toBe(0);
});
