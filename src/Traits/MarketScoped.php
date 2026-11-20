<?php

declare(strict_types=1);

namespace DbCms\Core\Traits;

use DbCms\Core\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait MarketScoped
{
    /**
     * @return BelongsToMany<Market, $this>
     */
    abstract public function markets(): BelongsToMany;

    /**
     * Scopes queries to content available in the given market (or globally available if no markets assigned).
     *
     * @param  Builder<Model>  $query
     */
    public function scopeForMarket(Builder $query, Market|string|int|null $market): void
    {
        if ($market === null) {
            return;
        }

        $marketId = is_numeric($market) ? (int) $market : ($market instanceof Market ? $market->id : Market::where('code', $market)->value('id'));

        if (! $marketId) {
            return;
        }

        $query->where(function (Builder $q) use ($marketId): void {
            // Accessible if linked specifically to this market OR not linked to any market (global content)
            $q->whereHas('markets', function (Builder $mq) use ($marketId): void {
                $mq->where('markets.id', $marketId);
            })->orDoesntHave('markets');
        });
    }
}
