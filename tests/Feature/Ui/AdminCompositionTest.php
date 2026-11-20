<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\Process\Process;

// No catalogue or demonstration records are needed for the presentation contract.
test('shared page composition preserves actions and alpine state in either brand', function (string $theme): void {
    config(['cms.admin_theme' => $theme]);
    $html = Blade::render(<<<'BLADE'
        <x-admin.form-header title="Editar contenido" description="Contenido por idioma" back-route="/admin/pages" live-url="https://example.test/page">
            <x-slot:actions><x-admin.button type="button" x-on:click="translate()" x-bind:disabled="busy">Traducir</x-admin.button></x-slot:actions>
        </x-admin.form-header>
        <form method="POST" action="/admin/pages/7">
            <x-admin.form-card title="Publicación" x-data="{ published: true }" x-show="canEdit" data-editor-panel="publication">
                <x-admin.switch name="is_published" id="publication-state" label="Publicado" checked />
                <x-admin.form-actions cancel-route="/admin/pages" submit-label="Guardar cambios" :submit-disabled="true" />
            </x-admin.form-card>
        </form>
        BLADE);
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($dom->getElementsByTagName('h1')->item(0)->textContent)->toContain('Editar contenido');
    $panel = $xpath->query('//section[@data-editor-panel="publication"]')->item(0);
    expect($panel->getAttribute('x-data'))->toBe('{ published: true }');
    expect($panel->getAttribute('x-show'))->toBe('canEdit');
    $switch = $xpath->query('//input[@name="is_published"]')->item(0);
    expect($switch->getAttribute('role'))->toBe('switch');
    expect($switch->hasAttribute('checked'))->toBeTrue();
    expect($xpath->query('//label[@for="publication-state"]')->length)->toBe(1);
    $submit = $xpath->query('//form//button[@type="submit"]')->item(0);
    expect($submit->hasAttribute('disabled'))->toBeTrue();
    expect($submit->textContent)->toContain('Guardar cambios');
    $translate = $xpath->query('//header//button')->item(0);
    expect($translate->getAttribute('x-on:click'))->toBe('translate()');
    expect($translate->getAttribute('x-bind:disabled'))->toBe('busy');
    expect($xpath->query('//a[@target="_blank"]')->item(0)->getAttribute('rel'))->toContain('noopener');
})->with(['dbcms', 'fixture-admin']);

test('textarea preserves identity bindings validation and an explicit description', function (): void {
    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['brief' => ['El brief es obligatorio.']]));
    view()->share('errors', $errors);
    $html = Blade::render('<x-admin.textarea name="brief" id="campaign-brief" label="Brief" x-model="brief" aria-describedby="brief-help" required />', compact('errors'));
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $control = $dom->getElementsByTagName('textarea')->item(0);
    expect($control->getAttribute('id'))->toBe('campaign-brief');
    expect($control->getAttribute('x-model'))->toBe('brief');
    expect($control->getAttribute('aria-invalid'))->toBe('true');
    expect($control->getAttribute('aria-describedby'))->toBe('brief-help campaign-brief-description');
    expect($dom->getElementsByTagName('label')->item(0)->getAttribute('for'))->toBe('campaign-brief');
    expect($html)->toContain('El brief es obligatorio.');
});

test('a disabled navigation action cannot receive keyboard focus', function (): void {
    $html = Blade::render('<x-admin.button href="/admin/export" disabled>Exportar</x-admin.button>');
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $link = $dom->getElementsByTagName('a')->item(0);
    expect($link->getAttribute('aria-disabled'))->toBe('true');
    expect($link->getAttribute('tabindex'))->toBe('-1');
});

test('rendered seo panel data executes getters and metadata edits in either brand', function (string $theme): void {
    config(['cms.admin_theme' => $theme]);
    $title = 'Una revisión con "comillas", apóstrofo y <etiquetas>';
    $description = str_repeat('Descripción editorial & fuentes. ', 4);
    $html = Blade::render('<x-admin.seo-meta-box locale="es" :all-languages="[\'es\', \'en\']" :meta-title="$title" :meta-description="$description" />', compact('title', 'description'));
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $panel = $xpath->query('//*[@x-data]')->item(0);
    expect($panel)->not->toBeNull();
    $process = new Process(['node', PACKAGE_ROOT.'/tests/Frontend/helpers/evaluate-seo-data.mjs']);
    $process->setInput(json_encode(['expression' => $panel->getAttribute('x-data'), 'title' => $title, 'description' => $description], JSON_THROW_ON_ERROR));
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
    $state = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    expect($state['locale'])->toBe('es');
    expect($state['languages'])->toBe(['es', 'en']);
    expect($state['title'])->toBe($title)->and($state['previewTitle'])->toBe($title);
    expect($state['description'])->toBe($description)->and($state['previewDescription'])->toBe($description);
    expect($state['titleLength'])->toBe(mb_strlen($title))->and($state['descriptionLength'])->toBe(mb_strlen($description));
    expect($state['schema'])->toBe('WebPage')->and($state['score'])->toBe(100);
})->with(['dbcms', 'fixture-admin']);
