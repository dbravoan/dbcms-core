<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Http;

use DbCms\Core\Blocks\Components\Services\ComponentAdministration;
use DbCms\Core\Models\BlockComponent;
use Illuminate\Http\RedirectResponse;

final class ComponentPutController
{
    public function __invoke(ComponentRequest $request, ComponentAdministration $service, BlockComponent $component): RedirectResponse
    {
        $service->update($component, $request->validated());

        return redirect()->route('admin.block-components.index')->with('success', 'Componente guardado.');
    }
}
