<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Media;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaManagerService;
use Illuminate\Http\JsonResponse;

final class MediaDeleteController extends Controller
{
    public function __construct(
        private readonly MediaManagerService $mediaService
    ) {}

    public function __invoke(string $filename): JsonResponse
    {
        $deleted = $this->mediaService->delete($filename);

        return response()->json([
            'success' => $deleted,
        ]);
    }
}
