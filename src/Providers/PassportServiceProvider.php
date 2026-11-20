<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Enums\OAuthScope;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class PassportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Passport standard routes enabled for OAuth2 token negotiation (client_credentials, password, etc.)
    }

    public function boot(): void
    {
        Passport::tokensCan(OAuthScope::definitions());

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }
}
