<?php

declare(strict_types=1);

namespace DbCms\Core\Providers;

use DbCms\Core\Forms\Contracts\FieldType;
use DbCms\Core\Forms\CoreFieldType;
use DbCms\Core\Forms\FieldTypeRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the CMS form field framework.
 *
 * The registry is a singleton shared by every module: the core registers the
 * primitive HTML controls here, and modules (e.g. Captcha) register their own
 * types in their service provider's `boot()`.
 */
final class FormFieldTypesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FieldTypeRegistry::class, static function (): FieldTypeRegistry {
            $registry = new FieldTypeRegistry;

            foreach (self::coreTypes() as $type) {
                $registry->register($type);
            }

            return $registry;
        });
    }

    /**
     * @return array<int, FieldType>
     */
    private static function coreTypes(): array
    {
        return [
            new CoreFieldType('text'),
            new CoreFieldType('email', 'email'),
            new CoreFieldType('tel'),
            new CoreFieldType('number', 'numeric'),
            new CoreFieldType('textarea'),
            new CoreFieldType('select'),
            new CoreFieldType('checkbox'),
            new CoreFieldType('radio'),
        ];
    }
}
