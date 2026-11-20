<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Install;

final readonly class InstallationAdminDTO
{
    public function __construct(public string $name, public string $email, public string $password, public string $siteName, public string $adminPath, public string $brand, public string $frontTheme, public string $appUrl = '', public ?LegalCompanyDTO $company = null) {}
}
