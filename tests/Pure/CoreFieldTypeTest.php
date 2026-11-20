<?php

declare(strict_types=1);

use DbCms\Core\Forms\CoreFieldType;
use DbCms\Core\Forms\FieldSchema;

test('core types require or allow the field and keep their base rule', function (): void {
    $text = new CoreFieldType('text');
    $email = new CoreFieldType('email', 'email');
    $number = new CoreFieldType('number', 'numeric');

    $required = new FieldSchema(key: 'nombre', type: 'text', required: true);
    $optional = new FieldSchema(key: 'apodo', type: 'text', required: false);

    expect($text->rules($required)['nombre'])->toBe(['required', 'string'])
        ->and($text->rules($optional)['apodo'])->toBe(['nullable', 'string'])
        ->and($email->rules(new FieldSchema(key: 'email', type: 'email', required: true))['email'])->toBe(['required', 'email'])
        ->and($number->rules(new FieldSchema(key: 'n', type: 'number', required: true))['n'])->toBe(['required', 'numeric']);
});

test('pipe-delimited schema rules are split into individual rules', function (): void {
    // Laravel no longer explodes pipes inside an array element, so the field
    // framework must do it (otherwise `max:100` is silently ignored).
    $rules = (new CoreFieldType('text'))->rules(
        new FieldSchema(key: 'nombre', type: 'text', required: true, rules: 'max:100')
    );

    expect($rules['nombre'])->toBe(['required', 'string', 'max:100']);

    $multi = (new CoreFieldType('textarea'))->rules(
        new FieldSchema(key: 'content', type: 'textarea', required: true, rules: 'min:3|max:3000')
    );

    expect($multi['content'])->toBe(['required', 'string', 'min:3', 'max:3000']);
});
