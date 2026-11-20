<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\DTOs\Content\EntityMutationData;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use DbCms\Core\Sections\SectionEditorService;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Content\EditorialDraftService;

final class ComponentAdministration
{
    public function __construct(private readonly EntityMutationService $mutations, private readonly SectionEditorService $sections, private readonly EditorialDraftService $drafts, private readonly ComponentProperties $properties) {}

    /** @param array<string,mixed> $input */
    public function create(array $input, User $actor): void
    {
        $this->mutations->create('block_component', EntityMutationData::fromForm($input)->arguments, $actor);
    }

    /** @param array<string,mixed> $input */
    public function update(BlockComponent $component, array $input): void
    {
        $args = EntityMutationData::fromForm($input)->arguments;
        if ($component->is_published && ! empty($args['attributes']['is_published'])) {
            unset($args['attributes']['is_published'], $args['attributes']['published_at']);
            if (array_key_exists('is_active', $args['attributes']) && (bool) $args['attributes']['is_active'] === $component->is_active) {
                unset($args['attributes']['is_active']);
            }
        }
        $this->mutations->update('block_component', $component->id, $args);
    }

    public function delete(BlockComponent $component): void
    {
        $this->mutations->delete('block_component', $component->id);
    }

    /** @return array<string,mixed> */
    public function formData(?BlockComponent $component = null): array
    {
        if ($component !== null) {
            $component->load(['translations', 'sections.translations', 'markets']);
            $draft = $this->drafts->forEditor($component);
            assert($draft instanceof BlockComponent);
            $component = $draft;
        }
        $sections = $component === null ? [] : $this->sections->payload($component);
        $targets = [];
        foreach ($sections as $rows) {
            $targets += $this->properties->targets($rows);
        }
        $properties = [];
        $exposed = ($component === null ? [] : $component->exposed_properties) ?? [];
        $oldExposed = request()->old('exposed_properties');
        if (is_string($oldExposed) && is_array(json_decode($oldExposed, true))) {
            $exposed = json_decode($oldExposed, true);
        }
        foreach ($exposed as $name => $property) {
            if (! is_array($property)) {
                continue;
            }
            $property['node_id'] = is_string($property['node_id'] ?? null) ? $property['node_id'] : '';
            $property['field'] = is_string($property['field'] ?? null) ? $property['field'] : '';
            $property['label'] = is_string($property['label'] ?? null) ? $property['label'] : (string) $name;
            $properties[] = ['name' => $name, 'label' => $property['label'], 'target' => $property['node_id'].'::'.$property['field'], 'node_id' => $property['node_id'], 'field' => $property['field']];
        }

        return ['blockComponent' => $component, 'sectionsData' => $sections, 'propertyTargets' => $targets, 'componentProperties' => $properties,
            'languages' => Language::where('is_active', true)->get(), 'markets' => Market::with('languages')->where('is_active', true)->get()];
    }
}
