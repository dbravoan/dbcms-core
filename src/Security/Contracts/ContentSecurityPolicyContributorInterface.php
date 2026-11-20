<?php

declare(strict_types=1);

namespace DbCms\Core\Security\Contracts;

interface ContentSecurityPolicyContributorInterface
{
    /** @return list<string> */
    public function connectOrigins(): array;

    /** @return list<string> */
    public function frameOrigins(): array;
}
