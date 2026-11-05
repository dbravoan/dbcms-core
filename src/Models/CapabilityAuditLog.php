<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit trail of the agent calls that touched the CMS.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $token_id
 * @property string|null $client_id
 * @property bool $is_m2m
 * @property string $tool
 * @property string $ability
 * @property string|null $permission
 * @property string $status
 * @property array<string, mixed>|null $args
 * @property string|null $error
 * @property int|null $duration_ms
 * @property string|null $ip
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class CapabilityAuditLog extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_DENIED = 'denied';

    public const STATUS_ERROR = 'error';

    public const STATUS_CONFIRMATION_REQUIRED = 'confirmation_required';

    protected $fillable = [
        'user_id',
        'token_id',
        'client_id',
        'is_m2m',
        'tool',
        'ability',
        'permission',
        'status',
        'args',
        'error',
        'duration_ms',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'is_m2m' => 'boolean',
            'args' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<CapabilityAuditLog>  $query
     * @return Builder<CapabilityAuditLog>
     */
    public function scopeProblems(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DENIED, self::STATUS_ERROR]);
    }
}
