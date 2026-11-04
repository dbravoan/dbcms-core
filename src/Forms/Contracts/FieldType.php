<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Contracts;

use DbCms\Core\Forms\FieldSchema;

/**
 * A renderable, validatable form field type.
 *
 * Field types are the extension point of the CMS form framework: the core
 * registers the primitive HTML inputs and any module (Captcha, Maps, Signatures
 * …) registers its own in the service provider's `boot()`. The renderer and the
 * validator never branch on a hardcoded type list — they ask the registry.
 *
 * A type may be *declared but unavailable* (e.g. the Captcha module is disabled):
 * `available()` then returns false and consumers must ignore the field entirely
 * (no rendering, no validation) instead of silently degrading it.
 */
interface FieldType
{
    /**
     * Unique machine key stored in `form_fields.type` (e.g. `text`, `captcha`).
     */
    public function key(): string;

    /**
     * Whether the type can be used right now (its module is installed and active).
     */
    public function available(): bool;

    /**
     * Whether the inputs of this type belong to the stored submission payload.
     *
     * Anti-spam fields (captcha) return false: they are validated but never
     * persisted.
     */
    public function stores(): bool;

    /**
     * Validation rules contributed by this type, keyed by **input name**.
     *
     * Most types map to their own field key, but a type may validate inputs
     * coming from a widget (e.g. `captcha_response` + `_captcha_token`).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(FieldSchema $field): array;

    /**
     * Blade view that renders the control for this field.
     */
    public function view(): string;
}
