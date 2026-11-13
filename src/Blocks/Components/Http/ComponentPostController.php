<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Http;

use DbCms\Core\Blocks\Components\Services\ComponentAdministration;
use DbCms\Core\Models\User;
use Illuminate\Http\RedirectResponse;

final class ComponentPostController
{
    public function __invoke(ComponentRequest $request, ComponentAdministration $service): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $service->create($request->validated(), $actor);

        return redirect()->route('admin.block-components.index')->with('success', 'Componente guardado.');
    }
}
