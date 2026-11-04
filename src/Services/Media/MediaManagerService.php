<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use DbCms\Core\Models\MediaFile;
use DbCms\Core\Services\Storage\ExternalStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaManagerService
{
    public const MAX_UPLOAD_KILOBYTES = 51200;

    public function __construct(
        private readonly ExternalStorageService $externalStorage,
        private readonly MediaUrlResolver $urlResolver,
        private readonly SvgSanitizer $svgSanitizer,
    ) {}

    /**
     * Resolves upload directory with test isolation.
     */
    public function getUploadDir(): string
    {
        return $this->urlResolver->uploadDir();
    }

    /**
     * Lists media files from database with automatic disk synchronization.
     *
     * @return array<int, array{
     *     id: int|string,
     *     uuid: string,
     *     name: string,
     *     filename: string,
     *     original_name: string,
     *     title: string,
     *     alt_text: string,
     *     caption: string|null,
     *     url: string,
     *     mime_type: string,
     *     size_formatted: string,
     *     width: int|null,
     *     height: int|null,
     *     is_image: bool,
     *     is_video: bool,
     *     is_doc: bool
     * }>
     */
    public function listMedia(): array
    {
        $dir = $this->getUploadDir();
        $filesOnDisk = File::files($dir);

        // Sync existing files on disk into media_files table if not tracked
        if (Schema::hasTable('media_files')) {
            $tracked = MediaFile::all()->keyBy('filename');

            foreach ($filesOnDisk as $file) {
                $filename = $file->getFilename();
                if (! $tracked->has($filename)) {
                    $ext = strtolower($file->getExtension());
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);
                    $dims = $isImage ? @getimagesize($file->getPathname()) : false;
                    $width = is_array($dims) ? (int) $dims[0] : null;
                    $height = is_array($dims) ? (int) $dims[1] : null;
                    $baseName = pathinfo($filename, PATHINFO_FILENAME);
                    $humanTitle = Str::headline($baseName);

                    MediaFile::create([
                        'uuid' => (string) Str::uuid(),
                        'filename' => $filename,
                        'original_name' => $filename,
                        'title' => $humanTitle,
                        'alt_text' => $humanTitle,
                        'caption' => null,
                        'mime_type' => (string) (File::mimeType($file->getPathname()) ?: 'application/octet-stream'),
                        'size_bytes' => (int) $file->getSize(),
                        'width' => $width,
                        'height' => $height,
                        'url' => $this->urlResolver->localUrl($filename),
                    ]);
                }
            }

            $records = MediaFile::orderBy('id', 'desc')->get();
            $result = [];

            foreach ($records as $record) {
                $ext = strtolower(pathinfo($record->filename, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true) || str_starts_with($record->mime_type, 'image/');
                $isVideo = in_array($ext, ['mp4', 'webm', 'mov'], true) || str_starts_with($record->mime_type, 'video/');
                $isDoc = in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'], true);

                $result[] = [
                    'id' => $record->id,
                    'uuid' => $record->uuid,
                    'name' => $record->title ?: $record->original_name,
                    'filename' => $record->filename,
                    'original_name' => $record->original_name,
                    'title' => (string) ($record->title ?: $record->original_name),
                    'alt_text' => (string) ($record->alt_text ?: $record->title ?: $record->original_name),
                    'caption' => $record->caption,
                    'url' => $this->urlResolver->url($record->filename, $record->url),
                    'mime_type' => $record->mime_type,
                    'size_formatted' => $record->formatted_size,
                    'width' => $record->width,
                    'height' => $record->height,
                    'is_image' => $isImage,
                    'is_video' => $isVideo,
                    'is_doc' => $isDoc,
                ];
            }

            return $result;
        }

        // Fallback if table does not exist
        $fallback = [];
        foreach ($filesOnDisk as $file) {
            $filename = $file->getFilename();
            $ext = strtolower($file->getExtension());
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);
            $isVideo = in_array($ext, ['mp4', 'webm', 'mov'], true);
            $isDoc = in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'], true);
            $sizeBytes = $file->getSize();
            $sizeFormatted = $sizeBytes > 1048576 ? round($sizeBytes / 1048576, 1).' MB' : round($sizeBytes / 1024, 1).' KB';

            $fallback[] = [
                'id' => $filename,
                'uuid' => $filename,
                'name' => $filename,
                'filename' => $filename,
                'original_name' => $filename,
                'title' => Str::headline(pathinfo($filename, PATHINFO_FILENAME)),
                'alt_text' => Str::headline(pathinfo($filename, PATHINFO_FILENAME)),
                'caption' => null,
                'url' => $this->urlResolver->localUrl($filename),
                'mime_type' => (string) File::mimeType($file->getPathname()),
                'size_formatted' => $sizeFormatted,
                'width' => null,
                'height' => null,
                'is_image' => $isImage,
                'is_video' => $isVideo,
                'is_doc' => $isDoc,
            ];
        }

        return array_reverse($fallback);
    }

    /**
     * Uploads media, generates WebP where supported, and persists metadata.
     *
     * @return array{
     *     id: int|string,
     *     uuid: string,
     *     url: string,
     *     filename: string,
     *     original_name: string,
     *     title: string,
     *     alt_text: string,
     *     caption: string|null,
     *     mime_type: string,
     *     size_formatted: string,
     *     width: int|null,
     *     height: int|null,
     *     is_image: bool
     * }
     */
    public function upload(UploadedFile $file): array
    {
        $this->validateUpload($file);
        $dir = $this->getUploadDir();

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'mp4', 'webm', 'mov', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowedExtensions, true)) {
            throw new \InvalidArgumentException("Tipo de archivo no permitido [{$ext}].");
        }

        $uuid = (string) Str::uuid();
        $filename = "{$uuid}.{$ext}";
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);

        // SVG served from the same origin is stored XSS unless active content
        // (scripts, event handlers, javascript: URLs) is stripped first.
        if ($ext === 'svg') {
            $this->svgSanitizer->sanitizeFile((string) $file->getRealPath());
        }

        // Off-host storage (AWS S3 / OVH / Cloudflare R2) when configured.
        if ($this->externalStorage->isConfigured()) {
            return $this->uploadToExternalDisk($file, $uuid, $filename, $isImage);
        }

        $file->move($dir, $filename);
        $fullPath = "{$dir}/{$filename}";
        $url = $this->urlResolver->localUrl($filename);

        // Attempt WebP conversion for jpg/png
        if ($isImage && in_array($ext, ['jpg', 'jpeg', 'png'], true) && function_exists('imagewebp')) {
            $webpFilename = "{$uuid}.webp";
            $webpPath = "{$dir}/{$webpFilename}";

            try {
                $img = in_array($ext, ['jpg', 'jpeg'], true)
                    ? @imagecreatefromjpeg($fullPath)
                    : @imagecreatefrompng($fullPath);

                if ($img instanceof \GdImage) {
                    $converted = @imagewebp($img, $webpPath, 85);
                    @imagedestroy($img);
                    if ($converted && is_file($webpPath) && filesize($webpPath) > 0) {
                        File::delete($fullPath);
                        $filename = $webpFilename;
                        $url = $this->urlResolver->localUrl($webpFilename);
                        $fullPath = $webpPath;
                    }
                }
            } catch (\Throwable) {
                // Keep original
            }
        }

        $dims = $isImage ? @getimagesize($fullPath) : false;
        $width = is_array($dims) ? (int) $dims[0] : null;
        $height = is_array($dims) ? (int) $dims[1] : null;

        $originalName = $file->getClientOriginalName();
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $title = Str::headline($baseName);
        $altText = $title;
        $sizeBytes = File::exists($fullPath) ? (int) File::size($fullPath) : (int) $file->getSize();
        $mime = (string) (File::mimeType($fullPath) ?: 'application/octet-stream');

        $recordId = $filename;
        if (Schema::hasTable('media_files')) {
            $record = MediaFile::create([
                'uuid' => $uuid,
                'filename' => $filename,
                'original_name' => $originalName,
                'title' => $title,
                'alt_text' => $altText,
                'caption' => null,
                'mime_type' => $mime,
                'size_bytes' => $sizeBytes,
                'width' => $width,
                'height' => $height,
                'url' => $url,
            ]);
            $recordId = $record->id;
        }

        $sizeFormatted = $sizeBytes > 1048576
            ? round($sizeBytes / 1048576, 1).' MB'
            : round($sizeBytes / 1024, 1).' KB';

        return [
            'id' => $recordId,
            'uuid' => $uuid,
            'url' => $url,
            'filename' => $filename,
            'original_name' => $originalName,
            'title' => $title,
            'alt_text' => $altText,
            'caption' => null,
            'mime_type' => $mime,
            'size_formatted' => $sizeFormatted,
            'width' => $width,
            'height' => $height,
            'is_image' => $isImage,
        ];
    }

    /** Every transport shares the same size, extension and actual MIME boundary. */
    private function validateUpload(UploadedFile $file): void
    {
        Validator::make(['file' => $file], ['file' => 'required|file|max:'.self::MAX_UPLOAD_KILOBYTES])->validate();
        $allowed = [
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
            'webp' => ['image/webp'], 'gif' => ['image/gif'],
            'svg' => ['image/svg+xml', 'text/xml', 'application/xml'],
            'mp4' => ['video/mp4'], 'webm' => ['video/webm'], 'mov' => ['video/quicktime'],
            'pdf' => ['application/pdf'], 'doc' => ['application/msword', 'application/x-ole-storage'],
            'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'txt' => ['text/plain'],
        ];
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if (! isset($allowed[$extension]) || ! in_array($mime, $allowed[$extension], true)) {
            throw ValidationException::withMessages(['file' => 'El contenido del archivo no corresponde a una extensión permitida.']);
        }
    }

    /**
     * Store an upload on the configured S3-compatible disk and track it.
     *
     * @return array{
     *     id: int|string,
     *     uuid: string,
     *     url: string,
     *     filename: string,
     *     original_name: string,
     *     title: string,
     *     alt_text: string,
     *     caption: string|null,
     *     mime_type: string,
     *     size_formatted: string,
     *     width: int|null,
     *     height: int|null,
     *     is_image: bool
     * }
     */
    private function uploadToExternalDisk(UploadedFile $file, string $uuid, string $filename, bool $isImage): array
    {
        $this->externalStorage->registerDisk();

        $tempPath = $file->getRealPath() ?: '';
        $dims = $isImage && $tempPath !== '' ? @getimagesize($tempPath) : false;
        $width = is_array($dims) ? (int) $dims[0] : null;
        $height = is_array($dims) ? (int) $dims[1] : null;

        Storage::disk(ExternalStorageService::DISK)->put($filename, (string) file_get_contents($tempPath));

        $url = $this->externalStorage->url($filename);
        $originalName = $file->getClientOriginalName();
        $title = Str::headline(pathinfo($originalName, PATHINFO_FILENAME));
        $sizeBytes = (int) ($file->getSize() ?: 0);
        $mime = (string) ((new \finfo(FILEINFO_MIME_TYPE))->file($tempPath) ?: 'application/octet-stream');

        $recordId = $filename;
        if (Schema::hasTable('media_files')) {
            $record = MediaFile::create([
                'uuid' => $uuid,
                'filename' => $filename,
                'original_name' => $originalName,
                'title' => $title,
                'alt_text' => $title,
                'caption' => null,
                'mime_type' => $mime,
                'size_bytes' => $sizeBytes,
                'width' => $width,
                'height' => $height,
                'url' => $url,
            ]);
            $recordId = $record->id;
        }

        $sizeFormatted = $sizeBytes > 1048576
            ? round($sizeBytes / 1048576, 1).' MB'
            : round($sizeBytes / 1024, 1).' KB';

        return [
            'id' => $recordId,
            'uuid' => $uuid,
            'url' => $url,
            'filename' => $filename,
            'original_name' => $originalName,
            'title' => $title,
            'alt_text' => $title,
            'caption' => null,
            'mime_type' => $mime,
            'size_formatted' => $sizeFormatted,
            'width' => $width,
            'height' => $height,
            'is_image' => $isImage,
        ];
    }

    /**
     * Download and persist a remote or base64 resource into the media library.
     *
     * @return array{
     *     id: int|string,
     *     uuid: string,
     *     url: string,
     *     filename: string,
     *     original_name: string,
     *     title: string,
     *     alt_text: string,
     *     caption: string|null,
     *     mime_type: string,
     *     size_formatted: string,
     *     width: int|null,
     *     height: int|null,
     *     is_image: bool,
     *     is_video: bool
     * }
     */
    public function importFromUrl(string $url, string $title, ?string $altText = null): array
    {
        if (str_starts_with($url, 'data:')) {
            $parts = explode(',', $url, 2);
            $meta = $parts[0] ?? '';
            $data = isset($parts[1]) ? base64_decode($parts[1], true) : false;
            if ($data === false) {
                throw new \InvalidArgumentException('Invalid base64 media.');
            }
            $ext = str_contains($meta, 'image/png') ? 'png' : (str_contains($meta, 'image/webp') ? 'webp' : 'jpg');

            return $this->saveFromBinary($data, $ext, $title, $altText);
        }

        $destination = app(RemoteMediaUrlPolicy::class)->resolve($url);
        $maxBytes = 100 * 1024 * 1024;
        $temporary = tempnam(sys_get_temp_dir(), 'dbcms-media-');
        if ($temporary === false) {
            throw new \RuntimeException('Cannot allocate media download.');
        }
        try {
            $pins = array_map(static fn (string $ip): string => $destination['host'].':'.$destination['port'].':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip), $destination['addresses']);
            $response = Http::timeout(60)->connectTimeout(10)->withOptions([
                'allow_redirects' => false,
                'proxy' => '',
                'sink' => $temporary,
                'curl' => [CURLOPT_RESOLVE => $pins],
                'progress' => static function (float $total, float $downloaded) use ($maxBytes): void {
                    if ($total > $maxBytes || $downloaded > $maxBytes) {
                        throw new \InvalidArgumentException('Media exceeds the 100 MB limit.');
                    }
                },
            ])->get($url);
            if (! $response->successful()) {
                throw new \RuntimeException('Media download failed: HTTP '.$response->status());
            }
            $binary = filesize($temporary) > 0 ? (string) file_get_contents($temporary) : $response->body();
            $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $ext = match ($contentType) {
                'image/webp' => 'webp', 'image/png' => 'png', 'image/jpeg' => 'jpg',
                'image/gif' => 'gif', 'image/svg+xml' => 'svg', 'video/mp4' => 'mp4', 'video/webm' => 'webm',
                default => throw new \InvalidArgumentException('Unsupported remote media MIME type.'),
            };
        } finally {
            @unlink($temporary);
        }

        return $this->saveFromBinary($binary, $ext, $title, $altText);
    }

    /**
     * Persist binary data directly into the media library.
     *
     * @return array{
     *     id: int|string,
     *     uuid: string,
     *     url: string,
     *     filename: string,
     *     original_name: string,
     *     title: string,
     *     alt_text: string,
     *     caption: string|null,
     *     mime_type: string,
     *     size_formatted: string,
     *     width: int|null,
     *     height: int|null,
     *     is_image: bool,
     *     is_video: bool
     * }
     */
    public function saveFromBinary(string $binaryData, string $extension, string $title, ?string $altText = null): array
    {
        $uuid = (string) Str::uuid();
        $ext = strtolower($extension);
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'mp4', 'webm', 'mov'];
        if (! in_array($ext, $allowed, true) || $binaryData === '' || strlen($binaryData) > 100 * 1024 * 1024) {
            throw new \InvalidArgumentException('Invalid media content or extension.');
        }
        if ($ext === 'svg') {
            $binaryData = $this->svgSanitizer->sanitize($binaryData);
            if (! str_contains($binaryData, '<svg')) {
                throw new \InvalidArgumentException('Invalid SVG image.');
            }
        } else {
            $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binaryData);
            $expected = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];
            if ($actualMime !== $expected[$ext]) {
                throw new \InvalidArgumentException('Media content does not match its extension.');
            }
        }
        $filename = "{$uuid}.{$ext}";
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);
        $isVideo = in_array($ext, ['mp4', 'webm', 'mov'], true);
        $dir = $this->getUploadDir();

        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $fullPath = "{$dir}/{$filename}";
        File::put($fullPath, $binaryData);
        $url = $this->urlResolver->localUrl($filename);

        // Attempt WebP conversion for jpg/png
        if ($isImage && in_array($ext, ['jpg', 'jpeg', 'png'], true) && function_exists('imagewebp')) {
            $webpFilename = "{$uuid}.webp";
            $webpPath = "{$dir}/{$webpFilename}";

            try {
                $img = in_array($ext, ['jpg', 'jpeg'], true)
                    ? @imagecreatefromjpeg($fullPath)
                    : @imagecreatefrompng($fullPath);

                if ($img instanceof \GdImage) {
                    $converted = @imagewebp($img, $webpPath, 85);
                    @imagedestroy($img);
                    if ($converted && is_file($webpPath) && filesize($webpPath) > 0) {
                        File::delete($fullPath);
                        $filename = $webpFilename;
                        $url = $this->urlResolver->localUrl($webpFilename);
                        $fullPath = $webpPath;
                        $ext = 'webp';
                    } else {
                        File::delete($webpPath);
                    }
                }
            } catch (\Throwable) {
                // Keep original
            }
        }

        $dims = $isImage ? @getimagesize($fullPath) : false;
        $width = is_array($dims) ? (int) $dims[0] : null;
        $height = is_array($dims) ? (int) $dims[1] : null;
        $sizeBytes = File::exists($fullPath) ? (int) File::size($fullPath) : strlen($binaryData);
        $sizeFormatted = $sizeBytes > 1048576 ? round($sizeBytes / 1048576, 1).' MB' : round($sizeBytes / 1024, 1).' KB';
        $mime = (string) (File::mimeType($fullPath) ?: ($isVideo ? 'video/mp4' : 'image/webp'));
        $originalName = "{$title}.{$ext}";
        $alt = $altText ?: $title;

        // If external storage is active, upload there as well
        if ($this->externalStorage->isConfigured()) {
            $this->externalStorage->registerDisk();
            Storage::disk(ExternalStorageService::DISK)->put($filename, (string) File::get($fullPath));
            $url = $this->externalStorage->url($filename);
        }

        $recordId = $filename;
        if (Schema::hasTable('media_files')) {
            $record = MediaFile::create([
                'uuid' => $uuid,
                'filename' => $filename,
                'original_name' => $originalName,
                'title' => $title,
                'alt_text' => $alt,
                'caption' => null,
                'mime_type' => $mime,
                'size_bytes' => $sizeBytes,
                'width' => $width,
                'height' => $height,
                'url' => $url,
            ]);
            $recordId = $record->id;
        }

        return [
            'id' => $recordId,
            'uuid' => $uuid,
            'url' => $url,
            'filename' => $filename,
            'original_name' => $originalName,
            'title' => $title,
            'alt_text' => $alt,
            'caption' => null,
            'mime_type' => $mime,
            'size_formatted' => $sizeFormatted,
            'width' => $width,
            'height' => $height,
            'is_image' => $isImage,
            'is_video' => $isVideo,
        ];
    }

    /**
     * Updates SEO metadata (title, alt_text, caption) for a media item.
     *
     * @param  array{title?: string|null, alt_text?: string|null, caption?: string|null}  $data
     */
    public function updateMetadata(int|string $id, array $data): MediaFile
    {
        $query = MediaFile::query();
        if (is_numeric($id)) {
            $media = $query->findOrFail((int) $id);
        } else {
            /** @var MediaFile $media */
            $media = $query->where('filename', $id)->orWhere('uuid', $id)->firstOrFail();
        }

        $updateData = [];
        if (array_key_exists('title', $data)) {
            $updateData['title'] = $data['title'];
        }
        if (array_key_exists('alt_text', $data)) {
            $updateData['alt_text'] = $data['alt_text'];
        }
        if (array_key_exists('caption', $data)) {
            $updateData['caption'] = $data['caption'];
        }

        $media->update($updateData);

        return $media;
    }

    /**
     * Deletes media file securely preventing path traversal.
     */
    public function delete(string $filename): bool
    {
        $safeName = basename($filename);
        if ($safeName !== $filename || ! preg_match('/^[a-zA-Z0-9_\-\.]+$/', $safeName)) {
            return false;
        }

        $dir = $this->getUploadDir();
        $fullPath = "{$dir}/{$safeName}";
        $local = File::exists($fullPath) || is_link($fullPath);
        if ($local) {
            $realUploads = realpath($dir);
            $realTarget = realpath($fullPath);
            if ($realUploads === false || $realTarget === false || ! str_starts_with($realTarget, $realUploads.DIRECTORY_SEPARATOR)) {
                return false;
            }
        }

        $remote = $this->externalStorage->isConfigured();
        if ($remote) {
            try {
                $this->externalStorage->registerDisk();
                if (! Storage::disk(ExternalStorageService::DISK)->delete($safeName)) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }
        if ($local && ! File::delete($fullPath)) {
            return false;
        }

        // Keep metadata until every owned file has been removed. A failed I/O
        // operation remains visible and can be retried, including remote deletes.
        $records = Schema::hasTable('media_files')
            ? MediaFile::where('filename', $safeName)->delete()
            : 0;

        return $local || $remote || $records > 0;
    }
}
