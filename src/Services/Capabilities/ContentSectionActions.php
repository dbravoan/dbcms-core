<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Capabilities;

use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Sections\SectionLayout;
use DbCms\Core\Services\Content\EditorialDraftService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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

            foreach ($translations as $translation) {
                $item['translations'][(string) $translation->getAttribute('locale')]
                    = SectionLayout::normalize((array) $translation->getAttribute('content_json'));
            }

            $sections[] = $item;
        }

        return [
            'type' => (string) ($args['type'] ?? ''),
            'id' => $entity->getKey(),
            'sections' => $sections,
        ];
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
