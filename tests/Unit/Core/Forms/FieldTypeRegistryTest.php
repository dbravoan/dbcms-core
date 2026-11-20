<?php

declare(strict_types=1);

use DbCms\Core\Captcha\Fields\CaptchaFieldType;
use DbCms\Core\Forms\Contracts\FieldType;
use DbCms\Core\Forms\CoreFieldType;
use DbCms\Core\Forms\FieldSchema;
use DbCms\Core\Forms\FieldTypeRegistry;

/**
 * The registry is a singleton shared by the whole application, so these tests
 * must not overwrite a real type (`captcha`) with a fixture: it used to leak
 * into the rest of the suite and hid the real captcha field type from the
 * backoffice form builder. Fixtures use their own keys and are removed again.
 */
test('the core registers the primitive html field types', function (): void {
    $registry = app(FieldTypeRegistry::class);

    foreach (['text', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox', 'radio'] as $key) {
        expect($registry->has($key))->toBeTrue("Missing core field type [{$key}].");
    }
});

test('available() filters out types whose module is disabled', function (): void {
    $registry = app(FieldTypeRegistry::class);

    $registry->register(new class implements FieldType
    {
        public function key(): string
        {
            return 'fixture_unknown_type';
        }

        public function available(): bool
        {
            return false;
        }

        public function stores(): bool
        {
            return false;
        }

        public function rules(FieldSchema $field): array
        {
            return [];
        }

        public function view(): string
        {
            return 'components.cms-form.field';
        }
    });

    try {
        expect($registry->has('fixture_unknown_type'))->toBeTrue()
            ->and(array_key_exists('fixture_unknown_type', $registry->available()))->toBeFalse();
    } finally {
        $registry->forget('fixture_unknown_type');
    }
});

test('resolve() ignores unavailable and unknown field types', function (): void {
    $registry = app(FieldTypeRegistry::class);

    // Known + available.
    expect($registry->resolve('email'))->toBeInstanceOf(CoreFieldType::class);

    // Local antispam is part of the core, even without optional modules.
    expect($registry->resolve('captcha'))->toBeInstanceOf(CaptchaFieldType::class);

    expect($registry->resolve('mystery'))->toBeNull();
});

test('resolve() ignores a registered type that reports itself unavailable', function (): void {
    $registry = app(FieldTypeRegistry::class);

    // A fixture uses its own key so the core CAPTCHA remains registered.
    $registry->register(new class implements FieldType
    {
        public function key(): string
        {
            return 'fixture_unavailable';
        }

        public function available(): bool
        {
            return false;
        }

        public function stores(): bool
        {
            return false;
        }

        public function rules(FieldSchema $field): array
        {
            return [];
        }

        public function view(): string
        {
            return 'components.cms-form.field';
        }
    });

    try {
        expect($registry->resolve('fixture_unavailable'))->toBeNull();
    } finally {
        $registry->forget('fixture_unavailable');
    }

    expect($registry->resolve('captcha'))->toBeInstanceOf(CaptchaFieldType::class);
});
