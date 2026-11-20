<?php

declare(strict_types=1);

namespace DbCms\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Denormalized search index row.
 *
 * One row per entity translation, with the canonical URL already resolved so
 * serving a result is a single query with no module-specific joins.
 *
 * @property int $id
 * @property string $type
 * @property string $entity_id
 * @property string $locale
 * @property string|null $market_code
 * @property string $url
 * @property string $title
 * @property string $excerpt
 * @property string $body
 * @property float $weight
 * @property string|null $image_url
 * @property Carbon|null $updated_at
 * @property float $relevance Ranked relevance, only present on search queries.
 */
class SearchDocumentIndex extends Model
{
    protected $table = 'search_documents';

    protected $fillable = [
        'type',
        'entity_id',
        'locale',
        'market_code',
        'url',
        'title',
        'excerpt',
        'body',
        'weight',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
        ];
    }

    /**
     * @param  Builder<SearchDocumentIndex>  $query
     * @return Builder<SearchDocumentIndex>
     */
    public function scopeLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }
}
