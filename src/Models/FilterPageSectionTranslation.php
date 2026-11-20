<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Localized content of a curated filter page block.
 *
 * @property int $id
 * @property int $filter_page_section_id
 * @property string $locale
 * @property array<string, mixed> $content_json
 * @property int|null $market_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FilterPageSection $section
 */
class FilterPageSectionTranslation extends Model
{
    protected $fillable = [
        'filter_page_section_id',
        'locale',
        'content_json',
        'market_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<FilterPageSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(FilterPageSection::class, 'filter_page_section_id');
    }
}
