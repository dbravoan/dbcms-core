<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** @property bool $email_enabled */
final class NotificationEmailPreference extends Model
{
    protected $fillable = ['user_id', 'module', 'email_enabled'];

    protected function casts(): array
    {
        return ['email_enabled' => 'boolean'];
    }
}
