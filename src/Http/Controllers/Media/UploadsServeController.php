<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Media;

use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Services\Media\MediaUrlResolver;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Backoffice-compatible uploads fallback.
 *
 * Static files inside `public/uploads` are served directly by the web server,
 * so this controller only runs for missing files: it then resolves them from
 * the configured external disk. This guarantees that `/uploads/{filename}`
 * references keep working even after the local copy has been pruned.
 */
final class UploadsServeController extends Controller
{
    public function __construct(
        private readonly MediaUrlResolver $urlResolver,
        private readonly ExternalStorageService $externalStorage
    ) {}

    public function __invoke(Request $request, string $path): BinaryFileResponse|RedirectResponse|StreamedResponse
    {
        $relative = $this->urlResolver->sanitize($path);

        // Reject traversal attempts and unexpected characters.
        if ($relative === '' || ! preg_match('#^[A-Za-z0-9_\-\./]+$#', $relative)) {
            abort(404);
        }

        $headers = strtolower(pathinfo($relative, PATHINFO_EXTENSION)) === 'vtt' ? ['Content-Type' => 'text/vtt; charset=UTF-8'] : [];
        $localPath = $this->urlResolver->localPath($relative);
        if (is_file($localPath)) {
            return response()->file($localPath, $headers);
        }

        if ($this->externalStorage->isConfigured()) {
            try {
                if ($this->externalStorage->exists($relative)) {
                    // Prefer an offload redirect to the public CDN when configured.
                    if ($this->externalStorage->publicUrlPrefix() !== '') {
                        return redirect()->away($this->externalStorage->url($relative));
                    }

                    return Storage::disk(ExternalStorageService::DISK)->response($relative, null, $headers);
                }
            } catch (Throwable) {
                // Fall through to a clean 404.
            }
        }

        abort(404);
    }
}
