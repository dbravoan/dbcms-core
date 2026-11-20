<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Http\Controllers\Admin\Concerns;

use Closure;
use DbCms\Core\Forms\FieldTypeCatalog;
use DbCms\Core\Forms\Models\CustomForm;
use Illuminate\Validation\Rule;

/**
 * Shared validation rules for creating and updating a dynamic form.
 *
 * Keeps the two verb controllers cohesive without duplicating the (large)
 * rule set or coupling them to each other.
 */
trait ValidatesFormPayload
{
    /**
     * @return array<string, mixed>
     */
    protected function formValidationRules(?CustomForm $form = null): array
    {
        $slugRule = $form !== null
            ? Rule::unique('custom_forms', 'slug')->ignore($form->getKey())
            : Rule::unique('custom_forms', 'slug');

        return [
            'translations' => 'required|array',
            'translations.*.name' => 'nullable|string|max:255',
            'translations.*.description' => 'nullable|string|max:1000',
            'translations.*.submit_button_text' => 'nullable|string|max:100',
            'translations.*.success_message' => 'nullable|string|max:500',
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
            'notification_email' => 'nullable|email',
            'layout' => 'nullable|array',
            'actions' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'market_ids' => 'nullable|array',
            'market_ids.*' => 'integer|exists:markets,id',
            'fields' => ['nullable', 'array', $this->singleCaptchaRule()],
            'fields.*.field_key' => 'required|string|max:50',
            // Types are declared by the core catalog + the modules: a module that
            // is not active never contributes a type here.
            'fields.*.type' => ['required', 'string', Rule::in(FieldTypeCatalog::keys())],
            'fields.*.translations' => 'nullable|array',
            'fields.*.translations.*.label' => 'nullable|string|max:255',
            'fields.*.translations.*.placeholder' => 'nullable|string|max:255',
            'fields.*.translations.*.options' => 'nullable|string|max:2000',
            'fields.*.is_required' => 'nullable|boolean',
            'fields.*.validation_rules' => 'nullable|string|max:255',
        ];
    }

    /**
     * A form can carry at most one captcha verification field.
     */
    protected function singleCaptchaRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $captchas = 0;

            foreach ($value as $field) {
                if (is_array($field) && (string) ($field['type'] ?? '') === 'captcha') {
                    $captchas++;
                }
            }

            if ($captchas > 1) {
                $fail((string) __('admin.forms_ui.single_captcha'));
            }
        };
    }
}
