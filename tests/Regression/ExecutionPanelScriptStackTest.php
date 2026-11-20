<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Support\CmsFixtures;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
});

it('renders the approval component initializer through each backoffice layout script stack', function (string $layout): void {
    config(['session.driver' => 'array']);
    session()->put('_token', 'csrf-fixture-token');
    $source = file_get_contents($layout);
    if ($source === false) {
        throw new RuntimeException('Cannot read the backoffice layout fixture.');
    }
    preg_match_all("/@stack\\('[^']+'\\)/", $source, $stacks);
    preg_match_all('/<meta\\b[^>]*>/', $source, $meta);
    $html = Blade::render(implode("\n", $meta[0])."\n@include('admin.orchestration.panel', ['initialExecutionId' => \$executionId])\n".implode("\n", $stacks[0]), [
        'executionId' => '00000000-0000-4000-8000-000000000001',
        'cspNonce' => 'fixture-nonce',
    ]);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<html><body>'.$html.'</body></html>');
        $xpath = new DOMXPath($document);
        $csrf = $xpath->query('//meta[@name="csrf-token"]');
        $panels = $xpath->query('//section[@x-data="cmsExecution()"]');
        $scripts = $xpath->query('//script[contains(text(), "function cmsExecution()")]');
        if ($csrf === false || $panels === false || $scripts === false) {
            throw new RuntimeException('Invalid backoffice fixture XPath.');
        }
        expect($csrf->length)->toBe(1);
        expect($panels->length)->toBe(1);
        expect($scripts->length)->toBe(1);
        $metaElement = $csrf->item(0);
        $scriptElement = $scripts->item(0);
        if (! $metaElement instanceof DOMElement || ! $scriptElement instanceof DOMElement) {
            throw new RuntimeException('Missing CSRF or approval script element.');
        }
        expect($metaElement->getAttribute('content'))->toBe(session()->token());
        expect($scriptElement->getAttribute('nonce'))->toBe('fixture-nonce');
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
})->with([Cms::path('resources/views/admin/layout.blade.php')]);
