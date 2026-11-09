<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Blocks\Contracts\ContextualBlockReferencesInterface;
use DbCms\Core\Blocks\Contracts\TranslatableBlockInterface;
use DbCms\Core\Contracts\SectionContentVisibility;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Capabilities\EntityReadService;
use Illuminate\Validation\ValidationException;

/** The registered blocks describe both internal assistants and external agents. */
final class BlockCatalogService
{
    public function __construct(private readonly BlockRegistryInterface $blocks, private readonly LocalizationServiceInterface $localization, private readonly EntityReadService $entities, private readonly ModuleManager $modules) {}

    /** @return array<string, mixed> */
    public function describe(string $entityType, string $locale, string $market, ?int $entityId = null, ?string $blockType = null): array
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

        return ['context' => ['entity_type' => $entityType, 'entity_id' => $entityId, 'locale' => $locale, 'market' => $market, 'market_id' => $destination->id], 'blocks' => $this->catalog($destination, $locale, $blockType)];
    }

    /** @return array<string, array<string, mixed>> */
    public function catalog(Market $market, string $locale, ?string $blockType = null): array
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
            $catalog[$type] = [
                'type' => $type, 'name' => $block->getName(), 'description' => $block->getDescription(),
                'category' => $block->getCategory(), 'required_module' => $block->getRequiredModule(),
                'is_full_width' => $block->isFullWidth(), 'defaults_are_examples' => true, 'default_payload' => $block->getDefaultPayload(),
                'schema' => $this->metadata($block->getEditorSchema()), 'validation_on_save' => true,
                'translation_paths' => $block instanceof TranslatableBlockInterface ? $block->getTranslationPaths() : [],
                'dynamic_data' => $block instanceof ContextualBlockReferencesInterface ? $block->getContextualEditorData($market, $locale) : [],
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

        return $block instanceof ContextualBlockReferencesInterface || ($block !== null && $this->hasReferenceFields($block->getEditorSchema()));
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
        $data = $block instanceof ContextualBlockReferencesInterface ? $block->getContextualEditorData($market, $locale) : [];
        $this->validateFields($payload, $block->getEditorSchema(), $data);
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
    private function validateFields(array $payload, array $schema, array $data, string $prefix = ''): void
    {
        foreach ($schema as $key => $field) {
            $value = $payload[$key] ?? null;
            if (isset($field['options_source']) && $value !== null && $value !== '') {
                $values = array_column($data[(string) $field['options_source']] ?? [], (string) ($field['option_value'] ?? 'id'));
                if (! is_scalar($value) || ! in_array((string) $value, array_map('strval', $values), true)) {
                    throw ValidationException::withMessages([$prefix.$key => 'Select a public reference available in the selected language and market.']);
                }
            }
            if (($field['type'] ?? null) === 'repeater' && is_array($value)) {
                foreach ($value as $index => $row) {
                    if (is_array($row)) {
                        $this->validateFields($row, (array) ($field['fields'] ?? []), $data, $prefix.$key.'.'.$index.'.');
                    }
                }
            }
        }
    }
}
