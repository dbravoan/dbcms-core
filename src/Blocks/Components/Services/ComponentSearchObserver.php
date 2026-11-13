<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\BlockComponentSection;
use DbCms\Core\Models\BlockComponentSectionTranslation;
use DbCms\Core\Models\BlockComponentTranslation;
use Illuminate\Database\Eloquent\Model;

final class ComponentSearchObserver
{
    public function __construct(private readonly ComponentSearchInvalidator $invalidation) {}

    public function saved(Model $model): void
    {
        $attributes = $model->getAttributes();
        $id = match (true) {
            $model instanceof BlockComponent => $model->getKey(),
            $model instanceof BlockComponentSection, $model instanceof BlockComponentTranslation => $attributes['block_component_id'] ?? null,
            $model instanceof BlockComponentSectionTranslation => BlockComponentSection::query()->whereKey($attributes['block_component_section_id'] ?? null)->value('block_component_id'),
            default => null,
        };
        if (is_numeric($id)) {
            $this->invalidation->queue((int) $id);
        }
    }

    public function deleted(Model $model): void
    {
        $this->saved($model);
    }
}
