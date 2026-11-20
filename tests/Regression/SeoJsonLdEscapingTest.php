<?php

declare(strict_types=1);

namespace Tests\Regression;

use DbCms\Core\Cms;
use DOMDocument;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

final class SeoJsonLdEscapingTest extends TestCase
{
    public function test_both_real_head_branches_keep_editorial_markup_inside_the_json_node(): void
    {
        $source = file_get_contents(Cms::path('resources/views/themes/components/seo-head.blade.php'));
        preg_match_all('~<script type="application/ld\+json">.*?</script>~s', $source, $matches);
        self::assertCount(2, $matches[0]);
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
        $data = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => '</script><script>alert("editorial")</script><img src=x onerror="alert(1)">', 'description' => 'Café & "comillas"'];
        $seoMeta = (object) ['schema' => $data];
        $jsonLd = $data;
        foreach ($matches[0] as $fragment) {
            ob_start();
            eval('?>'.$compiler->compileString($fragment));
            $rendered = (string) ob_get_clean();
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML('<html><head><meta charset="UTF-8">'.$rendered.'</head><body></body></html>');
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            self::assertSame(1, $document->getElementsByTagName('script')->length);
            self::assertSame(0, $document->getElementsByTagName('img')->length);
            $script = $document->getElementsByTagName('script')->item(0);
            self::assertNotNull($script);
            self::assertSame($data, json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR));
        }
    }
}
