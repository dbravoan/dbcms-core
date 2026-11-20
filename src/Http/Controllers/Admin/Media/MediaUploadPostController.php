<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Media;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class MediaUploadPostController extends Controller
{
    public function __construct(
        private readonly MediaManagerService $mediaService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:'.MediaManagerService::MAX_UPLOAD_KILOBYTES,
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $uploaded = $this->mediaService->upload($file);

        return response()->json([
            'success' => true,
            'file' => $uploaded,
        ]);
    }
}
