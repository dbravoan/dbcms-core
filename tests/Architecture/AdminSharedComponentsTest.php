<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

function adminSharedDom(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<!doctype html><html><body>'.$html.'</body></html>');
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return new DOMXPath($document);
}

test('row actions keep their submit type and accessible name without duplicated attributes', function (): void {
    $html = Blade::render('<x-admin.action-button type="submit" tooltip="Eliminar" icon="fa-solid fa-trash" aria-label="Eliminar elemento" />');
    $dom = adminSharedDom($html);
    $button = $dom->query('//button')->item(0);

    expect($button)->toBeInstanceOf(DOMElement::class)
        ->and($button->getAttribute('type'))->toBe('submit')
        ->and($button->getAttribute('aria-label'))->toBe('Eliminar elemento')
        ->and(substr_count($html, 'type="'))->toBe(1)
        ->and(substr_count($html, 'aria-label="'))->toBe(1)
        ->and($dom->query('//button/i[@aria-hidden="true"]')->length)->toBe(1);

    $link = adminSharedDom(Blade::render('<x-admin.action-button href="/item" tooltip="Ver elemento" />'))->query('//a')->item(0);
    expect($link->getAttribute('href'))->toBe('/item')
        ->and($link->getAttribute('aria-label'))->toBe('Ver elemento')
        ->and($link->hasAttribute('type'))->toBeFalse();
});

test('shared modal renders one labelled dialog with toolbar footer and owned close event', function (): void {
    $html = Blade::render(<<<'BLADE'
        <div x-data="{ active: true }">
            <x-admin.modal show="active" title="Prueba" on-close="active = false" :maximizable="false">
                <x-slot:toolbar><span id="toolbar">Herramientas</span></x-slot:toolbar>
                <input class="admin-control" data-admin-dialog-initial-focus>
                <x-slot:footer><x-admin.button>Guardar</x-admin.button></x-slot:footer>
            </x-admin.modal>
        </div>
    BLADE);
    $dom = adminSharedDom($html);
    $dialog = $dom->query('//*[@role="dialog"]')->item(0);
    $title = $dom->query('//*[@id="'.$dialog->getAttribute('aria-labelledby').'"]')->item(0);

    expect($dom->query('//*[@role="dialog"]')->length)->toBe(1)
        ->and($title->textContent)->toContain('Prueba')
        ->and($dialog->getAttribute('tabindex'))->toBe('-1')
        ->and($dom->query('//*[@data-admin-dialog-close]')->length)->toBe(1)
        ->and($dom->query('//*[@id="toolbar"]')->length)->toBe(1)
        ->and($html)->toContain('adminDialog(', 'syncDialog(Boolean(active))', 'admin-dialog-request-close.stop="active = false"')
        ->not->toContain('@keydown.escape.window', '@click.outside', '@js(');
});

test('confirmation deletion and onboarding reuse the shared dialog and retain their events', function (): void {
    foreach (['delete-modal' => 'confirm-delete', 'confirm-modal' => 'confirm-action', 'onboarding-tutorial' => 'start-onboarding-tour'] as $component => $event) {
        $html = Blade::render('<x-admin.'.$component.' />');
        $dom = adminSharedDom($html);
        expect($dom->query('//*[@role="dialog"]')->length)->toBe(1)
            ->and($dom->query('//*[@data-admin-dialog-close]')->length)->toBe(1)
            ->and($html)->toContain($event, 'adminDialog(')
            ->not->toContain('@keydown.escape.window', '@js(');

        if ($component === 'delete-modal') {
            expect($dom->query('//form[@method="POST"]/input[@name="_method" and @value="DELETE"]')->length)->toBe(1)
                ->and($dom->query('//form/input[@name="_token"]')->length)->toBe(1);
        }
    }
});

test('listing toolbar preserves search sorting limits and filter controls for both brands', function (string $brand): void {
    config(['cms.brand' => $brand, 'cms.options.per_page' => [15, 30, 60]]);
    app()->instance('request', Request::create('/admin/items', 'GET', ['search' => 'Texto', 'sort_by' => 'name', 'sort_dir' => 'desc', 'per_page' => '30']));
    $html = Blade::render(<<<'BLADE'
        <x-admin.datatable-header title="Elementos" create-modal-event="new-item">
            <select name="status" class="admin-control" aria-label="Estado"><option value="draft">Borrador</option></select>
        </x-admin.datatable-header>
    BLADE);
    $dom = adminSharedDom($html);

    expect($dom->query('//form[@method="GET" and @class="admin-list-toolbar"]')->length)->toBe(1)
        ->and($dom->query('//input[@name="search" and @value="Texto"]')->length)->toBe(1)
        ->and($dom->query('//input[@name="sort_by" and @value="name"]')->length)->toBe(1)
        ->and($dom->query('//input[@name="sort_dir" and @value="desc"]')->length)->toBe(1)
        ->and($dom->query('//select[@name="per_page"]/option[@selected and @value="30"]')->length)->toBe(1)
        ->and($dom->query('//select[@name="status"]')->length)->toBe(1)
        ->and($html)->toContain('new-item', 'admin-list-search', 'admin-list-filters', 'data-auto-submit');
})->with(['dbcms', 'fixture-admin']);
