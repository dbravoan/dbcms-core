<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DbCms\Core\Services\Content\SafeContentRenderer;
use PHPUnit\Framework\TestCase;

final class SafeContentRendererTest extends TestCase
{
    public function test_generated_html_preserves_prose_but_removes_executable_content(): void
    {
        $html = (new SafeContentRenderer)->html('<h2>Heading</h2><p>Text <strong>bold</strong><img src="/image.jpg" onerror="alert(1)"></p><script>alert(1)</script><svg onload="alert(2)"></svg><iframe src="https://evil.test"></iframe>');
        self::assertStringContainsString('<h2>Heading</h2>', $html);
        self::assertStringContainsString('<strong>bold</strong>', $html);
        foreach (['<script', 'onerror', '<svg', '<iframe', 'alert('] as $attack) {
            self::assertStringNotContainsString($attack, $html);
        }
    }

    public function test_html_and_markdown_block_obfuscated_unsafe_links(): void
    {
        $renderer = new SafeContentRenderer;
        $html = $renderer->html('<a href="java&#x09;script:alert(1)" onclick="evil()">Link</a><img src="data:image/svg+xml,attack"><a href="https://example.test" target="_blank">Safe</a>');
        self::assertStringNotContainsString('script:', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('data:', $html);
        self::assertStringContainsString('https://example.test', $html);
        self::assertStringNotContainsString('javascript:', $renderer->markdown('[Link](javascript:alert%281%29)'));
        self::assertStringContainsString('<strong>safe</strong>', $renderer->markdown('**safe**'));
    }
}
