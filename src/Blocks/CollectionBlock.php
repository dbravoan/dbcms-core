<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\ComposableBlockInterface;
use DbCms\Core\Blocks\Contracts\ContextualBlockTextInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Content\PublishedSectionTextService;

final class CollectionBlock extends AbstractBlockType implements ComposableBlockInterface, ContextualBlockTextInterface
{
    public function getType(): string
    {
        return 'collection';
    }

    public function getName(): string
    {
        return 'Colección de contenido';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-layer-group';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDescription(): string
    {
        return 'Repite una composición con datos públicos de una fuente disponible, filtros y paginación.';
    }

    public function getDefaultPayload(): array
    {
        return ['source' => '', 'filters' => [], 'per_page' => 6, 'pagination' => true, 'template' => [], 'empty' => []];
    }

    public function getEditorSchema(): array
    {
        $sources = app(BlockSources::class)->describe();

        return [
            'source' => ['type' => 'select', 'label' => 'Fuente pública', 'options' => ['' => 'Selecciona una fuente'] + array_map(static fn (array $source): string => $source['name'], $sources)],
            'filters' => ['type' => 'object', 'label' => 'Filtros de la fuente', 'fields_source' => 'public_sources', 'reference_field' => 'source', 'fields' => []],
            'per_page' => ['type' => 'integer', 'label' => 'Elementos por página', 'rules' => ['nullable', 'integer', 'between:1,50']],
            'pagination' => ['type' => 'boolean', 'label' => 'Navegación entre páginas'],
            'template' => ['type' => 'blocks', 'label' => 'Diseño de cada elemento'],
            'empty' => ['type' => 'blocks', 'label' => 'Contenido cuando no hay resultados'],
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        if ($market === null || ($payload['source'] ?? '') === '') {
            return '';
        }
        $locale ??= app()->getLocale();
        $key = $this->pageParameter($payload);
        $page = max(1, min(10000, (int) request()->query($key, 1)));
        $result = app(BlockSources::class)->read((string) $payload['source'], $market, $locale, (array) ($payload['filters'] ?? []), $page, (int) ($payload['per_page'] ?? 6));
        $renderer = app(BlockRenderer::class);
        if ($result['items'] === []) {
            return $renderer->children(array_values((array) ($payload['empty'] ?? [])), $market, $locale);
        }
        $html = '';
        foreach ($result['items'] as $item) {
            if ($renderer->exhausted()) {
                break;
            }
            $chunk = app(BlockBindings::class)->withItem($item, fn (): string => $renderer->children(array_values((array) ($payload['template'] ?? [])), $market, $locale));
            if (! $renderer->append($html, $chunk)) {
                break;
            }
        }
        if (! empty($payload['pagination']) && ($page > 1 || $result['has_more'])) {
            $html .= '<nav aria-label="Páginas de la colección" class="cms-collection-pagination">';
            foreach (['Anterior' => $page > 1 ? $page - 1 : null, 'Siguiente' => $result['has_more'] && $page < 10000 ? $page + 1 : null] as $label => $target) {
                if ($target !== null) {
                    $html .= '<a href="'.htmlspecialchars(request()->fullUrlWithQuery([$key => $target]), ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
                }
            }
            $html .= '</nav>';
        }

        return '<div class="cms-collection">'.$html.'</div>';
    }

    public function text(array $payload, Market $market, string $locale, bool $includeWidgets = true): string
    {
        if (! $includeWidgets || ($payload['source'] ?? '') === '') {
            return '';
        }
        $page = max(1, min(10000, (int) request()->query($this->pageParameter($payload), 1)));
        $result = app(BlockSources::class)->read((string) $payload['source'], $market, $locale, (array) ($payload['filters'] ?? []), $page, (int) ($payload['per_page'] ?? 6));
        $reader = app(PublishedSectionTextService::class);
        $limit = max(1, (int) config('cms.blocks.max_render_bytes', 2097152));
        $readNodes = static function (array $nodes) use ($reader, $market, $locale, $limit): string {
            $text = '';
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $chunk = $reader->payloadText($node, $market, $locale, true)."\n";
                if (strlen($text) + strlen($chunk) > $limit) {
                    break;
                }
                $text .= $chunk;
            }

            return $text;
        };
        if ($result['items'] === []) {
            return $readNodes((array) ($payload['empty'] ?? []));
        }
        $text = '';
        foreach ($result['items'] as $item) {
            $chunk = app(BlockBindings::class)->withItem($item, fn (): string => $readNodes((array) ($payload['template'] ?? [])));
            if (strlen($text) + strlen($chunk) > $limit) {
                break;
            }
            $text .= $chunk;
        }

        return $text;
    }

    /** @param array<string,mixed> $payload */
    private function pageParameter(array $payload): string
    {
        $identity = isset($payload['id']) && $payload['id'] !== ''
            ? (string) $payload['id']
            : json_encode(array_diff_key($payload, ['_render_id' => true, 'type' => true]), JSON_THROW_ON_ERROR);

        return 'cms_collection_'.substr(hash('sha256', $identity), 0, 20);
    }
}
