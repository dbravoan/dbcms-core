<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Media;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MediaFilesGetController extends Controller
{
    public function __invoke(Request $request, MediaManagerService $media): JsonResponse
    {
        $query = $request->validate([
            'search' => 'nullable|string|max:2000',
            'type' => ['nullable', Rule::in(['all', 'image', 'video', 'audio', 'animation', 'doc'])],
            'page' => 'nullable|integer|min:1',
            'per_page' => ['nullable', 'integer', Rule::in(config('cms.options.per_page', [10, 15, 25, 50, 100]))],
        ]);

        return response()->json($media->paginateMedia($query));
    }
}
