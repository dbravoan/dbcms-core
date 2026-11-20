<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use DbCms\Core\Content\Contracts\EditorialWorkflowInterface;
use DbCms\Core\Content\Contracts\EntitySchemaRegistryInterface;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\DTOs\Content\PendingEditorialDraft;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Services\FormAdministrationService;
use DbCms\Core\Forms\Services\FormDefinitionService;
use DbCms\Core\Forms\Services\FormMutationHandler;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Capabilities\EntityReadService;
use DbCms\Core\Services\Capabilities\EntityTranslationWriter;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['database.default' => 'forms_parity', 'database.connections.forms_parity' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    Schema::create('markets', static fn (Blueprint $table) => $table->id());
    (require Cms::path('database/migrations/2026_09_01_000032_create_form_tables.php'))->up();
    (require Cms::path('database/migrations/2026_11_13_000001_expand_composable_forms.php'))->up();
    $locale = Mockery::mock(LocalizationServiceInterface::class);
    $locale->shouldReceive('defaultLocale')->andReturn('es');
    app()->instance(LocalizationServiceInterface::class, $locale);
    $registry = app(EntitySchemaRegistryInterface::class);
    $this->reader = new EntityReadService($registry, $locale);
    $this->entities = new EntityMutationService($this->reader, new EntityTranslationWriter);
    $this->input = ['translations' => ['es' => ['name' => 'QA formulario']], 'attributes' => ['fields' => [['field_key' => 'Full name', 'type' => 'text', 'is_required' => true, 'translations' => ['es' => ['label' => 'Nombre']]]]]];
});

it('creates native forms with the same structural fields and defaults as the editor', function (): void {
    $result = $this->entities->create('form', $this->input);
    $form = CustomForm::findOrFail($result['id']);

    expect($form->slug)->toBe('qa-formulario')
        ->and($form->is_active)->toBeFalse()
        ->and($form->fields()->firstOrFail()->field_key)->toBe('full_name')
        ->and($result['relations']['fields'][0]['translations']['es']['label'])->toBe('Nombre');
});

it('keeps fields when a partial metadata patch omits them and replaces them only when supplied', function (): void {
    $result = $this->entities->create('form', $this->input);
    $this->entities->update('form', $result['id'], ['attributes' => ['notification_email' => 'qa@example.test']]);
    expect(CustomForm::findOrFail($result['id'])->fields()->count())->toBe(1);
    $this->entities->update('form', $result['id'], ['attributes' => ['fields' => []]]);
    expect(CustomForm::findOrFail($result['id'])->fields()->count())->toBe(0);
});

it('preserves omitted translation attributes in a partial locale patch', function (): void {
    $this->input['translations']['es']['success_message'] = 'Mensaje conservado';
    $result = $this->entities->create('form', $this->input);
    $updated = $this->entities->update('form', $result['id'], ['translations' => ['es' => ['name' => 'Nombre revisado']]]);

    expect($updated['translations']['es']['name'])->toBe('Nombre revisado')
        ->and($updated['translations']['es']['success_message'])->toBe('Mensaje conservado');
});

it('loads omitted persisted definition attributes without clearing stored layout or dirty actions under strict mode', function (): void {
    $layout = ['steps' => [['key' => 'contact', 'groups' => [['key' => 'details', 'columns' => ['mobile' => 1, 'tablet' => 1, 'desktop' => 1], 'fields' => ['full_name']]]]]];
    $this->input['attributes']['layout'] = $layout;
    $this->input['attributes']['actions'] = [['id' => 'confirm', 'type' => 'confirm']];
    $result = $this->entities->create('form', $this->input);
    $partial = CustomForm::query()->select('id', 'slug')->findOrFail($result['id']);
    $partial->actions = [];
    $prepared = app(FormMutationHandler::class)->prepare($partial, ['attributes' => ['notification_email' => 'updated@example.test']], false);
    expect($prepared['attributes']['notification_email'])->toBe('updated@example.test')
        ->and($partial->layout)->toBe($layout)
        ->and($partial->actions)->toBe([])
        ->and($partial->isDirty('actions'))->toBeTrue()
        ->and($partial->isDirty('layout'))->toBeFalse();
    expect(fn () => $partial->getAttribute('unselected_unknown_attribute'))->toThrow(MissingAttributeException::class);
    expect(fn () => app(FormMutationHandler::class)->prepare($partial, ['attributes' => ['fields' => []]], false))->toThrow(ValidationException::class);
    expect(CustomForm::findOrFail($result['id'])->layout)->toBe($layout);
});

it('validates an unsaved form without inventing persisted definition attributes', function (): void {
    $form = new CustomForm;
    app(FormDefinitionService::class)->validate([], [], $form);
    expect($form->getAttributes())->not->toHaveKeys(['layout', 'actions', 'notification_email']);
});

it('rejects unsupported field types and duplicate normalized keys', function (array $fields): void {
    $this->input['attributes']['fields'] = $fields;
    expect(fn () => $this->entities->create('form', $this->input))->toThrow(ValidationException::class);
    expect(CustomForm::count())->toBe(0);
})->with([
    [[['field_key' => 'bad', 'type' => 'executable']]],
    [[['field_key' => 'Full name', 'type' => 'text'], ['field_key' => 'full_name', 'type' => 'text']]],
]);

it('captures restores and hydrates field revisions without changing their ids or translations', function (): void {
    $result = $this->entities->create('form', $this->input);
    $form = CustomForm::findOrFail($result['id']);
    $handler = app(FormMutationHandler::class);
    $live = $handler->captureRevision($form);
    $this->entities->update('form', $result['id'], ['attributes' => ['fields' => []]]);
    $handler->restoreRevision($form, $live);
    expect($handler->captureRevision($form))->toEqual($live);
    $draft = clone $form;
    $handler->hydrateRevision($draft, $live);
    expect($draft->fields[0]->translations[0]->label)->toBe('Nombre');
});

it('uses the same writer for the HTTP editor adapter and native input', function (): void {
    $service = new FormAdministrationService($this->entities, $this->reader);
    $input = $this->input['attributes'] + ['translations' => $this->input['translations']];
    $form = $service->create($input, null);
    $service->update($form, ['fields' => [['field_key' => 'email', 'type' => 'email', 'translations' => ['es' => ['label' => 'Correo']]]]]);
    $read = $this->reader->find('form', $form->id);

    expect($read['relations']['fields'][0]['field_key'])->toBe('email')
        ->and($read['relations']['fields'][0]['translations']['es']['label'])->toBe('Correo');
});

it('stages active form fields while keeping the live definition intact until restoration of the reviewed snapshot', function (): void {
    $result = $this->entities->create('form', $this->input);
    $form = CustomForm::findOrFail($result['id']);
    CustomForm::withoutEvents(fn () => $form->update(['is_active' => true]));
    $actor = new User;
    $actor->setAttribute('id', 1);
    Auth::setUser($actor);
    $pending = [];
    $audits = Mockery::mock(EditorialWorkflowInterface::class);
    $audits->shouldReceive('isEnabled')->andReturn(true);
    $audits->shouldReceive('latestRevisionId')->andReturn(0);
    $audits->shouldReceive('requiresAudit')->andReturn(false);
    $audits->shouldReceive('pendingDraft')->andReturn(null);
    $audits->shouldReceive('stageDraft')->once()->andReturnUsing(function ($entity, $user, $snapshot, $hash) use (&$pending): PendingEditorialDraft {
        $pending = $snapshot;

        return new PendingEditorialDraft(99, $snapshot);
    });
    app()->instance(EditorialWorkflowInterface::class, $audits);
    $update = $this->entities->update('form', $form->id, ['attributes' => ['fields' => []]]);

    expect($update['live_preserved'])->toBeTrue()
        ->and($update['relations']['fields'])->toBe([])
        ->and($form->fresh()->fields()->count())->toBe(1);
    $snapshots = app(ContentSnapshotService::class);
    $draft = $snapshots->hydrate($form, $pending);
    expect($draft->getRelation('fields'))->toHaveCount(0);
    $snapshots->restore($form, $pending);
    expect($form->fresh()->fields()->count())->toBe(0);
});

it('refuses incomplete form revisions before replacing any live row', function (array $relations): void {
    $result = $this->entities->create('form', $this->input);
    $form = CustomForm::findOrFail($result['id']);
    $snapshots = app(ContentSnapshotService::class);
    $snapshot = $snapshots->capture($form);
    $snapshot['attributes']['slug'] = 'must-not-be-written';
    $snapshot['module_relations'] = $relations;

    expect(fn () => $snapshots->hydrate($form, $snapshot))->toThrow(DomainException::class)
        ->and(fn () => $snapshots->restore($form, $snapshot))->toThrow(DomainException::class);
    expect($form->fresh()->slug)->toBe('qa-formulario')
        ->and($form->fields()->sole()->field_key)->toBe('full_name');
})->with([
    'missing fields' => [[]],
    'incomplete field' => [['fields' => [['attributes' => ['id' => 1]]]]],
]);
