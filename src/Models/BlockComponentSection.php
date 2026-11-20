<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $is_active
 * @property string $status
 * @property int $order
 * @property-read Collection<int,BlockComponentSectionTranslation> $translations
 */
final class BlockComponentSection extends Model
{
    protected $fillable = ['block_component_id', 'type', 'order', 'styling_json', 'is_active', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['styling_json' => 'array', 'is_active' => 'boolean', 'order' => 'integer', 'published_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<BlockComponent,$this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(BlockComponent::class, 'block_component_id');
    }

    /**
     * @return HasMany<BlockComponentSectionTranslation,$this> */
    public function translations(): HasMany
    {
        return $this->hasMany(BlockComponentSectionTranslation::class);
    }
}
