<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Contracts;

use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\DTOs\Front\PublicContentContext;

interface PagePublicReaderInterface
{
    public function show(PublicContentContext $context): FrontPageData;
}
