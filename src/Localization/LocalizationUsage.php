<?php

declare(strict_types=1);

namespace DbCms\Core\Localization;

use DbCms\Core\Models\Market;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects whether a market is still referenced by content.
 *
 * Several tables define `market_id` with `cascadeOnDelete`, so deleting a market
 * used by content would silently wipe it. The backoffice asks this first and
 * refuses the deletion while the market is in use (and tells the admin where).
 */
final class LocalizationUsage
{
    /**
     * Tables (with row counts) that reference the given market.
     *
     * The `market_language` assignment is ignored: it is the pivot the market
     * owns and it is meant to disappear with the market.
     *
     * @return array<string, int>
     */
    public function marketUsage(Market $market): array
    {
        $usage = [];

        foreach ($this->tablesWithColumn('market_id') as $table) {
            if ($table === 'market_language') {
                continue;
            }

            $count = (int) DB::table($table)->where('market_id', $market->getKey())->count();

            if ($count > 0) {
                $usage[$table] = $count;
            }
        }

        return $usage;
    }

    /**
     * @return array<int, string>
     */
    private function tablesWithColumn(string $column): array
    {
        try {
            $rows = DB::select(
                'SELECT TABLE_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ?',
                [$column]
            );

            $tables = [];

            foreach ($rows as $row) {
                /** @var object{name: string} $row */
                $tables[] = (string) $row->name;
            }

            if ($tables !== []) {
                return $tables;
            }
        } catch (\Throwable) {
            // Fall back to the schema introspection below.
        }

        // Portable fallback (e.g. SQLite in unit tests).
        $tables = [];

        foreach (Schema::getTableListing() as $table) {
            $table = str_replace(['`', '"'], '', (string) $table);

            if (in_array($column, Schema::getColumnListing($table), true)) {
                $tables[] = $table;
            }
        }

        return $tables;
    }
}
