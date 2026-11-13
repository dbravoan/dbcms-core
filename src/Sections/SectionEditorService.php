<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Reads an entity's block-manager sections as a locale-keyed payload.
 *
 * The payload feeds the backoffice block manager (and the content-audit
 * snapshot). Each block carries its real `id` so the front office can deep-link
 * to a specific section (`?section=<id>`) and the editor can open it again even
 * after the blocks are reordered.
 */
class SectionEditorService
{
    /**
     * Build the locale-keyed block payload consumed by the block manager.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function payload(Model $entity): array
    {
        $locales = $this->activeLocales();

        $payload = [];
        foreach ($locales as $locale) {
            $payload[$locale] = [];
        }

        if (! $entity instanceof Sectionable || ($entity instanceof SectionContentVisibility && ! $entity->usesSectionContent())) {
            return $payload;
        }

        $sectionModel = $entity->sectionModelClass();
        $loaded = $entity->relationLoaded('sections') ? $entity->getRelation('sections') : null;
        $sections = $loaded instanceof Collection ? $loaded->sortBy('order')->values() : $sectionModel::query()
            ->where($entity->sectionForeignKey(), $entity->getKey())
            ->with('translations')
            ->orderBy('order')
            ->get();

        foreach ($sections as $section) {
            if (! $section instanceof Model) {
                continue;
            }

            $base = SectionLayout::normalize((array) ($section->getAttribute('styling_json') ?? []));
            $base['id'] = (int) $section->getKey();
            $base['type'] = (string) ($section->getAttribute('type') ?? 'text');

            $section->loadMissing('translations');
            $translations = $section->getRelation('translations');

            foreach ($locales as $locale) {
                $content = $this->contentFor($translations, $locale);
                if ($content === null) {
                    continue;
                }
                // Canonical, locale-resolved payload: every consumer (editor,
                // audit snapshot, MCP, admin bar) sees the same shape.
                $payload[$locale][] = SectionLayout::resolve($base, $content);
            }
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function activeLocales(): array
    {
        $locales = Language::query()->where('is_active', true)->orderBy('id')->pluck('code')->all();

        if ($locales === []) {
            return ['es'];
        }

        return array_values(array_map('strval', $locales));
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFor(mixed $translations, string $locale): ?array
    {
        if (! $translations instanceof Collection) {
            return null;
        }

        foreach ($translations as $translation) {
            if (! $translation instanceof Model) {
                continue;
            }

            if ((string) $translation->getAttribute('locale') === $locale && $translation->getAttribute('market_id') === null) {
                return (array) ($translation->getAttribute('content_json') ?? []);
            }
        }

        return null;
    }
}
