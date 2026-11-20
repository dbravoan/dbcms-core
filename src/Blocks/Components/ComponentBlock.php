<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components;

use DbCms\Core\Blocks\AbstractBlockType;
use DbCms\Core\Blocks\Components\Services\ComponentProperties;
use DbCms\Core\Blocks\Components\Services\ComponentRenderer;
use DbCms\Core\Blocks\Components\Services\PublicComponentReader;
use DbCms\Core\Blocks\Contracts\ComposableBlockInterface;
use DbCms\Core\Blocks\Contracts\ContextualBlockTextInterface;
use DbCms\Core\Blocks\Contracts\ContextualBlockValidationInterface;
use DbCms\Core\Blocks\Contracts\SearchableBlockReferencesInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Validation\ValidationException;

final class ComponentBlock extends AbstractBlockType implements ComposableBlockInterface, ContextualBlockTextInterface, ContextualBlockValidationInterface, SearchableBlockReferencesInterface
{
    public function __construct(private readonly PublicComponentReader $reader, private readonly ComponentProperties $properties, private readonly ComponentRenderer $renderer) {}

    public function getType(): string
    {
        return 'component';
    }

    public function getName(): string
    {
        return 'Componente reutilizable';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-cubes';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getDescription(): string
    {
        return 'Reutiliza una composición publicada y personaliza solo sus propiedades expuestas.';
    }

    public function getDefaultPayload(): array
    {
        return ['component_id' => '', 'overrides' => []];
    }

    public function getTranslationPaths(): array
    {
        return ['overrides.*'];
    }

    public function getEditorSchema(): array
    {
        return [
            'component_id' => ['type' => 'select', 'label' => 'Componente', 'options_source' => 'block_components', 'option_label' => 'title', 'option_value' => 'id', 'rules' => ['required', 'integer', 'min:1']],
            'overrides' => ['type' => 'object', 'label' => 'Propiedades de esta instancia', 'fields' => [], 'fields_source' => 'block_components', 'reference_field' => 'component_id', 'rules' => ['sometimes', 'array']],
        ];
    }

    public function getContextualEditorData(Market $market, string $locale): array
    {
        return [];
    }

    /** @return array{items:list<array<string,mixed>>,has_more:bool} */
    public function searchReferences(Market $market, string $locale, string $source, string $search, int $limit, int $offset): array
    {
        if ($source !== 'block_components') {
            throw ValidationException::withMessages(['source' => 'Origen de componentes no admitido.']);
        }
        $query = $this->reader->query($market, $locale)->with(['sections' => fn ($q) => $q->where('is_active', true)->where('status', 'published')->with('translations')]);
        if (preg_match('/^id:(\d+)$/D', $search, $match)) {
            $query->whereKey((int) $match[1]);
        } elseif (trim($search) !== '') {
            PublicTranslation::constrainResolvedField($query, $market, $locale, 'title', 'like', '%'.trim($search).'%');
        }
        $limit = max(1, min(50, $limit));
        $rows = $query->orderBy('id')->offset(max(0, $offset))->limit($limit + 1)->get();
        $items = [];
        foreach ($rows->take($limit) as $row) {
            $sections = $this->reader->sections($row, $market, $locale);
            $targets = $this->properties->targets($sections);
            $props = [];
            foreach ($row->exposed_properties ?? [] as $name => $property) {
                $target = $targets[$property['node_id'].'::'.$property['field']] ?? null;
                if ($target === null) {
                    continue;
                }
                $props[] = ['name' => $name, 'label' => $property['label'] ?? $name, 'type' => $target['type'], 'default' => $target['default'] ?? null];
            }
            $items[] = ['id' => $row->id, 'title' => (string) PublicTranslation::resolve($row, $locale, $market)?->getAttribute('title'), 'properties' => $props];
        }

        return ['items' => $items, 'has_more' => $rows->count() > $limit];
    }

    public function referenceExists(Market $market, string $locale, string $source, string $key, string|int $value): bool
    {
        if ($source !== 'block_components' || $key !== 'id') {
            throw ValidationException::withMessages(['source' => 'Referencia de componente no admitida.']);
        }

        return $this->reader->query($market, $locale)->whereKey($value)->exists();
    }

    public function validatePayload(array $payload, Market $market, string $locale): void
    {
        $this->renderer->validate((int) ($payload['component_id'] ?? 0), (array) ($payload['overrides'] ?? []), $market, $locale);
    }

    public function text(array $payload, Market $market, string $locale, bool $includeWidgets = true): string
    {
        return $this->renderer->text((int) ($payload['component_id'] ?? 0), (array) ($payload['overrides'] ?? []), $market, $locale, $includeWidgets);
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        return $this->renderer->render((int) ($payload['component_id'] ?? 0), (array) ($payload['overrides'] ?? []), $market, $locale ?? app()->getLocale());
    }
}
