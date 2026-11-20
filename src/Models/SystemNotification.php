<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $uuid
 * @property string $module
 * @property string $type
 * @property string $title
 * @property string $message
 * @property string|null $action_url
 * @property array<string, mixed>|null $data
 * @property bool $is_read
 * @property bool $is_visible
 * @property bool $is_global
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SystemNotification extends Model
{
    protected $fillable = [
        'user_id',
        'uuid',
        'module',
        'type',
        'title',
        'message',
        'action_url',
        'data',
        'is_read',
        'is_visible',
        'is_global',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_visible' => 'boolean',
        'is_global' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * @param  Builder<SystemNotification>  $query
     * @return Builder<SystemNotification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false)->where('is_visible', true);
    }

    /**
     * Notifications visible to a user: their own plus the global ones
     * (those without a user_id).
     *
     * @param  Builder<SystemNotification>  $query
     * @return Builder<SystemNotification>
     */
    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if ($userId === null) {
            return $query->whereNull('user_id')->where('is_global', true);
        }

        return $query->where(function (Builder $inner) use ($userId): void {
            $inner->where(fn (Builder $global) => $global->whereNull('user_id')->where('is_global', true))->orWhere('user_id', $userId);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): bool
    {
        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'lead' => '📥',
            'order' => '🛒',
            'inquiry' => '💬',
            'alert' => '⚠️',
            'success' => '✅',
            default => '🔔',
        };
    }
}
