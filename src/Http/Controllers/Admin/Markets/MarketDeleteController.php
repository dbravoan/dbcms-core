<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Markets;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Market;
use Illuminate\Http\RedirectResponse;

/**
 * Removes a market (and its language assignments).
 *
 * The default market and any market still referenced by content are protected:
 * several tables cascade on `market_id`, so a blind delete would wipe content.
 */
final class MarketDeleteController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(Market $market): RedirectResponse
    {
        return $this->resources->delete('market', $market);
    }
}
