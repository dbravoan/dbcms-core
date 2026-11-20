<?php

declare(strict_types=1);

namespace DbCms\Core\Search\Http\Controllers;

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Search\Contracts\SearchFrontPageServiceInterface;
use DbCms\Core\Search\DTOs\SearchFrontInput;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Public search transport; decisions belong to the core search service. */
final class SearchFrontController extends Controller
{
    public function __construct(private readonly SearchFrontPageServiceInterface $pages, private readonly LocalizationServiceInterface $localization) {}

    public function hub(Request $request): View|RedirectResponse
    {
        $data = $this->pages->hub(SearchFrontInput::fromRequest($request, $this->localization));
        abort_if($data->viewName === null && $data->redirectUrl === null, 404);

        return $data->redirectUrl !== null ? redirect()->to($data->redirectUrl, $data->redirectStatus) : view($data->viewName, $data->data);
    }

    public function results(Request $request): View|RedirectResponse
    {
        $data = $this->pages->results(SearchFrontInput::fromRequest($request, $this->localization));
        abort_if($data->viewName === null && $data->redirectUrl === null, 404);

        return $data->redirectUrl !== null ? redirect()->to($data->redirectUrl, $data->redirectStatus) : view($data->viewName, $data->data);
    }

    /** @return Collection<int,Language> */
    public function languages(Market $market): Collection
    {
        return $this->pages->languages($market);
    }
}
