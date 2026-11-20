<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Http\Controllers\Admin;

use DbCms\Core\Forms\Models\FormAttachment;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FormAttachmentGetController extends Controller
{
    public function __invoke(FormAttachment $attachment): StreamedResponse
    {
        // The form route group requires authentication and manage:forms.
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->filename, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
