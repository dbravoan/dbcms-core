<?php

declare(strict_types=1);

use DbCms\Core\Services\Cms\VisitorGeoDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'us']);
    CmsFixtures::home(['es', 'en'], ['es', 'us']);
});

test('visitor geo detection service correctly resolves country and browser language', function (): void {
    $geoService = new VisitorGeoDetectionService;

    $request = Request::create('/es/es/', 'GET', [], [], [], [
        'HTTP_CF_IPCOUNTRY' => 'US',
        'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
    ]);

    $recommendation = $geoService->getRecommendation($request);

    expect($recommendation['country'])->toBe('US');
    expect($recommendation['market']->code)->toBe('us');
    expect($recommendation['language']->code)->toBe('en');
});

test('first-time visitor is presented with the region and language preference modal', function (): void {
    $response = $this->get('/es/es/');
    $response->assertOk();
    $response->assertSee('Preferencias de Región');
    $response->assertSee('Confirmar y Continuar');
});

test('returning visitor keeps a hidden manual geo selector without an automatic prompt', function (): void {
    $response = $this->withCookie('cms_geo_preference', 'es_es')->get('/es/es/');
    $response->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $component = (new DOMXPath($document))->query('//*[@data-geo-selector]')->item(0);
    expect($component)->toBeInstanceOf(DOMElement::class);
    $configuration = json_decode($component->getAttribute('data-geo-selector'), true, flags: JSON_THROW_ON_ERROR);
    expect($configuration['autoShow'])->toBeFalse();
});

test('post to /geo/set-preference sets persistent 1-year cookie and redirects', function (): void {
    $response = $this->post('/geo/set-preference', [
        'market' => 'us',
        'locale' => 'en',
    ]);

    $response->assertRedirect('/us/en/');
    $response->assertPlainCookie('cms_geo_preference', 'us_en');
});
