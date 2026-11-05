<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Enums\OAuthScope;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as BaseAuthServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends BaseAuthServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    public function boot(): void
    {
        $this->registerPolicies();

        // Define OAuth2 scopes using the typed Enum
        Passport::tokensCan(OAuthScope::definitions());
        Passport::setDefaultScope([
            OAuthScope::READ_CONTENT->value,
        ]);

        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));
    }
}
