<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Blocks\BlockPatch;
use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Sections\SectionEditorService;
use DbCms\Core\Sections\SectionLayout;
use DbCms\Core\Services\Content\EditorialDraftService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ContentSectionActions
{
    public function __construct(private readonly EntityMutationService $mutations, private readonly EntityReadService $entities, private readonly EditorialDraftService $drafts) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function sectionsGet(array $args): array
    {
        $entity = $this->drafts->forEditor($this->sectionable($args));

        if (! $entity instanceof Sectionable || ($entity instanceof SectionContentVisibility && ! $entity->usesSectionContent())) {
            throw ValidationException::withMessages([
                'type' => "Entity [{$args['type']}] does not use the block manager.",
            ]);
        }

        $sections = [];
        $revision = [];
        $entity->loadMissing('sections.translations');
        /** @var Collection<int, Model> $rows */
        $rows = $entity->getRelation('sections');
        $rows = $rows->sortBy('order');

        foreach ($rows as $section) {
            $item = [
                'id' => (int) $section->getKey(),
                'type' => (string) $section->getAttribute('type'),
                'order' => (int) $section->getAttribute('order'),
                'status' => (string) $section->getAttribute('status'),
                // Canonical contract: `columns` (one entry per column).
                'styling' => SectionLayout::normalize((array) $section->getAttribute('styling_json')),
                'translations' => [],
            ];

            /** @var Collection<int, Model> $translations */
            $translations = $section->getRelation('translations');

            $records = [];
            foreach ($translations->sortBy(static fn (Model $row): string => (string) $row->getAttribute('locale').':'.str_pad((string) ($row->getAttribute('market_id') ?? 0), 12, '0', STR_PAD_LEFT)) as $translation) {
                $locale = (string) $translation->getAttribute('locale');
                $marketId = $translation->getAttribute('market_id');
                $content = SectionLayout::normalize((array) $translation->getAttribute('content_json'));
                $records[] = ['locale' => $locale, 'market_id' => $marketId, 'content' => $content];
                if ($marketId === null) {
                    $item['translations'][$locale] = $content;
                } else {
                    $item['market_translations'][$locale][(string) $marketId] = $content;
                }
            }
            $revision[] = array_replace($item, ['translations' => $records]);

            $sections[] = $item;
        }

        $result = [
            'type' => (string) ($args['type'] ?? ''),
            'id' => $entity->getKey(),
            'sections' => $sections,
            'version' => hash('sha256', json_encode($revision, JSON_THROW_ON_ERROR)),
        ];
        if (($args['view'] ?? '') === 'summary' || isset($args['node_id'])) {
            $payload = app(SectionEditorService::class)->payload($entity);
            $selected = [];
            foreach ($payload as $locale => $roots) {
                if (isset($args['locale']) && $args['locale'] !== $locale) {
                    continue;
                }
                foreach ($roots as $root) {
                    foreach (app(BlockTree::class)->nodes($root) as $node) {
                        if (isset($args['node_id'])) {
                            if ((string) ($node['id'] ?? '') === (string) $args['node_id']) {
                                $selected[$locale][] = $node;
                            }
                        } else {
                            $selected[$locale][] = ['id' => $node['id'] ?? null, 'type' => $node['type'] ?? 'text'];
                        }
                    }
                }
            }
            unset($result['sections']);
            $result['nodes'] = $selected;
        }

        return $result;
    }

    /** @param array<string,mixed> $args
     * @return array<string,mixed> */
    public function sectionsPatch(array $args): array
    {
        return DB::transaction(function () use ($args): array {
            $entity = $this->sectionable($args);
            $entity->newQuery()->whereKey($entity->getKey())->lockForUpdate()->firstOrFail();
            $current = $this->sectionsGet($args);
            if (! is_string($args['version'] ?? null) || ! hash_equals($current['version'], $args['version'])) {
                throw ValidationException::withMessages(['version' => 'El contenido ha cambiado. Consulta la versión actual antes de editar.']);
            }
            $locale = (string) ($args['locale'] ?? '');
            $draft = $this->drafts->forEditor($entity);
            $payload = app(SectionEditorService::class)->payload($draft);
            if (! array_key_exists($locale, $payload) || ! is_array($args['operations'] ?? null) || ! array_is_list($args['operations'])) {
                throw ValidationException::withMessages(['locale' => 'Selecciona un idioma activo y operaciones válidas.']);
            }
            $operations = [];
            foreach ($args['operations'] as $operation) {
                if (! is_array($operation)) {
                    throw ValidationException::withMessages(['operations' => 'Cada operación debe ser un objeto.']);
                }
                $operations[] = $operation;
            }
            $payload[$locale] = app(BlockPatch::class)->apply($payload[$locale], $operations);
            // Sections have one shared order; the edited locale selects it.
            $payload = [$locale => $payload[$locale]] + $payload;
            $result = $this->mutations->update((string) $args['type'], $entity->getKey(), ['sections_json' => $payload, 'sections_order_locale' => $locale]);
            $updated = $this->sectionsGet($args);

            return ['type' => $args['type'], 'id' => $entity->getKey(), 'version' => $updated['version'], 'changed' => count($args['operations'])] + array_intersect_key($result, array_flip(['status', 'live_preserved', 'pending_revision_id']));
        });
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function sectionsSync(array $args): array
    {
        $entity = $this->sectionable($args);

        $payload = $args['sections'] ?? $args['sections_json'] ?? null;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
        } else {
            $decoded = is_array($payload) ? $payload : null;
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'sections' => 'Send locale-keyed lists of blocks or their JSON representation.',
            ]);
        }

        if (! $entity instanceof Sectionable) {
            throw ValidationException::withMessages(['type' => 'This entity does not use the block manager.']);
        }
        $result = $this->mutations->update((string) ($args['type'] ?? ''), $entity->getKey(), [
            'sections_json' => $decoded,
            'sections_order_locale' => $args['order_locale'] ?? null,
        ]);

        return $this->sectionsGet(['type' => (string) ($args['type'] ?? ''), 'id' => $entity->getKey()])
            + array_intersect_key($result, array_flip(['status', 'live_preserved', 'pending_revision_id']));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function sectionable(array $args): Model
    {
        return $this->entities->resolveEntity((string) ($args['type'] ?? ''), $args['id'] ?? 0);
    }
}
