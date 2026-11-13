<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks\Components\Http;

use DbCms\Core\Blocks\Components\Services\ComponentAdministration;
use DbCms\Core\Blocks\Components\Services\ComponentEditorialPreview;
use DbCms\Core\DTOs\Front\PublicContentContext;
use DbCms\Core\Http\Middleware\SecurityHeaders;
use DbCms\Core\Models\BlockComponent;
use DbCms\Core\Models\Market;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ComponentPreviewGetController
{
    public function __invoke(Request $request, BlockComponent $component, ComponentAdministration $service, ComponentEditorialPreview $preview): Response
    {
        $input = $request->validate(['market' => 'nullable|integer|exists:markets,id', 'locale' => 'nullable|string|max:10']);
        $market = Market::query()->where('is_active', true)->when(isset($input['market']), fn ($query) => $query->whereKey($input['market']))->orderByDesc('is_default')->firstOrFail();
        $locale = (string) ($input['locale'] ?? $market->default_locale);
        abort_unless($market->languages()->where('code', $locale)->where('is_active', true)->exists(), 404);
        app()->setLocale($locale);
        $request->attributes->set('cms.editorial_preview', true);
        $request->attributes->set('market', $market);
        $draft = $service->formData($component)['blockComponent'];
        assert($draft instanceof BlockComponent);
        $page = $preview->render($draft, new PublicContentContext($market, $locale));
        abort_if($page->viewName === null, 404);

        return response()->view($page->viewName, $page->data)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Content-Security-Policy', app(SecurityHeaders::class)->previewPolicy());
    }
}
