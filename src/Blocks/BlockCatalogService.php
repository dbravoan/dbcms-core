<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\BlockTypeInterface;
use DbCms\Core\Blocks\Contracts\ComposableBlockInterface;
use DbCms\Core\Blocks\Contracts\ContextualBlockReferencesInterface;
use DbCms\Core\Blocks\Contracts\ContextualBlockValidationInterface;
use DbCms\Core\Blocks\Contracts\SearchableBlockReferencesInterface;
use DbCms\Core\Blocks\Contracts\TranslatableBlockInterface;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Sections\SectionAppearance;
use DbCms\Core\Services\Capabilities\EntityReadService;
use Illuminate\Validation\ValidationException;

/** The registered blocks describe both internal assistants and external agents. */
final class BlockCatalogService
{
    public function __construct(private readonly BlockRegistryInterface $blocks, private readonly LocalizationServiceInterface $localization, private readonly EntityReadService $entities, private readonly ModuleManager $modules) {}

    /** @return array<string, mixed> */
    public function describe(string $entityType, string $locale, string $market, ?int $entityId = null, ?string $blockType = null, string $view = 'detail', bool $includeReferences = true): array
    {
        $schema = $this->entities->schemaFor($entityType);
        $owner = $schema->ownerModule ?? $this->modules->ownerOfClass($schema->model);
        if (! $schema->sectionable || ($owner !== null && ! $this->modules->isModuleEnabled($owner))) {
            throw ValidationException::withMessages(['entity_type' => 'Select an available entity type that supports sections.']);
        }
        if ($entityId !== null) {
            $entity = $this->entities->resolveEntity($entityType, $entityId);
            if ($entity instanceof SectionContentVisibility && ! $entity->usesSectionContent()) {
                throw ValidationException::withMessages(['entity_id' => 'This entity does not use section content.']);
            }
        }
        $destination = $this->localization->activeMarkets()->firstWhere('code', $market);
        if (! $destination instanceof Market) {
            throw ValidationException::withMessages(['market' => 'Select an active market.']);
        }

        if (! in_array($view, ['summary', 'detail'], true)) {
            throw ValidationException::withMessages(['view' => 'Selecciona summary o detail.']);
        }

        $result = ['context' => ['entity_type' => $entityType, 'entity_id' => $entityId, 'locale' => $locale, 'market' => $market, 'market_id' => $destination->id], 'blocks' => $this->catalog($destination, $locale, $blockType, $view, $includeReferences)];
        if ($view === 'detail') {
            $result['appearance'] = ['properties' => BlockStyle::properties(), 'scopes' => ['base', 'tablet', 'mobile', 'hover', 'focus', 'active', 'selected'], 'extent' => ['theme', 'viewport'], 'presentation_properties' => SectionAppearance::properties(), 'theme_variables' => BlockStyle::themeVariables()];
            if ($blockType === 'collection') {
                $result['data_sources'] = app(BlockSources::class)->describe();
            }
        }

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    public function catalog(Market $market, string $locale, ?string $blockType = null, string $view = 'detail', bool $includeReferences = true): array
    {
        $this->validateContext($market, $locale);
        $active = $this->blocks->getActiveBlocks();
        if ($blockType !== null && ! isset($active[$blockType])) {
            throw ValidationException::withMessages(['block_type' => 'This block is unavailable: '.$blockType]);
        }
        $catalog = [];
        foreach ($active as $type => $block) {
            if ($blockType !== null && $type !== $blockType) {
                continue;
            }
            if ($view === 'summary') {
                $catalog[$type] = ['type' => $type, 'name' => $block->getName(), 'description' => $block->getDescription(), 'category' => $block->getCategory(), 'is_full_width' => $block->isFullWidth(), 'composable' => $block instanceof ComposableBlockInterface];

                continue;
            }
            $catalog[$type] = [
                'type' => $type, 'name' => $block->getName(), 'description' => $block->getDescription(),
                'category' => $block->getCategory(), 'required_module' => $block->getRequiredModule(),
                'is_full_width' => $block->isFullWidth(), 'composable' => $block instanceof ComposableBlockInterface, 'defaults_are_examples' => true, 'default_payload' => $block->getDefaultPayload(),
                'schema' => $this->metadata($block->getEditorSchema()), 'validation_on_save' => true,
                'translation_paths' => $block instanceof TranslatableBlockInterface ? $block->getTranslationPaths() : [],
                'dynamic_data' => $includeReferences && $block instanceof ContextualBlockReferencesInterface ? $block->getContextualEditorData($market, $locale) : [],
            ];
        }

        return $catalog;
    }

    public function requiresReferenceContext(string $type): bool
    {
        if (! $this->blocks->isAvailable($type)) {
            return false;
        }
        $block = $this->blocks->get($type);

        return $block instanceof ContextualBlockReferencesInterface || $block instanceof ContextualBlockValidationInterface || ($block !== null && $this->hasReferenceFields($block->getEditorSchema()));
    }

    /** @param array<string, array<string, mixed>> $schema */
    private function hasReferenceFields(array $schema): bool
    {
        foreach ($schema as $field) {
            if (isset($field['options_source']) || $this->hasReferenceFields((array) ($field['fields'] ?? []))) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $payload */
    public function validateReferences(string $type, array $payload, Market $market, string $locale): void
    {
        $this->validateContext($market, $locale);
        $this->blocks->validate($type, $payload);
        $block = $this->blocks->get($type);
        if ($block === null) {
            throw ValidationException::withMessages(['block_type' => 'This block is unavailable: '.$type]);
        }
        $data = $block instanceof ContextualBlockReferencesInterface && ! $block instanceof SearchableBlockReferencesInterface ? $block->getContextualEditorData($market, $locale) : [];
        $this->validateFields($payload, $block->getEditorSchema(), $data, '', $block, $market, $locale);
        if ($block instanceof ContextualBlockValidationInterface) {
            $block->validatePayload($payload, $market, $locale);
        }
    }

    /** Strict supplied-input boundary, separate from defensive persisted public rendering.
     * @param array<string,mixed> $payload */
    public function validateTree(array $payload, Market $market, string $locale): void
    {
        $this->validateContext($market, $locale);
        app(BlockBindings::class)->validateTree($payload, $market, $locale);
        foreach (app(BlockTree::class)->nodes($payload) as $node) {
            $type = (string) ($node['type'] ?? 'text');
            if ($this->requiresReferenceContext($type)) {
                $this->validateReferences($type, $node, $market, $locale);
            }
        }
    }

    /** @return array<string,mixed> */
    public function references(string $entityType, string $locale, string $market, string $type, string $source, string $search = '', int $limit = 20, int $offset = 0, ?int $entityId = null): array
    {
        $context = $this->describe($entityType, $locale, $market, $entityId, $type, 'summary', false)['context'];
        $block = $this->blocks->get($type);
        if (! $block instanceof SearchableBlockReferencesInterface) {
            throw ValidationException::withMessages(['block_type' => 'El bloque no ofrece búsqueda de referencias.']);
        }
        if ($limit < 1 || $limit > 50 || $offset < 0 || mb_strlen($search) > 200) {
            throw ValidationException::withMessages(['limit' => 'Usa entre 1 y 50 referencias y una búsqueda acotada.']);
        }
        $destination = $this->localization->activeMarkets()->firstWhere('code', $market);
        assert($destination instanceof Market);
        $result = $block->searchReferences($destination, $locale, $source, $search, $limit, $offset);

        return ['context' => $context, 'source' => $source, 'items' => $result['items'], 'next_offset' => $result['has_more'] ? $offset + $limit : null];
    }

    /** Only declarative metadata crosses the agent boundary; validators stay in PHP.
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function metadata(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->metadata($value);
            } elseif (is_scalar($value) || $value === null) {
                $result[$key] = $value;
            }
        }

        return array_is_list($data) ? array_values($result) : $result;
    }

    private function validateContext(Market $market, string $locale): void
    {
        if (! $market->is_active || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['locale' => 'Select an active language belonging to the selected market.']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<string, mixed>>  $schema
     * @param  array<string, list<array<string, mixed>>>  $data
     */
    private function validateFields(array $payload, array $schema, array $data, string $prefix = '', ?BlockTypeInterface $block = null, ?Market $market = null, ?string $locale = null): void
    {
        foreach ($schema as $key => $field) {
            $value = $payload[$key] ?? null;
            if (isset($field['options_source']) && $value !== null && $value !== '') {
                if ($block instanceof SearchableBlockReferencesInterface && $market !== null) {
                    if ((! is_string($value) && ! is_int($value)) || ! $block->referenceExists($market, (string) $locale, (string) $field['options_source'], (string) ($field['option_value'] ?? 'id'), $value)) {
                        throw ValidationException::withMessages([$prefix.$key => 'Selecciona una referencia pública del idioma y mercado actuales.']);
                    }

                    continue;
                }
                $values = array_column($data[(string) $field['options_source']] ?? [], (string) ($field['option_value'] ?? 'id'));
                if (! is_scalar($value) || ! in_array((string) $value, array_map('strval', $values), true)) {
                    throw ValidationException::withMessages([$prefix.$key => 'Select a public reference available in the selected language and market.']);
                }
            }
            if (($field['type'] ?? null) === 'object' && is_array($value)) {
                $this->validateFields($value, (array) ($field['fields'] ?? []), $data, $prefix.$key.'.', $block, $market, $locale);
            }
            if (($field['type'] ?? null) === 'repeater' && is_array($value)) {
                foreach ($value as $index => $row) {
                    if (is_array($row)) {
                        $this->validateFields($row, (array) ($field['fields'] ?? []), $data, $prefix.$key.'.'.$index.'.', $block, $market, $locale);
                    }
                }
            }
        }
    }
}
