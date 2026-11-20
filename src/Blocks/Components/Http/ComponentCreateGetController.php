<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Http;

use DbCms\Core\Blocks\Components\Services\ComponentAdministration;
use Illuminate\Contracts\View\View;

final class ComponentCreateGetController
{
    public function __invoke(ComponentAdministration $service): View
    {
        return view('admin.block-components.create', $service->formData());
    }
}
