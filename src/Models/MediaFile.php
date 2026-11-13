<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $filename
 * @property string $original_name
 * @property string|null $title
 * @property string|null $alt_text
 * @property string|null $caption
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property string $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaFile extends Model
{
    protected $fillable = [
        'uuid',
        'filename',
        'original_name',
        'title',
        'alt_text',
        'caption',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'url',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getIsVideoAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function getIsAudioAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    public function getIsAnimationAttribute(): bool
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION)) === 'json';
    }

    public function getIsDocAttribute(): bool
    {
        return in_array($this->mime_type, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/vtt',
        ], true);
    }
}
