<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Adapters;

use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Models\Market;
use Illuminate\Http\Request;

final class PublicContentContextFactory
{
    public function fromRequest(Request $request): PublicContentContext
    {
        $market = app('current.market');
        abort_unless($market instanceof Market, 404);
        $slug = $request->route('slug');
        $page = filter_var($request->query('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;

        return new PublicContentContext($market, app()->getLocale(), is_string($slug) ? $slug : '', $request->url(), array_values(array_filter($request->segments(), 'is_string')), $request->query(), $page);
    }
}
