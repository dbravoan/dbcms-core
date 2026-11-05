<?php

declare(strict_types=1);

namespace DbCms\Core\Events;

use DbCms\Core\DTOs\Install\LegalCompanyDTO;

final readonly class PrivacyConfigurationValidating
{
    public function __construct(public LegalCompanyDTO $company) {}
}
