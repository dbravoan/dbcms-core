<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use DbCms\Core\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property string $type
 * @property int|null $target_id
 * @property string|null $url
 * @property int $order
 * @property bool $is_active
 * @property bool $target_blank
 */
class MenuItem extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory, Translatable;

    protected $fillable = [
        'menu_id',
        'parent_id',
        'type',
        'target_id',
        'url',
        'order',
        'is_active',
        'target_blank',
    ];

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('order');
    }

    /**
     * @return HasMany<MenuItemTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(MenuItemTranslation::class);
    }
}
