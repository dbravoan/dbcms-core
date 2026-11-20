<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\BlockComponentSection;
use DbCms\Core\Search\Contracts\BlockReferenceSearchSourceInterface;
use DbCms\Core\Search\Contracts\SearchSourceRegistryInterface;
use DbCms\Core\Search\SearchIndexer;
use DbCms\Core\Sections\SectionLayout;
use DbCms\Core\Services\Capabilities\ContentSnapshotService;
use Illuminate\Support\Facades\DB;

/** Reprojects only public consumers of a changed definition, including parent components. */
final class ComponentSearchInvalidator
{
    /** @var array<int,true> */
    private array $pending = [];

    /** @var array<int,string> */
    private array $completed = [];

    public function __construct(private readonly PublicContentDiscoveryInterface $public, private readonly SearchSourceRegistryInterface $sources, private readonly BlockTree $tree, private readonly SearchIndexer $indexer, private readonly ComponentSearchBoundary $boundary) {}

    public function queue(int $id): void
    {
        if ($id < 1) {
            return;
        }
        $this->pending[$id] = true;
        // Register each transaction's callback even after a rolled-back earlier write.
        // Duplicate callbacks in the same commit become no-ops after the first one.
        $flush = function () use ($id): void {
            if (! isset($this->pending[$id])) {
                return;
            }
            unset($this->pending[$id]);
            $this->indexer->indexEntity('block_component', $id);
        };
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($flush);
        } else {
            $flush();
        }
    }

    public function refresh(int $id): void
    {
        $component = BlockComponent::query()->find($id);
        $this->boundary->remember($id, $component);
        $signature = $component === null ? 'missing' : hash('sha256', json_encode([
            'snapshot' => app(ContentSnapshotService::class)->capture($component),
            'available' => $component->is_active && $component->is_published && $component->published_at?->lessThanOrEqualTo(now()),
        ], JSON_THROW_ON_ERROR));
        // A caller may roll back projections and deadlines after this method returns.
        // Only committed, after-commit refreshes can safely use the scoped memo.
        $memoize = DB::transactionLevel() === 0;
        if ($memoize && ($this->completed[$id] ?? null) === $signature) {
            return;
        }
        $affected = $this->ancestors($id);
        $consumers = [];
        // The available source retains ownership of current and scheduled projections.
        foreach ($this->public->schemas() as $schema) {
            if (! $schema->sectionable) {
                continue;
            }
            $source = $this->sources->get($schema->type);
            if (! $source instanceof BlockReferenceSearchSourceInterface) {
                continue;
            }
            foreach ($source->referencingEntities('component', 'component_id', array_keys($affected)) as $consumerId) {
                $consumers[$schema->type.':'.$consumerId] = ['type' => $schema->type, 'id' => $consumerId];
            }
        }
        $this->indexer->batch(function () use ($consumers): void {
            foreach ($consumers as $consumer) {
                $this->indexer->indexEntity($consumer['type'], $consumer['id']);
            }
        });
        if ($memoize) {
            $this->completed[$id] = $signature;
        }
    }

    /** @return array<int,true> */
    private function ancestors(int $id): array
    {
        $parents = [];
        foreach (BlockComponentSection::query()->where('is_active', true)->where('status', 'published')->with('translations')->get() as $section) {
            $owner = (int) $section->getAttribute('block_component_id');
            foreach ($section->translations as $translation) {
                $payload = SectionLayout::resolve(array_replace(['type' => $section->getAttribute('type')], (array) $section->getAttribute('styling_json')), (array) $translation->getAttribute('content_json'));
                foreach ($this->tree->nodes($payload) as $node) {
                    if (($node['type'] ?? '') === 'component' && (int) ($node['component_id'] ?? 0) > 0) {
                        $parents[(int) $node['component_id']][$owner] = true;
                    }
                }
            }
        }
        $affected = [$id => true];
        $queue = [$id];
        while ($queue !== []) {
            $child = array_pop($queue);
            foreach (array_keys($parents[$child] ?? []) as $parent) {
                if (! isset($affected[$parent])) {
                    $affected[$parent] = true;
                    $queue[] = $parent;
                }
            }
        }

        return $affected;
    }
}
