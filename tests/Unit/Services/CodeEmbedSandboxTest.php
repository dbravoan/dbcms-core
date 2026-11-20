<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DbCms\Core\Blocks\Types\CodeEmbedBlock;
use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use Illuminate\Support\Facades\Vite;
use Tests\Support\FreshMigrationDatabase;
use Tests\TestCase;

final class CodeEmbedSandboxTest extends TestCase
{
    use FreshMigrationDatabase;

    public function test_code_embeds_keep_scripts_inside_an_opaque_sandbox(): void
    {
        config(['privacy.external_services' => ['fixture' => ['provider' => 'Fixture', 'purpose' => 'Embedding sandbox test', 'origins' => ['https://embed.example.test']]]]);
        app(GeneralSettingsServiceInterface::class)->set('legal.processing', [[
            'owner' => 'external_fixture', 'purpose' => 'Embedding sandbox test', 'legal_basis' => 'Optional consent',
            'data' => 'Network request data', 'retention' => 'Until consent withdrawal', 'manual_review' => true,
            'recipients' => 'Fixture provider', 'transfers' => 'None',
        ]], 'legal', 'json');
        Vite::useCspNonce('fixture-request-nonce');
        $html = (new CodeEmbedBlock)->render(['external_service' => 'fixture', 'content' => '<script>parent.document.cookie</script><img onerror="evil()">']);
        self::assertStringContainsString('sandbox="allow-scripts"', $html);
        self::assertStringContainsString('srcdoc="', $html);
        self::assertStringContainsString('nonce=&quot;fixture-request-nonce&quot;', $html);
        self::assertStringContainsString('Content-Security-Policy', $html);
        self::assertStringContainsString('https://embed.example.test', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('allow-same-origin', $html);
        self::assertStringNotContainsString('allow-top-navigation', $html);
    }
}
