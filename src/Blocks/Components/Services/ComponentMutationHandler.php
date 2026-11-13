<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\Content\Contracts\EntityMutationHandlerInterface;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\Market;
use DbCms\Core\Sections\SectionEditorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ComponentMutationHandler implements EntityMutationHandlerInterface
{
    public function __construct(private readonly ComponentProperties $properties, private readonly SectionEditorService $sections, private readonly ComponentRenderer $renderer) {}

    /** @param array<string,mixed> $args
     * @return array<string,mixed> */
    public function prepare(Model $entity, array $args, bool $creating): array
    {
        $attributes = (array) ($args['attributes'] ?? []);
        if (is_string($attributes['exposed_properties'] ?? null)) {
            $attributes['exposed_properties'] = json_decode($attributes['exposed_properties'], true);
        }
        Validator::make($attributes, [
            'exposed_properties' => 'sometimes|array', 'exposed_properties.*' => 'array:node_id,field,label',
            'exposed_properties.*.node_id' => 'required|string|max:255', 'exposed_properties.*.field' => 'required|string|max:255',
            'exposed_properties.*.label' => 'sometimes|string|max:255', 'is_active' => 'sometimes|boolean', 'is_published' => 'sometimes|boolean', 'published_at' => 'nullable|date',
        ])->validate();
        $exposed = $attributes['exposed_properties'] ?? ($entity->getAttributes()['exposed_properties'] ?? []);
        if (is_string($exposed)) {
            $exposed = json_decode($exposed, true);
        }
        $exposed = is_array($exposed) ? $exposed : [];
        $raw = $args['sections_json'] ?? null;
        $sections = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : ($creating ? [] : $this->sections->payload($entity)));
        if (! is_array($sections)) {
            throw ValidationException::withMessages(['sections_json' => 'Las secciones deben ser un objeto por idioma.']);
        }
        if ($exposed !== [] && (array_key_exists('exposed_properties', $attributes) || array_key_exists('sections_json', $args) || $creating)) {
            $found = false;
            foreach ($sections as $rows) {
                if (! is_array($rows) || $rows === []) {
                    continue;
                }
                if (! array_is_list($rows) || array_filter($rows, static fn (mixed $row): bool => ! is_array($row)) !== []) {
                    throw ValidationException::withMessages(['sections_json' => 'Cada idioma debe contener una lista de bloques.']);
                }
                /** @var list<array<string,mixed>> $validRows */
                $validRows = $rows;
                $this->properties->validate($exposed, $validRows);
                $found = true;
            }
            if (! $found) {
                throw ValidationException::withMessages(['exposed_properties' => 'Guarda primero los bloques que expondrán propiedades.']);
            }
        }
        if ($entity instanceof BlockComponent && array_key_exists('sections_json', $args)) {
            $marketIds = array_key_exists('market_ids', $args) ? (array) $args['market_ids'] : ($entity->exists ? $entity->markets()->pluck('markets.id')->all() : []);
            $markets = Market::query()->where('is_active', true)->when($marketIds !== [], fn ($query) => $query->whereIn('id', $marketIds))->with('languages')->get();
            foreach ($sections as $locale => $rows) {
                if (! is_string($locale) || ! is_array($rows)) {
                    continue;
                }
                /** @var list<array<string,mixed>> $candidate */
                $candidate = array_values(array_filter($rows, is_array(...)));
                foreach ($markets as $market) {
                    if ($market->languages->where('is_active', true)->contains('code', $locale)) {
                        $this->renderer->validateCandidate((int) ($entity->getKey() ?? 0), $candidate, $market, $locale);
                    }
                }
            }
        }
        if (($attributes['is_published'] ?? $attributes['publish'] ?? $args['publish'] ?? false) && empty($attributes['published_at'])) {
            $attributes['published_at'] = $entity->getAttribute('published_at') ?? now();
        }

        return array_replace($args, ['attributes' => $attributes]);
    }
}
