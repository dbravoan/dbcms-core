<?php

declare(strict_types=1);
use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('keeps following form controls outside the schema editor mount', function (string $blockKey): void {
    $html = view('components.admin.blocks._block_form_body', [
        'blockKey' => $blockKey,
        'dynamicPayloads' => [],
        'activeBlockTypes' => [],
        'isSubBlock' => $blockKey === 'subBlock',
    ])->render();
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<html><body>'.$html.'<button id="after-editor" type="submit">Save</button></body></html>');
        $xpath = new DOMXPath($document);
        expect($xpath->query('//button[@id="after-editor"]')->length)->toBe(1);
        expect($xpath->query('//button[@id="after-editor"]/ancestor::template')->length)->toBe(0);
        $mount = $xpath->query('//*[@data-schema-editor][@x-effect="mountSchemaEditor($el, '.$blockKey.', \'content\')"]');
        expect($mount->length)->toBe(1);
        expect($mount->item(0)->parentNode->getAttribute('x-if'))->toBe('isBlockAvailable('.$blockKey.') && !typeDef('.$blockKey.'.type).editorView');
        expect($xpath->query('./*', $mount->item(0)->parentNode)->length)->toBe(1);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
})->with(['block', 'subBlock']);

it('keeps the publishing controls outside the complete block manager templates', function (): void {
    $registry = Mockery::mock(BlockRegistryInterface::class);
    $registry->shouldReceive('getActiveBlocks')->andReturn([]);
    $registry->shouldReceive('getDynamicDataPayloads')->andReturn([]);
    $registry->shouldReceive('get')->andReturnNull();
    $registry->shouldReceive('isAvailable')->andReturnFalse();
    app()->instance(BlockRegistryInterface::class, $registry);
    $html = Blade::render('<x-admin.block-manager :languages="$languages" /><button id="publish-page" type="submit">Save</button>', [
        'languages' => collect([(object) ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸']]),
    ]);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<html><body>'.$html.'</body></html>');
        $xpath = new DOMXPath($document);
        expect($xpath->query('//button[@id="publish-page"]')->length)->toBe(1);
        expect($xpath->query('//button[@id="publish-page"]/ancestor::template')->length)->toBe(0);
        expect($xpath->query('//template[@x-for="(block, index) in blocks"]/*')->length)->toBe(1);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
});
