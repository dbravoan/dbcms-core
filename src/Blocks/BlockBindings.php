<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use Closure;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\SafeContentRenderer;
use Illuminate\Validation\ValidationException;

final class BlockBindings
{
    /** @var array<string,mixed>|null */
    private ?array $item = null;

    public function __construct(private readonly BlockSources $sources, private readonly BlockRegistryInterface $blocks) {}

    /** @param array<string,mixed> $item */
    public function withItem(array $item, Closure $render): string
    {
        $previous = $this->item;
        $this->item = $item;
        try {
            return $render();
        } finally {
            $this->item = $previous;
        }
    }

    /** @param array<string,mixed> $payload
     * @return array<string,mixed> */
    public function resolve(string $type, array $payload, Market $market, string $locale): array
    {
        $schema = $this->blocks->get($type)?->getEditorSchema() ?? [];
        foreach ((array) ($payload['bindings'] ?? []) as $target => $binding) {
            $field = $schema[$target] ?? null;
            if (! is_array($binding) || $field === null || ! in_array($field['type'] ?? 'text', ['text', 'string', 'textarea', 'url', 'media', 'number', 'integer', 'email'], true)) {
                continue; // Invalid persisted configuration cannot create new payload keys.
            }
            $source = (string) ($binding['source'] ?? '');
            $key = (string) ($binding['field'] ?? '');
            if ($source === 'item') {
                if ($this->item === null || ! array_key_exists($key, $this->item)) {
                    $payload[$target] = '';

                    continue;
                }
                $value = $this->item[$key];
            } else {
                $id = $binding['id'] ?? null;
                if (! is_int($id) && (! is_string($id) || ! ctype_digit($id))) {
                    $payload[$target] = '';

                    continue;
                }
                $row = $this->sources->read($source, $market, $locale, ['id' => (int) $id], 1, 1)['items'][0] ?? null;
                if ($row === null || ! array_key_exists($key, $row)) {
                    $value = ''; // A withdrawn reference never exposes stale/private data.
                } else {
                    $value = $row[$key];
                }
            }
            if (! is_scalar($value) && $value !== null) {
                $value = '';
            }
            if (in_array($field['type'] ?? '', ['url', 'media'], true) && (! is_string($value) || ! app(SafeContentRenderer::class)->isSafeUrl($value, $field['type'] === 'media'))) {
                $value = '';
            }
            $payload[$target] = in_array($field['type'] ?? 'text', ['text', 'string', 'textarea', 'url', 'media', 'email'], true) && $value !== null ? (string) $value : $value;
        }

        return $payload;
    }

    /** @param array<string,mixed> $payload */
    public function requiresContext(array $payload): bool
    {
        foreach (app(BlockTree::class)->nodes($payload) as $node) {
            if (($node['type'] ?? '') === 'collection' || ! empty($node['bindings'])) {
                return true;
            }
        }

        return false;
    }

    /** Strict write validation preserves collection ancestry while walking declared slots.
     * @param array<string,mixed> $payload */
    public function validateTree(array $payload, Market $market, string $locale): void
    {
        app(BlockTree::class)->assertLimits($payload);
        $this->validateNode($payload, $market, $locale, null, 0);
    }

    /** @param array<string,mixed> $node */
    private function validateNode(array $node, Market $market, string $locale, ?string $itemSource, int $depth): void
    {
        if ($depth > (int) config('cms.blocks.max_depth', 16)) {
            $this->invalid('La composición supera la profundidad permitida.');
        }
        $type = (string) ($node['type'] ?? 'text');
        if (! app(BlockTree::class)->supports($node) || ($depth > 0 && ! $this->blocks->isAvailable($type))) {
            return; // Nested unavailable owners remain opaque, including legacy columns.
        }
        if (! empty($node['columns']) && is_array($node['columns'])) {
            foreach ($node['columns'] as $column) {
                foreach ((array) ($column['blocks'] ?? []) as $child) {
                    if (is_array($child)) {
                        $this->validateNode($child, $market, $locale, $itemSource, $depth + 1);
                    }
                }
            }

            return;
        }
        if (! $this->blocks->isAvailable($type)) {
            return;
        }
        $schema = $this->blocks->get($type)?->getEditorSchema() ?? [];
        $bindings = $node['bindings'] ?? [];
        if (! is_array($bindings) || ($bindings !== [] && array_is_list($bindings))) {
            $this->invalid('Las vinculaciones deben indicar campos declarados.');
        }
        foreach ($bindings as $target => $binding) {
            $field = $schema[$target] ?? null;
            if (! is_array($binding) || array_diff(array_keys($binding), ['source', 'field', 'id']) !== []
                || ! is_array($field) || ! in_array($field['type'] ?? 'text', ['text', 'string', 'textarea', 'url', 'media', 'number', 'integer', 'email'], true)
                || ! is_string($binding['source'] ?? null) || ! is_string($binding['field'] ?? null)) {
                $this->invalid('Solo se pueden vincular campos escalares declarados con una fuente y un campo públicos.');
            }
            $source = $binding['source'];
            if ($source === 'item') {
                if ($itemSource === null || array_key_exists('id', $binding)) {
                    $this->invalid('La vinculación al elemento requiere una colección antecesora.');
                }
                $source = $itemSource;
            } else {
                $id = $binding['id'] ?? null;
                if ((! is_int($id) && (! is_string($id) || ! ctype_digit($id))) || (int) $id < 1) {
                    $this->invalid('Selecciona una referencia pública válida.');
                }
                if (($this->sources->items($source, $market, $locale, ['id' => (int) $id], 1, 1)['items'][0] ?? null) === null) {
                    $this->invalid('La referencia no está disponible en este mercado e idioma.');
                }
            }
            $sourceField = $this->sources->definition($source)['fields'][$binding['field']] ?? null;
            if (! is_array($sourceField) || ! $this->compatible((string) ($field['type'] ?? 'text'), (string) ($sourceField['type'] ?? 'text'))) {
                $this->invalid('El campo público no existe o su tipo no es compatible con el destino.');
            }
        }
        $collectionSource = null;
        if ($type === 'collection') {
            $collectionSource = (string) ($node['source'] ?? '');
            $this->sources->validateQuery($collectionSource, $market, $locale, (array) ($node['filters'] ?? []), 1, (int) ($node['per_page'] ?? 6));
        }
        foreach (app(BlockTree::class)->slots($node) as $slot) {
            $context = $type === 'collection' && $slot['path'] === ['template'] ? $collectionSource : $itemSource;
            foreach ($slot['nodes'] as $child) {
                $this->validateNode($child, $market, $locale, $context, $depth + 1);
            }
        }
    }

    private function compatible(string $target, string $source): bool
    {
        return match ($target) {
            'integer' => $source === 'integer',
            'number' => in_array($source, ['integer', 'number'], true),
            'url' => $source === 'url',
            'media' => in_array($source, ['image', 'media', 'url'], true),
            'email' => $source === 'email',
            default => in_array($source, ['text', 'string', 'textarea', 'email', 'url', 'image', 'media', 'integer', 'number', 'boolean'], true),
        };
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['bindings' => $message]);
    }
}
