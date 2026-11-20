<?php

declare(strict_types=1);

namespace DbCms\Core\Search\DTOs;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Market;
use Illuminate\Http\Request;

final readonly class SearchFrontInput
{
    public function __construct(public Market $market, public string $locale, public string $query = '', public string $term = '', public ?string $entityType = null, public int $page = 1) {}

    public static function fromRequest(Request $request, LocalizationServiceInterface $localization): self
    {
        $market = $localization->resolveMarket($request);
        $rawType = $request->route('entityType');
        $query = $request->query('q', '');

        return new self($market, $localization->resolveLocale($request, $market)->code, is_scalar($query) ? (string) $query : '', (string) $request->route('term'), is_string($rawType) && $rawType !== '' ? $rawType : null, (int) $request->query('page', 1));
    }
}
