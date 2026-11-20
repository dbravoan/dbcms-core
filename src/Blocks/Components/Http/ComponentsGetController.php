<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Http;

use DbCms\Core\DTOs\Admin\DatatableQueryData;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ComponentsGetController
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $request->validate(['is_active' => 'nullable|in:all,0,1', 'is_published' => 'nullable|in:all,0,1']);
        $components = $tables->paginate(
            BlockComponent::with('translations'),
            DatatableQueryData::fromInput($request->query()),
            ['translations.title'],
            ['is_active' => 'is_active', 'is_published' => 'is_published'],
        );
        foreach ($components->items() as $component) {
            $translations = $component->translations;
            $title = '#'.$component->id;
            if ($translations->isNotEmpty()) {
                $title = ($translations->firstWhere('locale', app()->getLocale()) ?? $translations->first())->title;
            }
            $component->setAttribute('admin_title', $title);
            $component->setAttribute('admin_status', ! $component->is_active ? 'inactive' : ($component->is_published ? 'published' : 'draft'));
        }

        return view('admin.block-components.index', compact('components'));
    }
}
