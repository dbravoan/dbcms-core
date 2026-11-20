<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\SchemaFactory;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Privacy\LegalPageService;

final class FormPresentationService
{
    public function __construct(private readonly FormDomainService $forms, private readonly LocalizationServiceInterface $localization, private readonly SchemaFactory $schemas) {}

    /** @param array<string,mixed> $props
     * @return array<string,mixed>
     */
    public function data(array $props): array
    {
        $market = $props['market'] ?? (app()->bound('current.market') ? app('current.market') : null);
        $market = $market instanceof Market ? $market : null;
        $locale = (string) ($props['locale'] ?? app()->getLocale());
        $form = $props['form'] ?? null;
        $form = $form instanceof CustomForm ? $form : $this->forms->getFormBySlug((string) ($props['slug'] ?? 'contact'), $market);
        if ($form !== null) {
            $form->loadDefinitionAttributes();
            $form->loadMissing('translations', 'fields.translations');
        }
        $slug = $form->slug ?? (string) ($props['slug'] ?? '');
        $translation = $form?->translation($locale, $market?->id);
        if ($translation === null) {
            $form = null;
        }
        $fields = $form?->fields->map(static function (FormField $field) use ($locale, $market): array {
            $translation = $field->translation($locale, $market?->id);

            return $field->only(['field_key', 'type', 'is_required', 'validation_rules']) + ($translation?->only(['label', 'placeholder', 'options']) ?? []);
        })->all() ?? [];

        $schema = $this->schemas->fromArray($fields);
        $byKey = [];
        foreach ($schema as $field) {
            $byKey[$field->key] = $field;
        }
        $steps = [];
        foreach (($form?->layout['steps'] ?? []) as $step) {
            $groups = [];
            foreach ($step['groups'] as $group) {
                $groups[] = [
                    'label' => $group['labels'][$locale] ?? '',
                    'columns' => $group['columns'],
                    'schema' => array_values(array_filter(array_map(static fn (string $key) => $byKey[$key] ?? null, $group['fields']))),
                ];
            }
            $steps[] = ['label' => $step['labels'][$locale] ?? '', 'groups' => $groups];
        }

        return [
            'targetForm' => $form,
            'collectionReady' => app(LegalPageService::class)->canCollect('forms'),
            'url' => $props['actionUrl'] ?? $this->localization->url('/forms/'.$slug.'/submit', $market, $locale),
            'formTitle' => $props['title'] ?? $translation?->name,
            'formDescription' => $translation?->description,
            'btnLabel' => $props['buttonText'] ?? $translation?->submit_button_text,
            'wrapperClass' => ($props['showCard'] ?? true) ? 'cms-card p-6 sm:p-8 rounded-2xl max-w-2xl mx-auto' : 'w-full',
            'schema' => $schema,
            'steps' => $steps,
        ];
    }
}
