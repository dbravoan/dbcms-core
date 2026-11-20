<?php

declare(strict_types=1);

namespace DbCms\Core\Forms\Models;

use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $custom_form_id
 * @property array<string, mixed> $payload
 * @property string|null $locale
 * @property int|null $market_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property bool $is_reviewed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, FormAttachment> $attachments
 * @property-read Collection<int, FormSubmissionAction> $actions
 * @property-read CustomForm $form
 */
class FormSubmission extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'custom_form_id',
        'payload',
        'locale',
        'market_id',
        'ip_address',
        'user_agent',
        'is_reviewed',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_reviewed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (self $submission): void {
            // Serialize deletion with the outbox claim before checking work in flight.
            self::query()->whereKey($submission->id)->lockForUpdate()->first();
            if ($submission->actions()->where('status', 'processing')->exists()) {
                throw ValidationException::withMessages(['privacy' => 'Revisa la entrega en curso antes de eliminar el envío.']);
            }
            $paths = $submission->attachments()->pluck('path')->all();
            DB::afterCommit(static function () use ($paths): void {
                Storage::disk('local')->delete($paths);
            });
        });
    }

    /** @return HasMany<FormAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(FormAttachment::class, 'form_submission_id');
    }

    /** @return HasMany<FormSubmissionAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(FormSubmissionAction::class, 'form_submission_id');
    }

    /**
     * @return BelongsTo<CustomForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
