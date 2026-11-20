<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $form_submission_id
 * @property string $action_key
 * @property array<string,mixed> $definition
 * @property string $status
 * @property int $attempts
 * @property string|null $failure_code
 * @property Carbon|null $completed_at
 * @property Carbon|null $updated_at
 * @property-read FormSubmission $submission
 */
final class FormSubmissionAction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['definition' => 'array', 'completed_at' => 'datetime'];
    }

    /** @return BelongsTo<FormSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }
}
