<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Markets;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\ResourceMutationRequest;
use DbCms\Core\Models\Market;
use Illuminate\Http\RedirectResponse;

final class MarketPutController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request, Market $market): RedirectResponse
    {
        return $this->resources->update('market', $market, $request);
    }
}
