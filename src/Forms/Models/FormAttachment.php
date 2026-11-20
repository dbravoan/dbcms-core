<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $form_submission_id
 * @property string $field_key
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property int $size
 */
final class FormAttachment extends Model
{
    protected $guarded = [];

    protected $hidden = ['path'];

    /** @return BelongsTo<FormSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function bytes(): string
    {
        return Storage::disk('local')->get($this->path) ?? '';
    }
}
