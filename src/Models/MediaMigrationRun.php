<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $status
 * @property int $total
 * @property int $processed
 * @property int $migrated
 * @property int $skipped
 * @property int $failed
 * @property int $rewritten
 * @property int $pruned
 * @property array<string, mixed>|null $options
 * @property array<int, string>|null $errors
 * @property string|null $message
 * @property int|null $user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MediaMigrationRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'status',
        'total',
        'processed',
        'migrated',
        'skipped',
        'failed',
        'rewritten',
        'pruned',
        'options',
        'errors',
        'message',
        'user_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'total' => 'integer',
        'processed' => 'integer',
        'migrated' => 'integer',
        'skipped' => 'integer',
        'failed' => 'integer',
        'rewritten' => 'integer',
        'pruned' => 'integer',
        'options' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function progressPercent(): int
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return 100;
        }

        if ($this->total <= 0) {
            return $this->status === self::STATUS_RUNNING ? 0 : 100;
        }

        return (int) min(100, floor(($this->processed / $this->total) * 100));
    }

    /**
     * @return array<string, mixed>
     */
    public function toProgressArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'processed' => $this->processed,
            'migrated' => $this->migrated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'rewritten' => $this->rewritten,
            'pruned' => $this->pruned,
            'percent' => $this->progressPercent(),
            'message' => $this->message,
            'errors' => array_slice($this->errors ?? [], 0, 10),
            'finished' => $this->isFinished(),
        ];
    }
}
