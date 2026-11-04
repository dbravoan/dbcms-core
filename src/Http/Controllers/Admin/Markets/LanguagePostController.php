<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Markets;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\ResourceMutationRequest;
use Illuminate\Http\RedirectResponse;

final class LanguagePostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request): RedirectResponse
    {
        return $this->resources->create('language', $request);
    }
}
