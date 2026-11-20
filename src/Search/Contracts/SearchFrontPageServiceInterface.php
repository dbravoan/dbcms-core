<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Contracts;

use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Search\DTOs\SearchFrontInput;
use Illuminate\Support\Collection;

interface SearchFrontPageServiceInterface
{
    public function hub(SearchFrontInput $input): FrontPageData;

    public function results(SearchFrontInput $input): FrontPageData;

    /** @return Collection<int,Language> */
    public function languages(Market $market): Collection;
}
