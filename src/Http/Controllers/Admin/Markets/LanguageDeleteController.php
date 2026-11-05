<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Markets;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Illuminate\Http\RedirectResponse;

/**
 * Removes a language (detaching it from every market).
 *
 * The platform default language and any language used as a market's
 * `default_locale` are protected, so no market is left pointing at a missing
 * language.
 */
final class LanguageDeleteController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(Language $language): RedirectResponse
    {
        return $this->resources->delete('language', $language);
    }
}
