<?php

declare(strict_types=1);

namespace DbCms\Core\Capabilities\Tools;

use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Blocks\Components\Services\PublicComponentReader;
use DbCms\Core\Sections\UnavailableBlockGuard;
use DbCms\Core\Services\Capabilities\EntityReadService;
use DbCms\Core\Services\Content\PublicTranslation;
use Illuminate\Validation\ValidationException;

final class BlockTemplatesTool extends AbstractCapabilityTool
{
    public function __construct(
        private readonly BlockCatalogService $catalog,
        private readonly EntityReadService $entities,
        private readonly PublicComponentReader $templates,
        private readonly UnavailableBlockGuard $availability,
    ) {}

    public function name(): string
    {
        return 'cms_block_templates';
    }

    public function description(): string
    {
        return 'Lists current published builder templates for the target language and market. Request template_id to read copyable sections. Use fresh IDs when copying sections or blocks into an existing composition.';
    }

    public function properties(): array
    {
        return [
            'entity_type' => ['type' => 'string', 'description' => 'Authorized destination entity type'],
            'entity_id' => ['type' => 'integer', 'minimum' => 1],
            'locale' => ['type' => 'string'],
            'market' => ['type' => 'string'],
            'template_id' => ['type' => 'integer', 'minimum' => 1],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            'offset' => ['type' => 'integer', 'minimum' => 0],
        ];
    }

    public function required(): array
    {
        return ['entity_type', 'locale', 'market'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function permissionFor(array $args): ?string
    {
        return $this->safePermission(fn (): ?string => $this->entities->schemaFor($this->string($args, 'entity_type'))->permission);
    }

    public function ability(): string
    {
        return self::ABILITY_READ;
    }

    public function handle(array $args): array
    {
        $locale = $this->string($args, 'locale');
        $market = $this->catalog->destination($this->string($args, 'entity_type'), $locale, $this->string($args, 'market'), isset($args['entity_id']) ? (int) $args['entity_id'] : null);
        $id = isset($args['template_id']) ? (int) $args['template_id'] : null;
        $limit = $id === null ? max(1, min(50, (int) ($args['limit'] ?? 20))) : 1;
        $offset = $id === null ? max(0, (int) ($args['offset'] ?? 0)) : 0;
        $query = $this->templates->query($market, $locale)->with('publishedSections.translations');
        if ($id !== null) {
            $query->whereKey($id);
        }
        $rows = $query->orderBy('id')->offset($offset)->limit($limit + 1)->get();
        $items = [];
        foreach ($rows->take($limit) as $template) {
            $template->setRelation('sections', $template->publishedSections);
            $sections = $this->templates->sections($template, $market, $locale);
            if ($sections === [] || array_filter($sections, $this->availability->containsUnavailable(...)) !== []) {
                continue;
            }
            $translation = PublicTranslation::resolve($template, $locale, $market);
            $item = ['id' => $template->id, 'title' => $translation?->getAttribute('title'), 'section_count' => count($sections)];
            if ($id !== null) {
                $item['sections_json'] = [$locale => $sections];
            }
            $items[] = $item;
        }
        if ($id !== null && $items === []) {
            throw ValidationException::withMessages(['template_id' => 'Select a published template available in this language and market.']);
        }

        return ['items' => $items, 'next_offset' => $rows->count() > $limit ? $offset + $limit : null];
    }
}
