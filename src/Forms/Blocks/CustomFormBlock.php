<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Blocks;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Blocks\Contracts\SearchableBlockReferencesInterface;
use DbCms\Core\Blocks\Contracts\TranslatableBlockInterface;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\Services\FormDomainService;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Validation\ValidationException;

class CustomFormBlock extends AbstractBlockType implements SearchableBlockReferencesInterface, TranslatableBlockInterface
{
    public function getType(): string
    {
        return 'form';
    }

    public function getName(): string
    {
        return 'Formulario Interactivo de Captura';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-clipboard-list';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDescription(): string
    {
        return 'Formulario dinámico configurable con campos personalizados y protección anti-spam honeypot.';
    }

    public function getRequiredModule(): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function getTranslationPaths(): array
    {
        return ['title', 'button_text'];
    }

    public function getEditorData(): array
    {
        return ['forms' => CustomForm::where('is_active', true)->with(['translations', 'fields.translations'])->get()->map(fn (CustomForm $form): array => ['id' => $form->id, 'name' => $form->name, 'slug' => $form->slug, 'fields' => $form->fields->map(fn (FormField $field): array => ['key' => $field->field_key, 'label' => $field->label, 'type' => $field->type, 'required' => (bool) $field->is_required])->all()])->all()];
    }

    public function getContextualEditorData(Market $market, string $locale): array
    {
        return ['forms' => array_values(app(FormDomainService::class)->getPublicForms($market, $locale)->map(static function (CustomForm $form) use ($market, $locale): array {
            $translation = PublicTranslation::resolve($form, $locale, $market);

            return ['id' => $form->id, 'name' => (string) $translation?->getAttribute('name'), 'slug' => $form->slug];
        })->all())];
    }

    /** @return array{items:list<array<string,mixed>>,has_more:bool} */
    public function searchReferences(Market $market, string $locale, string $source, string $search, int $limit, int $offset): array
    {
        $this->assertReference($source, 'slug');
        $query = app(FormDomainService::class)->publicFormsQuery($market, $locale);
        if (trim($search) !== '') {
            PublicTranslation::constrainResolvedField($query, $market, $locale, 'name', 'like', '%'.trim($search).'%');
        }
        $limit = max(1, min(50, $limit));
        $records = $query->orderBy('id')->offset(max(0, $offset))->limit($limit + 1)->get();
        $items = [];
        foreach ($records->take($limit) as $form) {
            $translation = PublicTranslation::resolve($form, $locale, $market);
            $items[] = ['id' => $form->id, 'name' => (string) $translation?->getAttribute('name'), 'slug' => $form->slug];
        }

        return ['items' => $items, 'has_more' => $records->count() > $limit];
    }

    public function referenceExists(Market $market, string $locale, string $source, string $key, string|int $value): bool
    {
        $this->assertReference($source, $key);

        return app(FormDomainService::class)->publicFormsQuery($market, $locale)->where('slug', $value)->exists();
    }

    private function assertReference(string $source, string $key): void
    {
        if ($source !== 'forms' || $key !== 'slug') {
            throw ValidationException::withMessages(['references' => 'Origen o campo de referencias no admitido.']);
        }
    }

    public function getEditorSchema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Título', 'description' => 'Título opcional sobre el formulario.'],
            'form_slug' => ['type' => 'select', 'required' => true, 'label' => 'Formulario', 'options' => ['' => 'Selecciona un formulario'], 'options_source' => 'forms', 'option_label' => 'name', 'option_value' => 'slug', 'rules' => ['nullable', 'string', 'max:255'], 'description' => 'Formulario que se insertará en la página.'],
            'button_text' => ['type' => 'text', 'label' => 'Texto del botón', 'description' => 'Texto del botón de envío.'],
        ];
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Cuéntanos tu proyecto',
            'form_slug' => '',
            'button_text' => 'Enviar mensaje',
        ];
    }

    /** @return array<string, mixed> */
    public function getExamplePayload(string $locale): array
    {
        if (str_starts_with(strtolower($locale), 'es')) {
            return $this->getDefaultPayload();
        }

        return array_replace($this->getDefaultPayload(), ['title' => 'Tell us about your project', 'button_text' => 'Send message']);
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $slug = (string) ($payload['form_slug'] ?? '');
        $title = ! empty($payload['title']) ? (string) $payload['title'] : null;
        $btnText = ! empty($payload['button_text']) ? (string) $payload['button_text'] : null;

        return view('forms::components.form-engine', [
            'slug' => $slug,
            'market' => $market,
            'locale' => $locale,
            'title' => $title,
            'buttonText' => $btnText,
            'showCard' => true,
        ])->render();
    }
}
