<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockDataSourceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\Market;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class BlockSources
{
    /** @var array<string,array{items:list<array<string,mixed>>,has_more:bool}> */
    private array $results = [];

    public function __construct(private readonly PublicContentDiscoveryInterface $public) {}

    /** @return array<string,array<string,mixed>> */
    public function describe(): array
    {
        $result = [];
        foreach ($this->public->schemas() as $type => $schema) {
            if ($schema->publicContent?->blockSource === null) {
                continue;
            }
            $source = $this->source($type);
            $result[$type] = ['name' => $schema->label, 'fields' => $source->fields(), 'filters' => $source->filters()];
        }

        return $result;
    }

    /** @param array<string,mixed> $filters
     * @return array{items:list<array<string,mixed>>,has_more:bool} */
    public function items(string $type, Market $market, string $locale, array $filters = [], int $page = 1, int $limit = 20): array
    {
        $filters = $this->validateQuery($type, $market, $locale, $filters, $page, $limit);
        $source = $this->source($type);
        $key = hash('sha256', json_encode([$type, $market->id, $locale, $filters, $page, $limit], JSON_THROW_ON_ERROR));
        if (! isset($this->results[$key])) {
            $result = $source->items($market, $locale, $filters, $page, $limit);
            if (count($result['items']) > $limit) {
                throw new \LogicException('La fuente excede el límite de elementos solicitado.');
            }
            $allowed = array_fill_keys(array_keys($source->fields()), true);
            $result['items'] = array_map(static fn (array $item): array => array_intersect_key($item, $allowed), $result['items']);
            $this->results[$key] = $result;
        }

        return $this->results[$key];
    }

    /** Defensive persisted reads keep unavailable contributions inert.
     * @param array<string,mixed> $filters
     * @return array{items:list<array<string,mixed>>,has_more:bool} */
    public function read(string $type, Market $market, string $locale, array $filters = [], int $page = 1, int $limit = 20): array
    {
        if (! $this->available($type)) {
            return ['items' => [], 'has_more' => false];
        }
        try {
            return $this->items($type, $market, $locale, $filters, $page, $limit);
        } catch (ValidationException) {
            return ['items' => [], 'has_more' => false];
        }
    }

    public function available(string $type): bool
    {
        $schema = $this->public->schemas()[$type] ?? null;

        return $schema !== null && $schema->publicContent !== null && $schema->publicContent->blockSource !== null;
    }

    /** @return array{fields:array<string,array<string,mixed>>,filters:array<string,array<string,mixed>>} */
    public function definition(string $type): array
    {
        $source = $this->source($type);

        return ['fields' => $source->fields(), 'filters' => $source->filters()];
    }

    /** Validates the finite query without loading owner records.
     * @param array<string,mixed> $filters
     * @return array<string,mixed> */
    public function validateQuery(string $type, Market $market, string $locale, array $filters = [], int $page = 1, int $limit = 20): array
    {
        if ($page < 1 || $page > 10000 || $limit < 1 || $limit > 50 || ! $market->is_active || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['source' => 'Contexto o paginación de contenido público inválidos.']);
        }
        $schema = $this->source($type)->filters();
        $filters = array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== '');
        if (array_diff(array_keys($filters), array_keys($schema)) !== []) {
            throw ValidationException::withMessages(['filters' => 'La fuente no admite alguno de los filtros recibidos.']);
        }
        $rules = [];
        foreach ($schema as $key => $field) {
            $rules[$key] = $field['rules'] ?? match ($field['type'] ?? 'text') {
                'integer' => ['nullable', 'integer', 'min:1'],
                'boolean' => ['nullable', 'boolean'],
                'select' => ['nullable', Rule::in(array_keys((array) ($field['options'] ?? [])))],
                default => ['nullable', 'string', 'max:200'],
            };
        }
        Validator::make($filters, $rules)->validate();

        return $filters;
    }

    private function source(string $type): BlockDataSourceInterface
    {
        $definition = $this->public->schemas()[$type] ?? null;
        $class = $definition?->publicContent?->blockSource;
        if ($class === null) {
            throw ValidationException::withMessages(['source' => 'Fuente pública no disponible: '.$type]);
        }
        $source = app($class);
        if (! $source instanceof BlockDataSourceInterface) {
            throw new \LogicException('La fuente debe implementar BlockDataSourceInterface.');
        }

        return $source;
    }
}
