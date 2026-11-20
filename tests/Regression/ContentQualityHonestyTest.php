<?php

declare(strict_types=1);

namespace Tests\Regression;

use DbCms\Core\Services\Seo\ContentQualityAuditorService;
use PHPUnit\Framework\TestCase;

final class ContentQualityHonestyTest extends TestCase
{
    public function test_scores_are_editorial_estimates_without_observed_citations(): void
    {
        $audit = (new ContentQualityAuditorService)->audit('Título público', '<h2>Qué es</h2><p>Información verificable.</p>');
        self::assertSame('editorial_heuristic', $audit['score_kind']);
        self::assertFalse($audit['metrics']['citations_measured']);
        self::assertStringNotContainsString('rank better', implode(' ', $audit['recommendations']));
    }
}
