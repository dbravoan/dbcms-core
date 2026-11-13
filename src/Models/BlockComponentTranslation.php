<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $title
 * @property string|null $slug
 * @property string $locale
 * @property int|null $market_id */
final class BlockComponentTranslation extends Model
{
    protected $fillable = ['block_component_id', 'locale', 'market_id', 'title', 'slug'];

    /**
     * @return BelongsTo<BlockComponent,$this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(BlockComponent::class, 'block_component_id');
    }
}
