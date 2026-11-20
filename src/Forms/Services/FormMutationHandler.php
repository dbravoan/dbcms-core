<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Services;

use DbCms\Core\Content\Contracts\EntityMutationHandlerInterface;
use DbCms\Core\Content\Contracts\EntityRelationMutationHandlerInterface;
use DbCms\Core\Content\Contracts\EntityRelationPayloadHandlerInterface;
use DbCms\Core\Content\Contracts\EntityRevisionHandlerInterface;
use DbCms\Core\Forms\Http\Controllers\Admin\Concerns\ValidatesFormPayload;
use DbCms\Core\Forms\Models\CustomForm;
use DbCms\Core\Forms\Models\FormField;
use DbCms\Core\Forms\Models\FormFieldTranslation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FormMutationHandler implements EntityMutationHandlerInterface, EntityRelationMutationHandlerInterface, EntityRelationPayloadHandlerInterface, EntityRevisionHandlerInterface
{
    use ValidatesFormPayload;

    public function __construct(private readonly FormSchemaService $schema) {}

    public function prepare(Model $entity, array $args, bool $creating): array
    {
        assert($entity instanceof CustomForm);
        $attributes = app(FormDefinitionService::class)->decode((array) ($args['attributes'] ?? []));
        $input = $attributes + array_intersect_key($args, array_flip(['translations', 'market_ids', 'fields']));
        $rules = $this->formValidationRules($creating ? null : $entity);
        $rules['translations'] = $creating ? 'required|array' : 'sometimes|array';
        Validator::make($input, $rules)->validate();
        if (array_key_exists('translations', $input) && $input['translations'] !== []) {
            if (! $creating) {
                foreach ($entity->translations()->whereNull('market_id')->get() as $translation) {
                    $input['translations'][$translation->locale] = array_replace($translation->only(['name', 'description', 'submit_button_text', 'success_message']), (array) ($input['translations'][$translation->locale] ?? []));
                }
            }
            $translations = $this->schema->normalizeTranslations($input);
            $args['form_translations'] = $translations;
        } elseif ($creating) {
            throw ValidationException::withMessages(['translations' => 'Define al menos el nombre del formulario en un idioma.']);
        }
        if ($creating || array_key_exists('slug', $attributes)) {
            $attributes['slug'] = $this->schema->resolveSlug((array) ($args['form_translations'] ?? []), isset($attributes['slug']) ? (string) $attributes['slug'] : null);
            Validator::make(['slug' => $attributes['slug']], ['slug' => $rules['slug']])->validate();
        }
        if (array_key_exists('fields', $input)) {
            $fields = array_values((array) $input['fields']);
            $keys = [];
            foreach ($fields as $field) {
                $key = ($field['type'] ?? '') === 'captcha' ? 'captcha' : Str::slug((string) $field['field_key'], '_');
                if ($key === '' || in_array($key, $keys, true)) {
                    throw ValidationException::withMessages(['fields' => 'Los identificadores de campo deben ser únicos y no vacíos.']);
                }
                $keys[] = $key;
            }
            $args['fields'] = $fields;
        }
        app(FormDefinitionService::class)->validate($input, isset($keys) ? $keys : array_values(array_map('strval', $entity->fields()->pluck('field_key')->all())), $creating ? null : $entity);
        unset($attributes['fields']);
        if ($creating) {
            $attributes += ['is_active' => false];
        }

        // Form metadata has no translation slug column; the form subsystem owns its localized persistence.
        return array_replace($args, ['attributes' => $attributes, 'translations' => []]);
    }

    public function syncRelations(Model $entity, array $args): void
    {
        assert($entity instanceof CustomForm);
        if (array_key_exists('form_translations', $args)) {
            $this->schema->syncTranslations($entity, (array) $args['form_translations']);
        }
        if (array_key_exists('fields', $args)) {
            $this->schema->syncFields($entity, (array) $args['fields']);
        }
        if (array_key_exists('market_ids', $args)) {
            $entity->markets()->sync((array) $args['market_ids']);
        }
    }

    public function captureRevision(Model $entity): array
    {
        assert($entity instanceof CustomForm);

        return ['fields' => $entity->fields()->with('translations')->get()->map(static fn (FormField $field): array => ['attributes' => $field->getAttributes(), 'translations' => $field->translations->map(static fn (FormFieldTranslation $translation): array => $translation->getAttributes())->all()])->all()];
    }

    public function validateRevision(array $revision): void
    {
        if (! isset($revision['fields']) || ! is_array($revision['fields']) || ! array_is_list($revision['fields'])) {
            throw new \DomainException('Form revisions require canonical field records.');
        }
        foreach ($revision['fields'] as $record) {
            if (! is_array($record) || ! isset($record['attributes'], $record['translations']) || ! is_array($record['attributes']) || $record['attributes'] === [] || ! is_array($record['translations']) || ! array_is_list($record['translations'])) {
                throw new \DomainException('Form revisions require field attributes and translations.');
            }
            foreach ($record['translations'] as $translation) {
                if (! is_array($translation) || $translation === [] || array_is_list($translation)) {
                    throw new \DomainException('Form revisions require canonical field translations.');
                }
            }
        }
    }

    public function hydrateRevision(Model $entity, array $revision): void
    {
        $fields = [];
        foreach ((array) ($revision['fields'] ?? []) as $record) {
            $field = (new FormField)->newInstance([], true);
            $field->setRawAttributes((array) $record['attributes'], true);
            $translations = [];
            foreach ((array) $record['translations'] as $attributes) {
                $translation = (new FormFieldTranslation)->newInstance([], true);
                $translation->setRawAttributes((array) $attributes, true);
                $translations[] = $translation;
            }
            $field->setRelation('translations', new Collection($translations));
            $fields[] = $field;
        }
        $entity->setRelation('fields', new Collection($fields));
    }

    public function restoreRevision(Model $entity, array $revision): void
    {
        assert($entity instanceof CustomForm);
        $entity->fields()->delete();
        foreach ((array) ($revision['fields'] ?? []) as $record) {
            FormField::query()->insert((array) $record['attributes']);
            if ($record['translations'] !== []) {
                FormFieldTranslation::query()->insert((array) $record['translations']);
            }
        }
        $entity->unsetRelation('fields');
    }

    public function relationPayload(Model $entity): array
    {
        assert($entity instanceof CustomForm);
        $entity->loadMissing('fields.translations');

        return ['fields' => $entity->fields->map(static function (FormField $field): array {
            $translations = [];
            foreach ($field->translations as $translation) {
                if ($translation->market_id === null) {
                    $translations[$translation->locale] = $translation->only(['label', 'placeholder', 'options']);
                }
            }

            return $field->only(['id', 'field_key', 'type', 'is_required', 'validation_rules', 'order']) + ['translations' => $translations];
        })->all()];
    }
}
