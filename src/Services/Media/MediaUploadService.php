<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MediaUploadService
{
    public function __construct(private readonly MediaManagerService $media) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function mediaUpload(array $args): array
    {
        $filename = (string) ($args['filename'] ?? '');
        $content = (string) ($args['content_base64'] ?? '');

        if ($filename === '' || $content === '') {
            throw ValidationException::withMessages([
                'content_base64' => 'A filename and the base64 content are required.',
            ]);
        }

        $maxBytes = MediaManagerService::MAX_UPLOAD_KILOBYTES * 1024;
        if (strlen($content) > 4 * (int) ceil($maxBytes / 3)) {
            throw ValidationException::withMessages(['content_base64' => 'El archivo supera el límite de 50 MB.']);
        }

        $binary = base64_decode($content, true);

        if ($binary === false || strlen($binary) > $maxBytes) {
            throw ValidationException::withMessages(['content_base64' => 'The content is not valid base64.']);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'mcp-media-');

        if ($temporary === false) {
            throw ValidationException::withMessages(['content_base64' => 'The upload could not be prepared.']);
        }

        file_put_contents($temporary, $binary);

        try {
            $file = new UploadedFile(
                $temporary,
                $filename,
                (string) (mime_content_type($temporary) ?: 'application/octet-stream'),
                null,
                true,
            );

            $uploaded = $this->media->upload($file);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return ['uploaded' => true, 'media' => $uploaded];
    }

    /** @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function importImage(array $args): array
    {
        $validated = Validator::make($args, [
            'url' => ['required', 'url', 'max:2048', 'starts_with:http://,https://'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        try {
            $uploaded = $this->media->importFromUrl(
                (string) $validated['url'],
                (string) ($validated['title'] ?? 'Imagen importada'),
                isset($validated['alt_text']) ? (string) $validated['alt_text'] : null,
                true,
            );
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            throw ValidationException::withMessages(['url' => $exception->getMessage()]);
        }

        return ['uploaded' => true, 'media' => $uploaded];
    }
}
