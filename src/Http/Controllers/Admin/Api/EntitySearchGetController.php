<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Api;

use DbCms\Core\Contracts\Services\EntitySearchServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * JSON endpoint powering the reusable `<x-admin.search-select>` component.
 * Only whitelisted sources declared in `config/entity_search.php` are allowed.
 */
final class EntitySearchGetController extends Controller
{
    public function __construct(
        private readonly EntitySearchServiceInterface $search
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', Rule::in($this->search->sources())],
            'q' => ['nullable', 'string', 'max:100'],
            'value_key' => ['nullable', 'string', 'max:60'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'locale' => ['nullable', 'string', 'max:10'],
            'market_ids' => ['nullable', 'array', 'max:50'],
            'market_ids.*' => ['integer'],
            'ids' => ['nullable', 'array', 'max:50'],
            'ids.*' => ['string', 'max:255'],
        ]);

        $source = (string) $validated['source'];
        $valueKey = isset($validated['value_key']) ? (string) $validated['value_key'] : null;
        $locale = isset($validated['locale']) ? (string) $validated['locale'] : null;

        /** @var list<int|string>|null $marketIds */
        $marketIds = isset($validated['market_ids']) && is_array($validated['market_ids'])
            ? array_values($validated['market_ids'])
            : null;

        /** @var list<string> $ids */
        $ids = isset($validated['ids']) && is_array($validated['ids']) ? array_values($validated['ids']) : [];

        if ($ids !== []) {
            return response()->json([
                'results' => $this->search->resolve($source, $ids, $valueKey, $locale, $marketIds),
                'has_more' => false,
            ]);
        }

        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : null;

        return response()->json(
            $this->search->search(
                $source,
                isset($validated['q']) ? (string) $validated['q'] : null,
                $valueKey,
                $perPage,
                $locale,
                $marketIds,
            )
        );
    }
}
