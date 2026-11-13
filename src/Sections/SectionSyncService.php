<?php

declare(strict_types=1);

namespace DbCms\Core\Sections;

use DbCms\Core\Blocks\BlockBindings;
use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Blocks\BlockTree;
use DbCms\Core\Contracts\Sectionable;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function sync(Model $entity, string $sectionsJson, string $status = 'published', ?string $orderLocale = null): void
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
        app(BlockTree::class)->assertLimits($decoded);
        foreach ($decoded as $roots) {
            if (is_array($roots) && array_is_list($roots)) {
                app(BlockTree::class)->assertUniqueIds($roots);
            }
        }

        $status = in_array($status, ['draft', 'published'], true) ? $status : 'published';
        $languages = $this->activeLocales();

        DB::transaction(function () use ($entity, $decoded, $languages, $status, $orderLocale): void {
            $sectionModel = $entity->sectionModelClass();
            $translationModel = $entity->sectionTranslationModelClass();
            $entityFk = $entity->sectionForeignKey();
            $translationFk = Str::snake(class_basename($sectionModel)).'_id';

            // Hints never grant access to another entity's section or choose a new PK.
            $existing = $sectionModel::query()->where($entityFk, $entity->getKey())->lockForUpdate()->get()->keyBy('id');
            $entity->unsetRelation('sections');
            $storedPayload = app(SectionEditorService::class)->payload($entity);
            $plan = $this->plan($decoded, $languages, $orderLocale);
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
                    $this->validateReferences($entity, $content, $locale);
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

    /** @param array<string, mixed> $payload */
    private function validateReferences(Model $entity, array $payload, string $locale): void
    {
        $catalog = app(BlockCatalogService::class);
        $bindings = app(BlockBindings::class);
        $nodes = app(BlockTree::class)->nodes($payload);
        $needsBindings = $bindings->requiresContext($payload);
        $referenced = array_values(array_filter($nodes, fn (array $block): bool => $catalog->requiresReferenceContext((string) ($block['type'] ?? 'text'))));
        if (! $needsBindings && $referenced === []) {
            return;
        }
        $markets = Market::query()->where('is_active', true)
            ->whereHas('languages', static fn ($languages) => $languages->where('code', $locale)->where('is_active', true));
        if (method_exists($entity, 'markets')) {
            $relation = $entity->markets();
            if ($relation instanceof BelongsToMany && $relation->exists()) {
                $markets->whereIn('id', $relation->pluck('markets.id')->all());
            }
        }
        $destinations = $markets->get();
        if ($destinations->isEmpty()) {
            throw ValidationException::withMessages(['sections_json' => 'Dynamic blocks require an active market for their language.']);
        }
        foreach ($destinations as $market) {
            $catalog->validateTree($payload, $market, $locale);
        }
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
        $locales = Language::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('code')->pluck('code')->all();

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
    private function plan(array $decoded, array $languages, ?string $orderLocale): array
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

        if ($orderLocale !== null && (! in_array($orderLocale, $languages, true) || ($decoded !== [] && ! array_key_exists($orderLocale, $decoded)))) {
            throw ValidationException::withMessages(['sections_order_locale' => 'The ordering locale must be an active language included in the sections payload.']);
        }

        $groups = [];
        // Shared section order follows an explicit edited locale, otherwise the
        // active default language and a deterministic fallback; JSON key order
        // never decides the identity or visual order of translated sections.
        $locales = array_values(array_filter($languages, static fn (string $locale): bool => array_key_exists($locale, $decoded)));
        if ($orderLocale !== null && array_key_exists($orderLocale, $decoded)) {
            $locales = [$orderLocale, ...array_values(array_diff($locales, [$orderLocale]))];
        }
        foreach ($locales as $locale) {
            foreach ($decoded[$locale] as $index => $content) {
                $identity = $content['id'] ?? null;
                $key = (is_string($identity) && $identity !== '') || (is_int($identity) && $identity > 0)
                    ? 'identity:'.$identity : 'position:'.$index;
                $groups[$key] ??= ['master' => $content, 'contents' => []];
                $groups[$key]['contents'][$locale] = $content;
            }
        }
        $plan = array_values($groups);

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
}
