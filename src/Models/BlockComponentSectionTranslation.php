<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BlockComponentSectionTranslation extends Model
{
    protected $fillable = ['block_component_section_id', 'locale', 'market_id', 'content_json'];

    protected function casts(): array
    {
        return ['content_json' => 'array'];
    }

    /**
     * @return BelongsTo<BlockComponentSection,$this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(BlockComponentSection::class, 'block_component_section_id');
    }
}
