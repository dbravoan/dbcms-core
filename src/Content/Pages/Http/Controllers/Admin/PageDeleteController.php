<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Http\Controllers\Admin;

use DbCms\Core\Content\Pages\Contracts\PageDomainServiceInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class PageDeleteController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(Page $page): RedirectResponse
    {
        $this->service->deletePage($page);

        return redirect()->route('admin.pages.index')->with('success', 'Página eliminada correctamente.');
    }
}
