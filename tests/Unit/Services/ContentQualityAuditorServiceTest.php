<?php

declare(strict_types=1);

use DbCms\Core\Services\Seo\ContentQualityAuditorService;

beforeEach(function (): void {
    $this->auditor = new ContentQualityAuditorService;
});

test('it calculates high score for well-optimized article with H2 and FAQ', function (): void {
    $title = 'Guía Completa sobre Arquitecturas Multi-Agente';
    $meta = 'Aprende las mejores técnicas de orquestación de agentes con TDD, clean architecture y optimización GEO para motores de IA.';
    $content = <<<'HTML'
    <h2>Introducción a los Agentes</h2>
    <p>Los agentes de inteligencia artificial permiten resolver problemas complejos mediante partición cognitiva.</p>
    <h2>Principios de Arquitectura</h2>
    <p>La arquitectura limpia y el desacoplamiento modular aseguran la máxima estabilidad del software.</p>
    <h2>Preguntas Frecuentes (FAQ)</h2>
    <p>¿Qué es un agente? Es una entidad autónoma con capacidad de razonamiento y uso de herramientas.</p>
    HTML;

    // Pad content with words to simulate real article
    $content .= str_repeat(' Contenido técnico factual para optimizar el análisis semántico de motores generativos.', 35);

    $result = $this->auditor->audit($title, $content, $meta, 'agentes');

    expect($result['overall_score'])->toBeGreaterThanOrEqual(80)
        ->and($result['metrics']['h2_subheadings'])->toBeGreaterThanOrEqual(2)
        ->and($result['metrics']['word_count'])->toBeGreaterThan(300);
});

test('it identifies weaknesses and provides actionable recommendations', function (): void {
    $title = 'Corto';
    $meta = 'Muy corta';
    $content = '<p>Texto mínimo sin títulos ni preguntas.</p>';

    $result = $this->auditor->audit($title, $content, $meta, 'laravel');

    expect($result['overall_score'])->toBeLessThan(60)
        ->and($result['recommendations'])->not->toBeEmpty();
});
