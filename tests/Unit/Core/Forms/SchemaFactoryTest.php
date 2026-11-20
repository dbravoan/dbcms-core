<?php

declare(strict_types=1);

use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\SchemaFactory;
use Illuminate\Database\Eloquent\Collection;

function schemaFactory(): SchemaFactory
{
    return new SchemaFactory;
}

test('it builds the same schema from an array and from json', function (): void {
    $input = [
        ['field_key' => 'nombre', 'type' => 'text', 'label' => 'Nombre', 'placeholder' => 'Tu nombre', 'is_required' => true, 'validation_rules' => 'max:255'],
        ['field_key' => 'plan', 'type' => 'select', 'label' => 'Plan', 'options' => "pro | Pro\nlite | Lite"],
    ];

    $fromArray = schemaFactory()->fromArray($input);
    $fromJson = schemaFactory()->fromJson((string) json_encode(['fields' => $input]));

    expect($fromArray)->toHaveCount(2)
        ->and($fromJson)->toHaveCount(2)
        ->and($fromArray[0]->key)->toBe('nombre')
        ->and($fromArray[0]->required)->toBeTrue()
        ->and($fromArray[0]->rules)->toBe('max:255')
        ->and($fromArray[0]->placeholder)->toBe('Tu nombre')
        ->and($fromArray[1]->options)->toBe([
            ['value' => 'pro', 'label' => 'Pro'],
            ['value' => 'lite', 'label' => 'Lite'],
        ]);

    // Both entry points produce an identical structure.
    expect($fromJson[1]->options)->toBe($fromArray[1]->options)
        ->and($fromJson[0]->key)->toBe($fromArray[0]->key);
});

test('it accepts associative options and ignores fields without a key', function (): void {
    $schema = schemaFactory()->fromArray([
        ['field_key' => '', 'type' => 'text'],
        ['field_key' => 'pais', 'type' => 'select', 'options' => ['es' => 'España', 'fr' => 'Francia']],
    ]);

    expect($schema)->toHaveCount(1)
        ->and($schema[0]->options)->toBe([
            ['value' => 'es', 'label' => 'España'],
            ['value' => 'fr', 'label' => 'Francia'],
        ]);
});

test('it normalizes eloquent form field models', function (): void {
    $field = new FormField([
        'field_key' => 'nombre',
        'type' => 'text',
        'is_required' => true,
    ]);
    $field->setRelation('translations', new Collection);

    $schema = schemaFactory()->fromModels([$field]);

    expect($schema)->toHaveCount(1)
        ->and($schema[0]->key)->toBe('nombre')
        ->and($schema[0]->type)->toBe('text')
        ->and($schema[0]->required)->toBeTrue()
        ->and($schema[0]->label())->toBe('nombre')
        ->and($schema[0]->options)->toBe([]);
});

test('it supports the alternate short keys used by inline blade schemas', function (): void {
    $schema = schemaFactory()->fromArray([
        ['key' => 'apodo', 'type' => 'text', 'label' => 'Apodo', 'required' => true, 'rules' => 'min:2'],
    ]);

    expect($schema[0]->key)->toBe('apodo')
        ->and($schema[0]->required)->toBeTrue()
        ->and($schema[0]->rules)->toBe('min:2');
});
