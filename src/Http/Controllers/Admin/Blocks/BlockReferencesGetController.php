<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Blocks;

use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\FilterPage;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Capabilities\EntityReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BlockReferencesGetController extends Controller
{
    public function __invoke(Request $request, BlockCatalogService $catalog, EntityReadService $entities): JsonResponse
    {
        $input = $request->validate(['entity_type' => 'required|string|max:100', 'entity_id' => 'nullable|integer|min:1', 'locale' => 'required|string|max:20', 'market' => 'required|string|max:20', 'block_type' => 'required|string|max:128', 'source' => 'required|string|max:100', 'search' => 'nullable|string|max:200', 'limit' => 'nullable|integer|min:1|max:50', 'offset' => 'nullable|integer|min:0']);
        $actor = $request->user();
        $filter = $input['entity_type'] === 'filter_page';
        $permission = $filter ? 'manage:seo' : ($entities->schemaFor($input['entity_type'])->permission ?? 'access:admin');
        if ($filter && isset($input['entity_id'])) {
            FilterPage::query()->findOrFail((int) $input['entity_id']);
        }
        abort_unless($actor instanceof User && ($actor->isAdmin() || $actor->hasPermission($permission)), 403);

        return response()->json($catalog->references($filter ? 'page' : $input['entity_type'], $input['locale'], $input['market'], $input['block_type'], $input['source'], $input['search'] ?? '', (int) ($input['limit'] ?? 20), (int) ($input['offset'] ?? 0), ! $filter && isset($input['entity_id']) ? (int) $input['entity_id'] : null))->header('Cache-Control', 'private, no-store');
    }
}
