<?php

declare(strict_types=1);

namespace DbCms\Core\Captcha\Fields;

use DbCms\Core\Captcha\Services\CaptchaService;
use DbCms\Core\Forms\Contracts\FieldType;
use DbCms\Core\Forms\FieldSchema;

/** Local CAPTCHA validation inputs are transient and never stored with a submission. */
final class CaptchaFieldType implements FieldType
{
    public function key(): string
    {
        return 'captcha';
    }

    public function available(): bool
    {
        return app(CaptchaService::class)->isEnabled();
    }

    public function stores(): bool
    {
        return false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(FieldSchema $field): array
    {
        $service = app(CaptchaService::class);

        $rules = [];

        // The field that must be present depends on the active driver (an
        // external-only widget never posts `captcha_response`).
        foreach ($service->requiredInputs() as $input) {
            $rules[$input] = ['captcha'];
        }

        return $rules;
    }

    public function view(): string
    {
        return 'captcha::captcha_input';
    }
}
