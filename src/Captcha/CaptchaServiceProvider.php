<?php

declare(strict_types=1);

namespace DbCms\Core\Captcha;

use DbCms\Core\Captcha\Fields\CaptchaFieldType;
use DbCms\Core\Captcha\Services\CaptchaCookieDisclosure;
use DbCms\Core\Captcha\Services\CaptchaService;
use DbCms\Core\Cms;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Forms\FieldTypeRegistry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class CaptchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CaptchaService::class);
        $this->app->tag(CaptchaService::class, 'cms.csp.connect');
        $this->app->tag(CaptchaCookieDisclosure::class, 'cms.cookie-disclosures');
    }

    public function boot(): void
    {
        $views = Cms::path('resources/views/captcha');
        $this->loadViewsFrom($views, 'captcha');
        $this->app->make(AdminThemeServiceInterface::class)->registerModuleThemePaths('captcha', $views);

        // Register the `captcha` form field type in the core form framework.
        if ($this->app->bound(FieldTypeRegistry::class)) {
            $this->app->make(FieldTypeRegistry::class)->register(new CaptchaFieldType);
        }

        // Custom validation rule 'captcha'
        Validator::extendImplicit('captcha', function ($attribute, $value, $parameters, $validator): bool {
            /** @var CaptchaService $service */
            $service = app(CaptchaService::class);

            return $service->verifyRequest($validator->getData());
        }, 'El código de seguridad Captcha no es correcto o ha expirado.');
    }
}
