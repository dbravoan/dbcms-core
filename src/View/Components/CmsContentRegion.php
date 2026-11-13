<?php

declare(strict_types=1);

namespace DbCms\Core\View\Components;

use DbCms\Core\Services\Cms\FrontViewResolver;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** A theme's content track with an explicit, parent-contained viewport track. */
final class CmsContentRegion extends Component
{
    public string $theme;

    public function __construct(?string $theme = null, public string $as = 'div')
    {
        if (! in_array($as, ['div', 'main', 'section'], true)) {
            throw new \InvalidArgumentException('Content regions must use div, main or section.');
        }
        $this->theme = $theme ?? app(FrontViewResolver::class)->theme();
    }

    public function render(): View
    {
        return view('components.cms.content-region');
    }
}
