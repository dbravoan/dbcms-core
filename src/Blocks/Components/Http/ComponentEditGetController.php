<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Http;

use DbCms\Core\Blocks\Components\Services\ComponentAdministration;
use DbCms\Core\Models\BlockComponent;
use Illuminate\Contracts\View\View;

final class ComponentEditGetController
{
    public function __invoke(ComponentAdministration $service, BlockComponent $component): View
    {
        return view('admin.block-components.edit', $service->formData($component));
    }
}
