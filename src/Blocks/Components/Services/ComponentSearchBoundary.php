<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Services;

use DbCms\Core\Models\BlockComponent;
use Illuminate\Support\Facades\DB;

/** Durable publication deadlines; ordinary searches inspect only the indexed due rows. */
final class ComponentSearchBoundary
{
    public const TABLE = 'block_component_search_boundaries';

    public function remember(int $id, ?BlockComponent $component): void
    {
        if ($component?->is_active && $component->is_published && $component->published_at?->isFuture()) {
            DB::table(self::TABLE)->updateOrInsert(['block_component_id' => $id], ['available_at' => $component->published_at]);

            return;
        }
        DB::table(self::TABLE)->where('block_component_id', $id)->delete();
    }

    public function synchronize(): void
    {
        while (DB::transaction(function (): bool {
            $boundary = DB::table(self::TABLE)->where('available_at', '<=', now())
                ->orderBy('available_at')->orderBy('block_component_id')->lockForUpdate()->first();
            if ($boundary === null) {
                return false;
            }
            // The same transaction retains the deadline if any consumer fails.
            // A concurrent search waits for these projections before returning.
            app(ComponentSearchInvalidator::class)->refresh((int) $boundary->block_component_id);
            DB::table(self::TABLE)->where('block_component_id', $boundary->block_component_id)
                ->where('available_at', '<=', now())->delete();

            return true;
        })) {
            // Process the next deadline without holding every due definition's lock.
        }
    }
}
