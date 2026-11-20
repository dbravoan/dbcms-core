<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Http\Controllers;

use DbCms\Core\Content\Pages\Contracts\PagePublicReaderInterface;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Http\Adapters\FrontPageResponseMapper;
use DbCms\Core\Http\Adapters\PublicContentContextFactory;
use DbCms\Core\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class FrontPageController extends Controller
{
    public function __construct(private readonly PagePublicReaderInterface $reader, private readonly PublicContentContextFactory $contexts, private readonly FrontPageResponseMapper $responses) {}

    public function show(Request $request): View
    {
        $result = $this->reader->show($this->contexts->fromRequest($request));
        $page = $result->data['page'] ?? null;
        if ($page instanceof Page && ! $page->usesSectionContent()) {
            $request->attributes->set('_cms_cache_public', false);
        }

        return $this->responses->view($result);
    }
}
