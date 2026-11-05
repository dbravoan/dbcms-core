<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $notification_id
 * @property int|null $user_id
 * @property string $status
 * @property string|null $failure_code
 */
final class NotificationEmailDelivery extends Model
{
    protected $fillable = ['notification_id', 'user_id', 'status', 'failure_code', 'attempted_at', 'sent_at'];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
