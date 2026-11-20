<?php

declare(strict_types=1);

use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\CustomFormTranslation;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\Models\FormFieldTranslation;
use DbCms\Core\Forms\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;
use Tests\Support\FormsComposition;

uses(RefreshDatabase::class, FormsComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    $this->form = CustomForm::create([
        'slug' => 'contacto',
        'is_active' => true,
    ]);

    CustomFormTranslation::create([
        'custom_form_id' => $this->form->id,
        'locale' => 'es',
        'name' => 'Formulario de Contacto General',
        'submit_button_text' => 'Enviar Mensaje',
        'success_message' => '¡Gracias por contactarnos!',
    ]);

    $fields = [
        ['field_key' => 'nombre', 'type' => 'text', 'is_required' => true, 'order' => 1, 'label' => 'Tu Nombre'],
        ['field_key' => 'email', 'type' => 'email', 'is_required' => true, 'order' => 2, 'label' => 'Correo Electrónico'],
        ['field_key' => 'mensaje', 'type' => 'textarea', 'is_required' => false, 'order' => 3, 'label' => 'Mensaje'],
    ];

    foreach ($fields as $field) {
        $created = FormField::create([
            'custom_form_id' => $this->form->id,
            'field_key' => $field['field_key'],
            'type' => $field['type'],
            'is_required' => $field['is_required'],
            'order' => $field['order'],
        ]);

        FormFieldTranslation::create([
            'form_field_id' => $created->id,
            'locale' => 'es',
            'label' => $field['label'],
        ]);
    }
});

test('it validates dynamic schema and records form submission', function (): void {
    $response = $this->postJson('/forms/contacto/submit', [
        'nombre' => 'David Desarrollador',
        'email' => 'david@example.com',
        'mensaje' => 'Quiero solicitar una cotización para un proyecto enterprise.',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => '¡Gracias por contactarnos!',
        ]);

    expect(FormSubmission::where('custom_form_id', $this->form->id)->count())->toBe(1);
});

test('it blocks spam submissions triggering the honeypot field', function (): void {
    $response = $this->postJson('/forms/contacto/submit', [
        'nombre' => 'Spam Bot',
        'email' => 'bot@spammer.com',
        '_hp_check' => 'I am a malicious bot filling hidden fields',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['spam']);

    expect(FormSubmission::count())->toBe(0);
});
