<?php

declare(strict_types=1);

use DbCms\Core\Services\Ai\AntiAiEditorialService;

beforeEach(function (): void {
    $this->service = new AntiAiEditorialService;
});

test('it detects common AI clichés in Spanish and decreases score', function (): void {
    $roboticText = 'En el vertiginoso mundo de la tecnología, es crucial destacar que la IA es una palanca de cambio. En conclusión, debemos adaptarnos.';
    $result = $this->service->validateEditorialTone('Guía Completa De Inteligencia Artificial', $roboticText);

    expect($result['is_human_grade'])->toBeFalse();
    expect($result['anti_ai_score'])->toBeLessThan(80);
    expect($result['cliches_found'])->toContain('en el vertiginoso mundo');
    expect($result['cliches_found'])->toContain('es crucial destacar');
    expect($result['cliches_found'])->toContain('en conclusión');
    expect($result['recommendations'])->not->toBeEmpty();
});

test('it approves natural, human-written text with varied burstiness and proper Spanish', function (): void {
    $title = 'La nueva regulación europea sobre inteligencia artificial';
    $naturalText = <<<'MD'
El Parlamento Europeo ha aprobado la nueva ley. Este marco regulatorio cambia las reglas del juego.
A diferencia de intentos anteriores donde las normas quedaban obsoletas antes de publicarse,
esta directiva introduce una categorización exhaustiva de riesgos que obligará a las empresas a auditar sus modelos.

¿Cómo afectará esto a las pymes en España? La respuesta no es sencilla, pero existen plazos de adaptación razonables.
MD;

    $result = $this->service->validateEditorialTone($title, $naturalText);

    expect($result['is_human_grade'])->toBeTrue();
    expect($result['anti_ai_score'])->toBeGreaterThanOrEqual(85);
    expect($result['cliches_found'])->toBeEmpty();
    expect($result['title_evaluation']['is_valid_sentence_case'])->toBeTrue();
    expect($result['punctuation_evaluation']['has_spanish_punctuation'])->toBeTrue();
});

test('it evaluates title casing and flags English Title Case in Spanish', function (): void {
    $invalidTitle = 'Guía Práctica Para Optimizar Tu Comercio Electrónico';
    $eval = $this->service->evaluateTitleCasing($invalidTitle);

    expect($eval['is_valid_sentence_case'])->toBeFalse();
    expect($eval['suggested_title'])->toBe('Guía práctica para optimizar tu comercio electrónico');

    $validTitle = 'Guía práctica para optimizar tu comercio electrónico con IA y SEO';
    $validEval = $this->service->evaluateTitleCasing($validTitle);
    expect($validEval['is_valid_sentence_case'])->toBeTrue();
});

test('it measures burstiness and flags robotic uniform sentence lengths', function (): void {
    // Uniform robotic sentences (all ~12 words)
    $uniform = 'El sistema procesa la información de forma rápida y eficiente todos los días. '
             .'Los desarrolladores implementan soluciones escalables para mejorar el rendimiento de la aplicación. '
             .'La base de datos responde con baja latencia durante las horas de tráfico intenso.';

    $burstiness = $this->service->calculateBurstiness($uniform);
    expect($burstiness['sentence_count'])->toBe(3);
    expect($burstiness['std_dev_words'])->toBeLessThan(6.0);
    expect($burstiness['is_bursty'])->toBeFalse();

    // Natural bursty text (mix of 4 words and 25 words)
    $bursty = 'Todo cambió de repente. Tras semanas de pruebas exhaustivas en el entorno de preproducción con cargas simuladas de diez mil usuarios concurrentes, el equipo descubrió una fuga de memoria en el controlador principal. Nadie lo esperaba.';

    $burstyMetric = $this->service->calculateBurstiness($bursty);
    expect($burstyMetric['std_dev_words'])->toBeGreaterThanOrEqual(8.0);
    expect($burstyMetric['is_bursty'])->toBeTrue();
});

test('it humanizes prose by substituting recognized AI clichés with natural Spanish phrasing', function (): void {
    $draft = 'En el vertiginoso mundo de la ciberseguridad, es crucial destacar que las amenazas crecen. En conclusión, debemos protegernos.';
    $result = $this->service->humanizeProse($draft);

    expect($result['humanized_content'])->not->toContain('en el vertiginoso mundo de');
    expect($result['humanized_content'])->not->toContain('es crucial destacar que');
    expect($result['humanized_content'])->not->toContain('en conclusión,');
    expect($result['changes_applied'])->not->toBeEmpty();
});

test('it detects common AI clichés in English and does not penalize English punctuation', function (): void {
    $title = 'A Guide to Enterprise Cloud Architecture';
    $roboticEnglish = "In today's fast-paced world, it is crucial to note that we must delve into the rich tapestry of modern microservices. In conclusion, this is a game-changer.";
    $result = $this->service->validateEditorialTone($title, $roboticEnglish, 'en');

    expect($result['is_human_grade'])->toBeFalse();
    expect($result['anti_ai_score'])->toBeLessThan(80);
    expect($result['cliches_found'])->toContain("in today's fast-paced world");
    expect($result['cliches_found'])->toContain('delve into');
    expect($result['cliches_found'])->toContain('tapestry of');
    // English punctuation does NOT require ¿ or ¡
    expect($result['punctuation_evaluation']['missing_inverted_marks'])->toBe(0);
    expect($result['punctuation_evaluation']['has_spanish_punctuation'])->toBeTrue();
    expect($result['title_evaluation']['is_valid_sentence_case'])->toBeTrue();
});

test('it approves natural, human-written English text', function (): void {
    $title = 'Understanding the European Union AI Act';
    $naturalEnglish = <<<'MD'
The European Parliament approved the new rules. This regulatory framework changes everything.
Unlike earlier proposals where technical standards fell out of date before publication, this directive establishes a rigorous risk taxonomy that requires foundation model developers to perform continuous audits and disclose data sources.

How will this affect startups? Immediate clarity remains scarce, but transitional compliance grace periods will ease the shift.
MD;

    $result = $this->service->validateEditorialTone($title, $naturalEnglish, 'en');

    expect($result['is_human_grade'])->toBeTrue();
    expect($result['anti_ai_score'])->toBeGreaterThanOrEqual(85);
    expect($result['cliches_found'])->toBeEmpty();
    expect($result['punctuation_evaluation']['missing_inverted_marks'])->toBe(0);
});

test('it humanizes English prose by replacing robotic clichés with natural phrasing', function (): void {
    $draft = "In today's fast-paced world, we must delve into cloud infrastructure. This tool is a testament to clean architecture. In conclusion, simplicity wins.";
    $result = $this->service->humanizeProse($draft, 'en');

    expect($result['humanized_content'])->not->toContain("in today's fast-paced world");
    expect($result['humanized_content'])->not->toContain('delve into');
    expect($result['humanized_content'])->not->toContain('a testament to');
    expect($result['humanized_content'])->toContain('today');
    expect($result['humanized_content'])->toContain('examine');
    expect($result['humanized_content'])->toContain('evidence of');
    expect($result['changes_applied'])->not->toBeEmpty();
});
