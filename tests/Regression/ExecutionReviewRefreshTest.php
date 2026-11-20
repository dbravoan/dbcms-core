<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

it('lets a human refresh an outdated review without approving it automatically', function (): void {
    $html = view('admin.orchestration.panel', ['initialExecutionId' => '00000000-0000-4000-8000-000000000001'])->render();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>');
        $buttons = (new DOMXPath($document))->query('//button[contains(@x-show, "approval_required") and @*[name()="x-on:click" and contains(., "resume")]]');
        expect($buttons->length)->toBe(1)
            ->and($buttons->item(0)?->textContent)->toContain('Actualizar revisión');
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
});
