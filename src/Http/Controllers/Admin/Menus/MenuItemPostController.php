<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Menus;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\ResourceMutationRequest;
use DbCms\Core\Models\Menu;
use Illuminate\Http\RedirectResponse;

final class MenuItemPostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request, Menu $menu): RedirectResponse
    {
        return $this->resources->create('menu_item', $request);
    }
}
