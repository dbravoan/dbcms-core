<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Http\Controllers\Admin;

use DbCms\Core\Content\Pages\Contracts\PageDomainServiceInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\EditorialEntityRequest;
use Illuminate\Http\RedirectResponse;

final class PagePutController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(EditorialEntityRequest $request, Page $page): RedirectResponse
    {
        $this->service->updatePage($page, $request->validated());

        return redirect()->route('admin.pages.index')->with('success', 'Página actualizada correctamente.');
    }
}
