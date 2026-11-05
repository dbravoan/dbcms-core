<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Adapters;

use DbCms\Core\DTOs\Front\FrontPageData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class FrontPageResponseMapper
{
    public function respond(FrontPageData $page): View|RedirectResponse
    {
        if ($page->redirectUrl !== null) {
            return redirect()->to($page->redirectUrl, $page->redirectStatus)->with($page->data);
        }

        return $this->view($page);
    }

    public function view(FrontPageData $page): View
    {
        abort_if($page->viewName === null, 404);

        return view($page->viewName, $page->data);
    }
}
