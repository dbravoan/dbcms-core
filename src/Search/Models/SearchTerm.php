<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Aggregated usage of one search term. Queries may contain personal data:
 * retention and access are governed by the Search privacy command.
 *
 * @property int $id
 * @property string $term
 * @property string $locale
 * @property string $market_code
 * @property int $hits
 * @property int $results
 * @property int $zero_results
 * @property Carbon|null $last_seen_at
 */
class SearchTerm extends Model
{
    protected $fillable = [
        'term',
        'locale',
        'market_code',
        'hits',
        'results',
        'zero_results',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'results' => 'integer',
            'zero_results' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<SearchTerm>  $query
     * @return Builder<SearchTerm>
     */
    public function scopeLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    /**
     * @param  Builder<SearchTerm>  $query
     * @return Builder<SearchTerm>
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('hits')->orderByDesc('last_seen_at');
    }

    /**
     * @param  Builder<SearchTerm>  $query
     * @return Builder<SearchTerm>
     */
    public function scopeUnsatisfied(Builder $query): Builder
    {
        return $query->where('zero_results', '>', 0)->orderByDesc('zero_results')->orderByDesc('hits');
    }
}
