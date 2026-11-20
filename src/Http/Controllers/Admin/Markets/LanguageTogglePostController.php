<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Markets;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Models\Language;
use Illuminate\Http\RedirectResponse;

/**
 * Enables/disables a language. The platform default language is protected so
 * the site always keeps at least one usable locale.
 */
final class LanguageTogglePostController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(Language $language): RedirectResponse
    {
        return $this->resources->toggle('language', $language);
    }
}
