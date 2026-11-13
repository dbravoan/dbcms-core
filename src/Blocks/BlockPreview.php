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
        $context = $this->catalog->describe($type === 'filter_page' ? 'page' : $type, (string) $input['locale'], (string) $input['market'], $type !== 'filter_page' && isset($input['entity_id']) ? (int) $input['entity_id'] : null, (string) $input['type'], 'summary', false)['context'];
        $market = Market::query()->whereKey((int) $context['market_id'])->firstOrFail();
        request()->attributes->set('cms.editorial_preview', true);
        app()->instance('current.market', $market);
        app()->setLocale((string) $input['locale']);
        $payload = array_replace((array) $input['payload'], ['type' => $input['type']]);
        app(BlockRegistryInterface::class)->validate((string) $input['type'], $payload);
        $this->catalog->validateTree($payload, $market, (string) $input['locale']);
        $selectedNodeId = (string) ($input['selected_node_id'] ?? '');
        $selectedColumnIndex = $input['selected_column_index'] ?? null;
        if ($selectedNodeId !== '' && (string) ($payload['id'] ?? '') !== $selectedNodeId && ! collect($this->tree->nodes($payload))->contains(static fn (array $node): bool => (string) ($node['id'] ?? '') === $selectedNodeId)) {
            throw ValidationException::withMessages(['selected_node_id' => 'El bloque seleccionado no pertenece a esta composición.']);
        }
        if ($selectedColumnIndex !== null && ! array_key_exists((int) $selectedColumnIndex, SectionLayout::columns($payload))) {
            throw ValidationException::withMessages(['selected_column_index' => 'La columna seleccionada no pertenece a esta composición.']);
        }
        $html = $this->renderer->renderSection(['type' => $input['type'], 'styling_json' => $payload], $market, (string) $input['locale']);

        return view('components.admin.block-preview', ['html' => $html, 'previewLocale' => $input['locale'], 'previewTheme' => app(ThemeManagerService::class)->getActiveTheme(), 'previewRootId' => (string) ($payload['id'] ?? ''), 'selectedNodeId' => $selectedNodeId, 'selectedColumnIndex' => $selectedColumnIndex])->render();
    }
}
