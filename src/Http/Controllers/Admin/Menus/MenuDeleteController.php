<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Menus;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Menu;
use Illuminate\Http\RedirectResponse;

final class MenuDeleteController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(Menu $menu): RedirectResponse
    {
        return $this->resources->delete('menu', $menu);
    }
}
