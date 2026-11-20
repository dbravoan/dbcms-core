<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Forms\Contracts\FormEditorServiceInterface;
use DbCms\Core\Forms\DTOs\FormEditorData;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\Support\FieldTypeOptions;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\EditorialDraftService;

final class FormEditorService implements FormEditorServiceInterface
{
    public function __construct(
        private readonly EditorialDraftService $drafts,
        private readonly FormSchemaService $schema,
        private readonly FieldTypeOptions $fieldTypeOptions,
    ) {}

    public function creation(): FormEditorData
    {
        $languages = Language::where('is_active', true)->get();
        $locales = $languages->pluck('code')->all() ?: ['es'];
        $defaults = [
            ['field_key' => 'nombre', 'type' => 'text', 'validation_rules' => 'required|string|max:255', 'label' => 'Nombre y apellidos', 'placeholder' => 'Tu nombre completo...'],
            ['field_key' => 'email', 'type' => 'email', 'validation_rules' => 'required|email', 'label' => 'Correo electrónico', 'placeholder' => 'ejemplo@empresa.com'],
            ['field_key' => 'mensaje', 'type' => 'textarea', 'validation_rules' => 'required|string|min:10', 'label' => 'Mensaje', 'placeholder' => '¿En qué podemos ayudarte?'],
        ];
        $fields = array_map(static function (array $field) use ($locales): array {
            $translations = [];
            foreach ($locales as $locale) {
                $translations[$locale] = ['label' => $field['label'], 'placeholder' => $field['placeholder'], 'options' => ''];
            }

            return array_intersect_key($field, array_flip(['field_key', 'type', 'validation_rules'])) + ['is_required' => true, 'translations' => $translations];
        }, $defaults);

        return new FormEditorData(new CustomForm(['is_active' => true]), array_values($fields), [], $languages,
            Market::with('languages')->where('is_active', true)->get(), [], array_values($this->fieldTypeOptions->list()));
    }

    public function editing(CustomForm $form): FormEditorData
    {
        $form = $this->drafts->forEditor($form);
        assert($form instanceof CustomForm);
        $form->loadMissing(['translations', 'fields.translations', 'markets']);
        $languages = Language::where('is_active', true)->get();
        $translationsMap = [];
        foreach ($form->translations as $translation) {
            if ($translation->market_id !== null) {
                continue;
            }
            $translationsMap[$translation->locale] = [
                'name' => $translation->name,
                'description' => $translation->description ?? '',
                'submit_button_text' => $translation->submit_button_text ?? '',
                'success_message' => $translation->success_message ?? '',
            ];
        }
        $fields = $form->fields->map(function (FormField $field) use ($languages): array {
            $translations = [];
            foreach ($field->translations as $translation) {
                if ($translation->market_id !== null) {
                    continue;
                }
                $translations[$translation->locale] = [
                    'label' => $translation->label,
                    'placeholder' => $translation->placeholder ?? '',
                    'options' => $this->schema->optionsToText(is_array($translation->options) ? $translation->options : null),
                ];
            }
            foreach ($languages as $language) {
                $translations[$language->code] ??= ['label' => '', 'placeholder' => '', 'options' => ''];
            }

            return [
                'id' => $field->id, 'field_key' => $field->field_key, 'type' => $field->type,
                'is_required' => (bool) $field->is_required, 'validation_rules' => $field->validation_rules ?? '',
                'translations' => $translations,
            ];
        })->values()->all();

        return new FormEditorData($form, array_values($fields), $translationsMap, $languages,
            Market::with('languages')->where('is_active', true)->get(),
            array_values(array_map(static fn ($id): int => (int) $id, $form->markets->pluck('id')->all())), array_values($this->fieldTypeOptions->list()));
    }
}
