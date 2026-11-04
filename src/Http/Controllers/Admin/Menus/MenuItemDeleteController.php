<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Menus;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\MenuItem;
use Illuminate\Http\RedirectResponse;

final class MenuItemDeleteController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(MenuItem $item): RedirectResponse
    {
        return $this->resources->delete('menu_item', $item);
    }
}
