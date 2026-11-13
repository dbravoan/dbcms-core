<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Blocks;

use DbCms\Core\Blocks\BlockPreview;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BlockPreviewPostController extends Controller
{
    public function __invoke(Request $request, BlockPreview $preview): JsonResponse
    {
        $input = $request->validate(['type' => 'required|string|max:128', 'payload' => 'required|array', 'entity_type' => 'required|string|max:100', 'entity_id' => 'nullable|integer|min:1', 'locale' => 'required|string|max:20', 'market' => 'required|string|max:20', 'selected_node_id' => 'nullable|string|max:128', 'selected_column_index' => 'nullable|integer|min:0|max:99']);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return response()->json(['html' => $preview->document($input, $actor)])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
