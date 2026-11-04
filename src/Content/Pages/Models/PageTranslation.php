<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Models;

use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property string $locale
 * @property string $title
 * @property string $slug
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string $schema_type
 * @property int|null $market_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PageTranslation extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'page_id',
        'locale',
        'title',
        'slug',
        'meta_title',
        'meta_description',
        'schema_type',
        'market_id',
    ];

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
