<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Models\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for persisting the block manager payload into the
 * relational section tables. Replaces the logic previously duplicated across
 * the Pages, Blog and Shop controllers/services.
 *
 * Accepts the locale-keyed payload (`{es: [...], en: [...]}`) emitted by the editor.
 */
class SectionSyncService
{
    public function __construct(private readonly UnavailableBlockGuard $availability) {}

    /** Promote the current, reviewed section version without recreating its rows. */
    public function publish(Model $entity): void
    {
        if (! $entity instanceof Sectionable || ($entity instanceof SectionContentVisibility && ! $entity->usesSectionContent())) {
            return;
        }
        $attributes = $entity->getAttributes();
        if (! (bool) ($attributes['is_published'] ?? $attributes['is_active'] ?? false)) {
            return;
        }
        $sectionModel = $entity->sectionModelClass();
        $sectionModel::query()->where($entity->sectionForeignKey(), $entity->getKey())
            ->where('status', 'draft')->update(['status' => 'published', 'published_at' => now()]);
        $entity->unsetRelation('sections');
        $entity->unsetRelation('publishedSections');
    }

    public function sync(Model $entity, string $sectionsJson, string $status = 'published'): void
    {
        if ($entity instanceof SectionContentVisibility && ! $entity->usesSectionContent()) {
            throw ValidationException::withMessages(['sections_json' => 'This page uses a Blade view; its preserved sections cannot be edited.']);
        }
        if (! $entity instanceof Sectionable) {
            return;
        }

        $decoded = json_decode($sectionsJson, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['sections_json' => 'Sections must be a locale-keyed object.']);
        }

        $status = in_array($status, ['draft', 'published'], true) ? $status : 'published';
        $languages = $this->activeLocales();

        DB::transaction(function () use ($entity, $decoded, $languages, $status): void {
            $sectionModel = $entity->sectionModelClass();
            $translationModel = $entity->sectionTranslationModelClass();
            $entityFk = $entity->sectionForeignKey();
            $translationFk = Str::snake(class_basename($sectionModel)).'_id';

            // Hints never grant access to another entity's section or choose a new PK.
            $existing = $sectionModel::query()->where($entityFk, $entity->getKey())->lockForUpdate()->get()->keyBy('id');
            $entity->unsetRelation('sections');
            $storedPayload = app(SectionEditorService::class)->payload($entity);
            $plan = $this->plan($decoded, $languages);
            $plannedIds = [];
            foreach ($plan as &$item) {
                $id = $this->identityHint($item['master']['id'] ?? null);
                $plannedIds[] = $id;
                foreach ($item['contents'] as $locale => &$content) {
                    $stored = [];
                    foreach ($storedPayload[$locale] ?? [] as $candidate) {
                        if ($id !== null && (int) ($candidate['id'] ?? 0) === $id) {
                            $stored = $candidate;
                            break;
                        }
                    }
                    $content = $this->availability->reconcile(SectionLayout::normalize($content), $stored);
                }
                unset($content);
                foreach ($storedPayload as $locale => $sections) {
                    if (array_key_exists($locale, $item['contents'])) {
                        continue;
                    }
                    foreach ($sections as $candidate) {
                        if ($id !== null && (int) ($candidate['id'] ?? 0) === $id && $this->availability->containsUnavailable($candidate)) {
                            $item['contents'][$locale] = $candidate;
                        }
                    }
                }
                $item['master'] = reset($item['contents']) ?: $item['master'];
            }
            unset($item);
            foreach ($existing as $oldSection) {
                if (in_array((int) $oldSection->getKey(), $plannedIds, true)) {
                    continue;
                }
                $contents = [];
                foreach ($storedPayload as $locale => $sections) {
                    foreach ($sections as $candidate) {
                        if ((int) ($candidate['id'] ?? 0) === (int) $oldSection->getKey()) {
                            $contents[$locale] = $candidate;
                        }
                    }
                }
                if (array_filter($contents, fn (array $payload): bool => $this->availability->containsUnavailable($payload)) !== []) {
                    $plan[] = ['master' => $contents[array_key_first($contents)], 'contents' => $contents];
                }
            }
            $retained = [];
            $marketAware = in_array('market_id', (new $translationModel)->getFillable(), true);

            foreach ($plan as $order => $item) {
                // Canonical shape on write: the section content moves into its
                // first column and the block grid size lives in `grid_columns`.
                $master = SectionLayout::normalize($item['master']);

                $hint = $this->identityHint($master['id'] ?? null);
                unset($master['id']);
                $section = $hint === null ? null : $existing->get($hint);
                if ($section !== null && in_array((int) $section->getKey(), $retained, true)) {
                    throw ValidationException::withMessages(['sections_json' => 'A section identity cannot appear more than once.']);
                }
                $section ??= new $sectionModel;
                $section->fill([
                    $entityFk => $entity->getKey(),
                    'type' => $master['type'] ?? 'text',
                    'order' => $order + 1,
                    'is_active' => true,
                    'styling_json' => $master,
                    'status' => $status,
                    'published_at' => $status === 'published' ? now() : null,
                ])->save();
                $retained[] = (int) $section->getKey();

                $removedTranslations = $translationModel::query()->where($translationFk, $section->getKey())
                    ->whereIn('locale', $languages)->whereNotIn('locale', array_keys($item['contents']));
                if ($marketAware) {
                    $removedTranslations->whereNull('market_id');
                }
                $removedTranslations->delete();

                foreach ($item['contents'] as $locale => $content) {
                    if (is_array($content)) {
                        $content = SectionLayout::normalize($content);
                        unset($content['id']);
                    }

                    $identity = [$translationFk => $section->getKey(), 'locale' => $locale];
                    if ($marketAware) {
                        $identity['market_id'] = null;
                    }
                    $translationModel::query()->updateOrCreate($identity, ['content_json' => $content]);
                }
            }
            $sectionModel::query()->where($entityFk, $entity->getKey())->whereNotIn('id', $retained)->delete();
            $entity->unsetRelation('sections');
            $entity->unsetRelation('publishedSections');
        });
    }

    /** Only persisted numeric IDs are identity hints. */
    private function identityHint(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (! is_string($value) || ! preg_match('/^([1-9][0-9]*)$/D', $value, $matches)) {
            return null;
        }
        $id = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($id) ? $id : null;
    }

    /**
     * @return list<string>
     */
    private function activeLocales(): array
    {
        $locales = Language::query()->where('is_active', true)->pluck('code')->all();

        if ($locales === []) {
            return ['es'];
        }

        return array_values(array_map('strval', $locales));
    }

    /**
     * Normalize the payload into an ordered list of {master, contents} items.
     *
     * @param  array<mixed>  $decoded
     * @param  list<string>  $languages
     * @return list<array{master: array<string, mixed>, contents: array<string, array<string, mixed>>}>
     */
    private function plan(array $decoded, array $languages): array
    {
        if ($decoded !== [] && array_is_list($decoded)) {
            throw ValidationException::withMessages(['sections_json' => 'Sections must be keyed by locale.']);
        }
        foreach ($decoded as $locale => $sections) {
            if (! in_array($locale, $languages, true) || ! is_array($sections) || ! array_is_list($sections)) {
                throw ValidationException::withMessages(['sections_json' => 'Sections require an active locale and a list of blocks.']);
            }
            foreach ($sections as $section) {
                $this->validateBlockStructure($section);
            }
        }

        $plan = [];

        $max = 0;
        foreach ($decoded as $list) {
            $max = max($max, count($list));
        }

        for ($index = 0; $index < $max; $index++) {
            $master = $this->firstBlockAt($decoded, $index);
            if ($master === null) {
                continue;
            }

            $contents = [];
            foreach ($languages as $locale) {
                $content = $this->blockAt($decoded[$locale] ?? null, $index);
                if ($content !== null) {
                    $contents[$locale] = $content;
                }
            }

            $plan[] = ['master' => $master, 'contents' => $contents];
        }

        return $plan;
    }

    /** Reject malformed writes before the read normalizer can discard their nodes. */
    private function validateBlockStructure(mixed $block): void
    {
        if (! is_array($block) || $block === [] || array_is_list($block)) {
            throw ValidationException::withMessages(['sections_json' => 'Every section and nested block must be a block object.']);
        }
        if (! array_key_exists('columns', $block)) {
            return;
        }
        if (! is_array($block['columns']) || ! array_is_list($block['columns'])) {
            throw ValidationException::withMessages(['sections_json' => 'Columns must be a list of column objects.']);
        }
        foreach ($block['columns'] as $column) {
            if (! is_array($column) || $column === [] || array_is_list($column)) {
                throw ValidationException::withMessages(['sections_json' => 'Every column must be a column object.']);
            }
            $children = array_key_exists('blocks', $column) ? $column['blocks'] : [];
            if (! is_array($children) || ! array_is_list($children)) {
                throw ValidationException::withMessages(['sections_json' => 'Column blocks must be a list of block objects.']);
            }
            foreach ($children as $child) {
                $this->validateBlockStructure($child);
            }
        }
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<string, mixed>|null
     */
    private function firstBlockAt(array $decoded, int $index): ?array
    {
        $preferred = $this->blockAt($decoded['es'] ?? null, $index);
        if ($preferred !== null) {
            return $preferred;
        }

        foreach ($decoded as $list) {
            $block = $this->blockAt($list, $index);
            if ($block !== null) {
                return $block;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blockAt(mixed $list, int $index): ?array
    {
        if (! is_array($list) || ! isset($list[$index]) || ! is_array($list[$index])) {
            return null;
        }

        /** @var array<string, mixed> $block */
        $block = $list[$index];

        return $block;
    }
}
