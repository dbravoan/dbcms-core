<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionLayout;
use DbCms\Core\Sections\SectionRendererService;
use DbCms\Core\Services\Capabilities\EntityReadService;
use DbCms\Core\Services\Cms\ThemeManagerService;
use Illuminate\Validation\ValidationException;

/** Ephemeral rendering never calls an entity writer or a publication workflow. */
final class BlockPreview
{
    public function __construct(private readonly BlockCatalogService $catalog, private readonly EntityReadService $entities, private readonly SectionRendererService $renderer, private readonly BlockTree $tree) {}

    /** @param array<string,mixed> $input */
    public function document(array $input, User $actor): string
    {
        $type = (string) $input['entity_type'];
        $permission = $type === 'filter_page' ? 'manage:seo' : ($this->entities->schemaFor($type)->permission ?? 'access:admin');
        abort_unless($actor->isAdmin() || $actor->hasPermission($permission), 403);
        if ($type === 'filter_page' && isset($input['entity_id'])) {
            FilterPage::query()->findOrFail((int) $input['entity_id']);
        }

        $sections = $this->sections($input);
        $contextType = $sections[0]['type'] === SectionLayout::CONTAINER_TYPE ? null : $sections[0]['type'];
        $context = $this->catalog->describe($type === 'filter_page' ? 'page' : $type, (string) $input['locale'], (string) $input['market'], $type !== 'filter_page' && isset($input['entity_id']) ? (int) $input['entity_id'] : null, $contextType, 'summary', false)['context'];
        $market = Market::query()->whereKey((int) $context['market_id'])->firstOrFail();
        request()->attributes->set('cms.editorial_preview', true);
        app()->instance('current.market', $market);
        app()->setLocale((string) $input['locale']);

        $registry = app(BlockRegistryInterface::class);
        $html = '';
        $rootId = '';
        foreach ($sections as $index => $section) {
            $payload = array_replace((array) $section['payload'], ['type' => $section['type']]);
            $registry->validate((string) $section['type'], $payload);
            $this->catalog->validateTree($payload, $market, (string) $input['locale']);
            if ($index === 0) {
                $rootId = (string) ($payload['id'] ?? '');
            }
            $html .= $this->renderer->renderSection(['type' => $section['type'], 'styling_json' => $payload], $market, (string) $input['locale']);
        }

        $selectedNodeId = (string) ($input['selected_node_id'] ?? '');
        $selectedColumnIndex = $input['selected_column_index'] ?? null;
        $this->assertSelection($sections, $selectedNodeId, $selectedColumnIndex);

        return view('components.admin.block-preview', [
            'html' => $html,
            'previewLocale' => $input['locale'],
            'previewTheme' => app(ThemeManagerService::class)->getActiveTheme(),
            'previewRootId' => $rootId,
            'selectedNodeId' => $selectedNodeId,
            'selectedColumnIndex' => $selectedColumnIndex,
        ])->render();
    }

    /**
     * @param  array<string,mixed>  $input
     * @return list<array{type:string,payload:array<string,mixed>}>
     */
    private function sections(array $input): array
    {
        if (isset($input['sections']) && is_array($input['sections'])) {
            $rows = [];
            foreach ($input['sections'] as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $rows[] = [
                    'type' => (string) ($section['type'] ?? 'rich_text'),
                    'payload' => (array) ($section['payload'] ?? []),
                ];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['sections' => 'La vista previa necesita al menos una sección.']);
            }

            return $rows;
        }

        return [[
            'type' => (string) ($input['type'] ?? 'rich_text'),
            'payload' => (array) ($input['payload'] ?? []),
        ]];
    }

    /**
     * @param  list<array{type:string,payload:array<string,mixed>}>  $sections
     */
    private function assertSelection(array $sections, string $selectedNodeId, mixed $selectedColumnIndex): void
    {
        if ($selectedNodeId !== '') {
            $found = false;
            foreach ($sections as $section) {
                $payload = array_replace($section['payload'], ['type' => $section['type']]);
                if ((string) ($payload['id'] ?? '') === $selectedNodeId
                    || collect($this->tree->nodes($payload))->contains(static fn (array $node): bool => (string) ($node['id'] ?? '') === $selectedNodeId)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw ValidationException::withMessages(['selected_node_id' => 'El bloque seleccionado no pertenece a esta composición.']);
            }
        }

        if ($selectedColumnIndex === null) {
            return;
        }

        $index = (int) $selectedColumnIndex;
        foreach ($sections as $section) {
            $payload = array_replace($section['payload'], ['type' => $section['type']]);
            if (array_key_exists($index, SectionLayout::columns($payload))) {
                return;
            }
        }

        throw ValidationException::withMessages(['selected_column_index' => 'La columna seleccionada no pertenece a esta composición.']);
    }
}
