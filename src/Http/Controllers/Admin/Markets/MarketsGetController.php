<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Markets;

use DbCms\Core\DTOs\Admin\DatatableQueryData;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class MarketsGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = Market::with('languages');

        $markets = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
            ['name', 'code', 'currency'],
            ['is_active' => 'is_active'],
            'created_at',
            'asc'
        );

        $allLanguages = Language::all();

        return view('admin.markets.index', [
            'markets' => $markets,
            'allLanguages' => $allLanguages,
        ]);
    }
}
