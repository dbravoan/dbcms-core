<?php

declare(strict_types=1);

namespace DbCms\Core\Content;

use Illuminate\Database\Eloquent\Model;

/**
 * Declarative description of a content entity owned by a module.
 *
 * Modules register their schemas so generic consumers (the AI assistant, MCP
 * bridge, importers, auditors...) know which entities exist, which semantic
 * fields they expose and how each one maps to a form input — without any of
 * those consumers hardcoding knowledge about the module.
 *
 * `fields` maps a *semantic role* (title, summary, body, meta_title...) to the
 * form input that carries it. The `{locale}` placeholder is resolved at runtime
 * for localized forms.
 */
final readonly class EntitySchema
{
    public const ROLE_TITLE = 'title';

    public const ROLE_SLUG = 'slug';

    public const ROLE_SUMMARY = 'summary';

    public const ROLE_BODY = 'body';

    public const ROLE_META_TITLE = 'meta_title';

    public const ROLE_META_DESCRIPTION = 'meta_description';

    /**
     * @param  array<string,mixed>  $creationDefaults
     * @param  array<string, array{input: string, label: string, required?: bool, multiline?: bool}>  $fields
     */
    public function __construct(
        public string $type,
        public string $label,
        public string $model,
        public bool $sectionable = false,
        public string $description = '',
        public array $fields = [],
        public ?string $createView = null,
        public ?string $editView = null,
        public bool $searchable = true,
        public float $searchWeight = 1.0,
        public ?string $searchPrefix = null,
        public ?string $urlTemplate = null,
        public ?string $permission = null,
        public ?string $urlPrefix = null,
        public array $creationDefaults = [],
        public ?string $ownerModule = null,
        public ?string $mutationHandler = null,
        public bool $uniqueSlugs = true,
        public ?PublicContentDefinition $publicContent = null,
        public ?string $adminFormView = null,
        public ?string $previewHandler = null,
    ) {}

    /**
     * @return list<string>
     */
    public function fieldRoles(): array
    {
        return array_keys($this->fields);
    }

    public function hasField(string $role): bool
    {
        return isset($this->fields[$role]);
    }

    public function newModel(): Model
    {
        /** @var class-string<Model> $class */
        $class = $this->model;

        return new $class;
    }

    /**
     * \Illuminate\Database\Eloquent\Model attributes an agent may write (the model's own fillable list).
     *
     * @return list<string>
     */
    public function fillableAttributes(): array
    {
        return array_values(array_filter(
            $this->newModel()->getFillable(),
            static fn (mixed $attribute): bool => is_string($attribute)
                && $attribute !== 'id',
        ));
    }

    /**
     * Semantic role → translation column, read from the declared form inputs
     * (`translations[{locale}][title]` → `title`).
     *
     * @return array<string, string>
     */
    public function translationAttributes(): array
    {
        $attributes = [];

        foreach ($this->fields as $role => $field) {
            $input = (string) ($field['input'] ?? '');

            if (preg_match('/translations\[\{locale\}\]\[([a-z0-9_]+)\]/i', $input, $matches) === 1) {
                $attributes[$role] = $matches[1];
            }
        }

        if ($this->hasField(EntitySchema::ROLE_SLUG) && ! in_array('slug', $attributes, true)) {
            $attributes[EntitySchema::ROLE_SLUG] = 'slug';
        }

        return $attributes;
    }
}
