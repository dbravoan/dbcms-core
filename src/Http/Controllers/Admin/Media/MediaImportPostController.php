<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Media;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaImportPostController extends Controller
{
    public function __invoke(Request $request, MediaUploadService $media): JsonResponse
    {
        $result = $media->importImage($request->only(['url', 'title', 'alt_text']));

        return response()->json(['success' => true, 'file' => $result['media']]);
    }
}
