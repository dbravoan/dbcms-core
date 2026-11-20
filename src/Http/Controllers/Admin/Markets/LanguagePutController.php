<?php

declare(strict_types=1);

namespace DbCms\Core\Http\Controllers\Admin\Markets;

use DbCms\Core\Http\Adapters\Resources\AdminResourceMutationTransport;
use DbCms\Core\Http\Controllers\Controller;
use DbCms\Core\Http\Requests\Admin\ResourceMutationRequest;
use DbCms\Core\Models\Language;
use Illuminate\Http\RedirectResponse;

/**
 * Updates a language (name, native name, flag emoji, direction, status).
 */
final class LanguagePutController extends Controller
{
    public function __construct(private readonly AdminResourceMutationTransport $resources) {}

    public function __invoke(ResourceMutationRequest $request, Language $language): RedirectResponse
    {
        return $this->resources->update('language', $language, $request);
    }
}
