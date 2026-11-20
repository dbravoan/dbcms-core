<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Http\Controllers\Admin;

use DbCms\Core\Content\Pages\Contracts\PageDomainServiceInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class PageEditGetController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(Page $page): View
    {
        return view('admin.pages.edit', $this->service->editData($page));
    }
}
