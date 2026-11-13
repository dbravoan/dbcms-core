<?php

declare(strict_types=1);

namespace DbCms\Core\View\Components;

use DbCms\Core\Blocks\BlockCatalogService;
use DbCms\Core\Blocks\BlockRenderer;
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Models\Market;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/** Code-owned pages use the same availability and payload rules as the editor. */
final class CmsBlock extends Component
{
    /** @param array<string,mixed> $payload */
    public function __construct(public string $type, public array $payload = [], public ?Market $market = null, public ?string $locale = null) {}

    public function render(): HtmlString
    {
        $blocks = app(BlockRegistryInterface::class);
        $html = '';
        if ($blocks->isAvailable($this->type)) {
            $market = $this->market ?? (app()->bound('current.market') ? app('current.market') : null);
            $locale = $this->locale ?? app()->getLocale();
            if (! $market instanceof Market) {
                throw new \LogicException('CMS blocks need a market context; use the cms.front middleware.');
            }
            if ($market->is_active && $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
                $blocks->validate($this->type, $this->payload);
                app(BlockCatalogService::class)->validateTree(array_replace($this->payload, ['type' => $this->type]), $market, $locale);
                $html = app(BlockRenderer::class)->render($this->type, $this->payload, $market, $locale);
            }
        }

        return new HtmlString($html);
    }
}
