<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Admin;

use DbCms\Core\DTOs\Admin\DatatableQueryData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class DatatableQueryService
{
    /**
     * Apply standardized live search, filters, column sorting, and pagination to an Eloquent query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $searchColumns  Columns to search (supports relations like 'translations.title')
     * @param  array<string, string>  $filterableColumns  Map of request param => database column
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(
        Builder $query,
        DatatableQueryData $input,
        array $searchColumns = [],
        array $filterableColumns = [],
        string $defaultSort = 'id',
        string $defaultDir = 'desc'
    ): LengthAwarePaginator {
        // 1. Text Search Filter
        $search = trim((string) $input->input('search', ''));
        if ($search !== '' && ! empty($searchColumns)) {
            $query->where(function (Builder $subQ) use ($search, $searchColumns): void {
                $first = true;
                foreach ($searchColumns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $relColumn] = explode('.', $column, 2);
                        $method = $first ? 'whereHas' : 'orWhereHas';
                        $subQ->{$method}($relation, function (Builder $relQ) use ($relColumn, $search): void {
                            $relQ->where($relColumn, 'LIKE', "%{$search}%");
                        });
                    } else {
                        $method = $first ? 'where' : 'orWhere';
                        $subQ->{$method}($column, 'LIKE', "%{$search}%");
                    }
                    $first = false;
                }
            });
        }

        // 2. Exact / Categorical Column Filters
        foreach ($filterableColumns as $paramKey => $dbColumn) {
            $val = $input->input($paramKey);
            if ($val !== null && $val !== '' && $val !== 'all') {
                // Relation filters (`markets.id`) go through `whereHas` before
                // any boolean shortcut: `market_id=1` is an id, not `true`, and
                // `where('markets.id', true)` would run on the wrong query.
                if (str_contains($dbColumn, '.')) {
                    [$rel, $col] = explode('.', $dbColumn, 2);
                    $query->whereHas($rel, function (Builder $relQ) use ($col, $val): void {
                        $table = $relQ->getModel()->getTable();
                        $column = str_contains($col, '.') ? $col : $table.'.'.$col;

                        $relQ->where($column, $val);
                    });

                    continue;
                }

                if ($val === 'true' || $val === '1' || $val === 'active' || $val === 'published') {
                    $query->where($dbColumn, true);
                } elseif ($val === 'false' || $val === '0' || $val === 'inactive' || $val === 'draft') {
                    $query->where($dbColumn, false);
                } else {
                    $query->where($dbColumn, $val);
                }
            }
        }

        // 3. Column Sorting / Ordering
        $sortBy = (string) $input->input('sort_by', $defaultSort);
        $sortDir = strtolower((string) $input->input('sort_dir', $defaultDir)) === 'asc' ? 'asc' : 'desc';

        $safeDefaultDir = strtolower($defaultDir) === 'asc' ? 'asc' : 'desc';
        $model = $query->getModel();
        $columns = $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable());
        if (in_array($sortBy, $columns, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy($defaultSort, $safeDefaultDir);
        }

        // 4. Pagination Limit
        $perPage = (int) $input->input('per_page', 15);
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 15;
        }

        /** @var LengthAwarePaginator<int, TModel> $paginator */
        $paginator = $query->paginate($perPage, ['*'], 'page', (int) $input->input('page', 1))->appends($input->query);

        return $paginator;
    }
}
