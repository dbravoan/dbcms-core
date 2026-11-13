<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Http;

use DbCms\Core\Models\BlockComponent;
use Illuminate\Contracts\View\View;

final class ComponentsGetController
{
    public function __invoke(): View
    {
        return view('admin.block-components.index', ['components' => BlockComponent::with('translations')->latest('id')->paginate(20)]);
    }
}
