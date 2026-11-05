<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Contracts;

use DbCms\Core\DTOs\Front\FrontPageData;
use DbCms\Core\DTOs\Front\PublicContentContext;
use Illuminate\Database\Eloquent\Model;

interface EditorialPreviewHandlerInterface
{
    public function render(Model $entity, PublicContentContext $context): FrontPageData;
}
