<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DbCms\Core\Cms;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

final class ValidationMessagesTest extends TestCase
{
    public function test_validation_catalogs_have_identical_keys_and_interpolation_contracts(): void
    {
        $spanish = $this->flatten(require Cms::path('lang/es/validation.php'));
        $english = $this->flatten(require Cms::path('lang/en/validation.php'));
        self::assertSame(array_keys($english), array_keys($spanish));
        foreach ($english as $key => $message) {
            preg_match_all('/:[a-z_]+/', $message, $expected);
            preg_match_all('/:[a-z_]+/', $spanish[$key], $actual);
            sort($expected[0]);
            sort($actual[0]);
            self::assertSame($expected[0], $actual[0], $key);
        }
    }

    /** @param array<string,mixed> $messages
     * @return array<string,string>
     */
    private function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];
        foreach ($messages as $key => $message) {
            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            if (is_array($message)) {
                $flat += $this->flatten($message, $path);
            } else {
                self::assertIsString($message);
                $flat[$path] = $message;
            }
        }
        ksort($flat);

        return $flat;
    }

    public function test_required_locale_has_a_readable_spanish_validation_error(): void
    {
        $translator = new Translator(new FileLoader(new Filesystem, Cms::path('lang')), 'es');
        $translator->setFallback('en');
        $message = (new Factory($translator))->make([], ['default_locale' => 'required'])->errors()->first('default_locale');

        self::assertStringNotContainsString('validation.required', $message);
        self::assertStringContainsString('obligatorio', $message);
    }

    public function test_each_locale_has_readable_size_validation_messages(): void
    {
        foreach (['es' => 'al menos', 'en' => 'at least'] as $locale => $phrase) {
            $translator = new Translator(new FileLoader(new Filesystem, Cms::path('lang')), $locale);
            $translator->setFallback('en');
            $message = (new Factory($translator))->make(['name' => 'a'], ['name' => 'min:3'])->errors()->first('name');

            self::assertStringNotContainsString('validation.min', $message);
            self::assertStringContainsString('3', $message);
            self::assertStringContainsString($phrase, $message);
        }
    }
}
