<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Media;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MediaPutController extends Controller
{
    public function __construct(
        private readonly MediaManagerService $mediaService
    ) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
        ]);

        $file = $this->mediaService->updateMetadata($id, $validated);

        return response()->json([
            'success' => true,
            'file' => $file,
        ]);
    }
}
