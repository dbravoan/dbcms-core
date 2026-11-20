<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Observers;

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Content\Pages\Models\SectionTranslation;
use DbCms\Core\Search\SearchEntityObserver;
use Illuminate\Database\Eloquent\Model;

final class PageSearchObserver extends SearchEntityObserver
{
    protected function type(): string
    {
        return 'page';
    }

    protected function entityId(Model $model): int|string|null
    {
        $attributes = $model->getAttributes();
        $id = match (true) {
            $model instanceof Page => $attributes[$model->getKeyName()] ?? null,
            $model instanceof PageTranslation, $model instanceof PageSection => $attributes['page_id'] ?? null,
            default => null,
        };
        if ($model instanceof SectionTranslation && (is_int($attributes['page_section_id'] ?? null) || is_string($attributes['page_section_id'] ?? null))) {
            $id = PageSection::query()->whereKey($attributes['page_section_id'])->value('page_id');
        }

        return is_int($id) || is_string($id) ? $id : null;
    }
}
