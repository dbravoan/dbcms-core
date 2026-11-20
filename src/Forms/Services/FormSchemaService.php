<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\CustomFormTranslation;
use DbCms\Core\Forms\Models\FormFieldTranslation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Normalizes and persists the localized definition of a dynamic form.
 *
 * A form is language-aware: metadata lives in `custom_form_translations`
 * (per locale, with an optional per-market override) and each field's
 * label/placeholder/options live in `form_field_translations`. This service
 * keeps the two verb controllers thin and the persistence logic cohesive.
 */
class FormSchemaService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array{name: string, description: string|null, submit_button_text: string, success_message: string|null}>
     */
    public function normalizeTranslations(array $input): array
    {
        $translations = [];

        /** @var array<string, mixed> $rawTranslations */
        $rawTranslations = (array) ($input['translations'] ?? []);

        foreach ($rawTranslations as $locale => $data) {
            if (! is_array($data) || empty($data['name'])) {
                continue;
            }

            $translations[(string) $locale] = [
                'name' => (string) $data['name'],
                'description' => $this->nullIfBlank($data['description'] ?? null),
                'submit_button_text' => ! empty($data['submit_button_text'])
                    ? (string) $data['submit_button_text']
                    : 'Enviar',
                'success_message' => $this->nullIfBlank($data['success_message'] ?? null),
            ];
        }

        if ($translations === []) {
            throw ValidationException::withMessages([
                'translations' => 'Define al menos el nombre del formulario en un idioma.',
            ]);
        }

        return $translations;
    }

    /**
     * Resolve the canonical slug from the primary translation (first non-empty).
     *
     * @param  array<string, array{name: string, ...}>  $translations
     */
    public function resolveSlug(array $translations, ?string $rawSlug = null): string
    {
        $rawSlug = $rawSlug !== null ? trim($rawSlug) : '';
        if ($rawSlug !== '') {
            return Str::slug($rawSlug);
        }

        $preferred = $translations['es']['name']
            ?? $translations[array_key_first($translations)]['name']
            ?? 'formulario';

        return Str::slug($preferred) ?: 'formulario';
    }

    /**
     * @param  array<string, array{name: string, description: string|null, submit_button_text: string, success_message: string|null}>  $translations
     */
    public function syncTranslations(CustomForm $form, array $translations): void
    {
        $locales = array_keys($translations);

        foreach ($translations as $locale => $data) {
            CustomFormTranslation::updateOrCreate(
                ['custom_form_id' => $form->getKey(), 'locale' => $locale, 'market_id' => null],
                $data
            );
        }

        // Drop translations for locales that were removed from the payload.
        $form->translations()->whereNull('market_id')->whereNotIn('locale', $locales)->delete();
    }

    /**
     * Replace the structural field definition, persisting localized labels.
     *
     * @param  array<int, mixed>  $fields
     */
    public function syncFields(CustomForm $form, array $fields): void
    {
        $form->fields()->delete();

        foreach ($fields as $order => $fieldItem) {
            if (! is_array($fieldItem)) {
                continue;
            }

            $rawKey = trim((string) ($fieldItem['field_key'] ?? ''));
            if ($rawKey === '') {
                continue;
            }

            $type = (string) ($fieldItem['type'] ?? 'text');
            $isCaptcha = $type === 'captcha';

            $field = $form->fields()->create([
                // The captcha is a singleton verification widget: fixed key and
                // always required, regardless of what the builder posted.
                'field_key' => $isCaptcha ? 'captcha' : (Str::slug($rawKey, '_') ?: 'campo_'.($order + 1)),
                'type' => $type,
                'is_required' => $isCaptcha ? true : ! empty($fieldItem['is_required']),
                'validation_rules' => $isCaptcha ? null : $this->nullIfBlank($fieldItem['validation_rules'] ?? null),
                'order' => (int) $order,
            ]);

            /** @var array<string, mixed> $fieldTranslations */
            $fieldTranslations = (array) ($fieldItem['translations'] ?? []);

            foreach ($fieldTranslations as $locale => $data) {
                if (! is_array($data) || empty($data['label'])) {
                    continue;
                }

                FormFieldTranslation::updateOrCreate(
                    ['form_field_id' => $field->getKey(), 'locale' => (string) $locale, 'market_id' => null],
                    [
                        'label' => (string) $data['label'],
                        'placeholder' => $this->nullIfBlank($data['placeholder'] ?? null),
                        'options' => $this->parseOptions($data['options'] ?? null),
                    ]
                );
            }
        }
    }

    /**
     * Parse the options textarea into an ordered list of value/label pairs.
     *
     * A JSON object map loses its order in MySQL, so options are persisted as
     * an ordered list: `[['value' => 'pro', 'label' => 'Profesional'], ...]`.
     *
     * Accepts one option per line using any of `value | label`, `value: label`
     * or `value = label`; a bare value keeps itself as both value and label.
     *
     * @return array<int, array{value: string, label: string}>|null
     */
    public function parseOptions(mixed $raw): ?array
    {
        if (is_array($raw)) {
            $pairs = [];
            foreach ($raw as $key => $value) {
                if (is_array($value)) {
                    $label = trim((string) ($value['label'] ?? ''));
                    $optionValue = trim((string) ($value['value'] ?? $key));
                } elseif (is_int($key) || ctype_digit((string) $key)) {
                    $label = trim((string) $value);
                    $optionValue = $label;
                } else {
                    $optionValue = trim((string) $key);
                    $label = trim((string) $value);
                }

                if ($label === '' && $optionValue === '') {
                    continue;
                }

                $pairs[] = [
                    'value' => $optionValue !== '' ? $optionValue : (Str::slug($label, '_') ?: $label),
                    'label' => $label !== '' ? $label : $optionValue,
                ];
            }

            return $pairs === [] ? null : $pairs;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $lines = preg_split('/[\r\n]+/', $raw) ?: [];
        $pairs = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $separator = null;
            foreach (['|', ':', '='] as $candidate) {
                if (str_contains($line, $candidate)) {
                    $separator = $candidate;
                    break;
                }
            }

            if ($separator !== null) {
                [$value, $label] = explode($separator, $line, 2);
                $value = trim($value);
                $label = trim($label);
                if ($label === '') {
                    $label = $value;
                }
                $pairs[] = [
                    'value' => $value !== '' ? $value : (Str::slug($label, '_') ?: $label),
                    'label' => $label,
                ];
            } else {
                $pairs[] = ['value' => Str::slug($line, '_') ?: $line, 'label' => $line];
            }
        }

        return $pairs === [] ? null : $pairs;
    }

    /**
     * Render stored options back into the builder textarea format.
     *
     * @param  array<int|string, mixed>|null  $options
     */
    public function optionsToText(?array $options): string
    {
        if (! is_array($options) || $options === []) {
            return '';
        }

        $lines = [];
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = trim((string) ($option['value'] ?? ''));
                $label = trim((string) ($option['label'] ?? $value));
            } elseif (is_int($key) || ctype_digit((string) $key)) {
                $value = trim((string) $option);
                $label = $value;
            } else {
                $value = trim((string) $key);
                $label = trim((string) $option);
            }

            if ($label === '') {
                continue;
            }

            $lines[] = ($value !== '' && $value !== $label) ? $value.' | '.$label : $label;
        }

        return implode("\n", $lines);
    }

    private function nullIfBlank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
