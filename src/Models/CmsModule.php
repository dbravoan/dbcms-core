<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $identifier
 * @property string $name
 * @property string $version
 * @property string|null $description
 * @property string $provider
 * @property bool $is_pageable
 * @property bool $is_installed
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CmsModule extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'identifier',
        'name',
        'version',
        'description',
        'provider',
        'is_pageable',
        'is_installed',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_pageable' => 'boolean',
            'is_installed' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
