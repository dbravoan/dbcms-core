<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

class ContentQualityAuditorService
{
    /**
     * Audits content quality across classic SEO and Generative Engine Optimization (GEO) dimensions.
     *
     * @return array{
     *     score_kind: string,
     *     score_label: string,
     *     overall_score: int,
     *     seo_score: int,
     *     geo_score: int,
     *     metrics: array<string, mixed>,
     *     recommendations: array<int, string>
     * }
     */
    public function audit(string $title, string $content, string $metaDescription = '', string $primaryKeyword = ''): array
    {
        $plainText = strip_tags($content);
        $wordCount = preg_match_all('/[\p{L}\p{N}]+/u', $plainText) ?: 0;
        $titleLength = mb_strlen(trim($title));
        $metaLength = mb_strlen(trim($metaDescription));

        $recommendations = [];
        $seoScore = 100;
        $geoScore = 100;

        // 1. Title Assessment
        if ($titleLength < 35 || $titleLength > 65) {
            $seoScore -= 10;
            $recommendations[] = "Title length ({$titleLength} chars) should ideally be between 40 and 60 characters.";
        }

        // 2. Meta Description Assessment
        if ($metaLength < 100 || $metaLength > 165) {
            $seoScore -= 10;
            $recommendations[] = "Meta description ({$metaLength} chars) should ideally be between 120 and 160 characters.";
        }

        // 3. Word Count Assessment
        if ($wordCount < 300) {
            $seoScore -= 20;
            $geoScore -= 25;
            $recommendations[] = "Content is brief ({$wordCount} words). Check whether it fully answers the intended question; length alone does not establish quality or ranking.";
        }

        // 4. Keyword Density
        $keywordDensity = 0.0;
        if (! empty($primaryKeyword)) {
            $keywordCount = substr_count(mb_strtolower($plainText), mb_strtolower($primaryKeyword));
            $keywordDensity = $wordCount > 0 ? round(($keywordCount / $wordCount) * 100, 2) : 0;

            if ($keywordDensity < 0.5) {
                $seoScore -= 15;
                $recommendations[] = "Keyword '{$primaryKeyword}' appears rarely ({$keywordDensity}%). Check that the topic is clear; no universal density target applies.";
            } elseif ($keywordDensity > 3.5) {
                $seoScore -= 15;
                $recommendations[] = "Keyword '{$primaryKeyword}' density is high ({$keywordDensity}%). Avoid keyword stuffing.";
            }
        }

        // 5. Heading Structure (H1 / H2 / H3)
        $h2Count = preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $content);
        $h3Count = preg_match_all('/<h3[^>]*>(.*?)<\/h3>/i', $content);

        if ($h2Count === 0) {
            $seoScore -= 15;
            $geoScore -= 15;
            $recommendations[] = 'No H2 subheadings found. Add structured H2 sections to organize topics for crawlers and AI synthesizers.';
        }

        // 6. Generative Engine Optimization (GEO) - Citability & Factuality
        $hasFaqOrDefinitions = (bool) preg_match('/(faq|preguntas|qué es|definición|resumen|how to)/i', $content);
        if (! $hasFaqOrDefinitions) {
            $geoScore -= 15;
            $recommendations[] = 'Consider a concise definition or FAQ when it helps readers answer the intended question; no AI citation outcome is measured.';
        }

        // 7. Information Density Index
        $sentences = preg_split('/[.!?]+/', $plainText);
        $sentenceCount = is_array($sentences) ? count(array_filter($sentences)) : 1;
        $avgWordsPerSentence = $sentenceCount > 0 ? round($wordCount / $sentenceCount, 1) : 0;

        if ($avgWordsPerSentence > 30) {
            $geoScore -= 10;
            $recommendations[] = "Sentences are overly complex (avg {$avgWordsPerSentence} words/sentence). Consider splitting difficult sentences for readability; factual accuracy is not assessed by this heuristic.";
        }

        $seoScore = max(0, min(100, $seoScore));
        $geoScore = max(0, min(100, $geoScore));
        $overallScore = (int) round(($seoScore * 0.5) + ($geoScore * 0.5));

        return [
            'score_kind' => 'editorial_heuristic',
            'score_label' => 'Editorial heuristic; no ranking, factuality or citations measured',
            'overall_score' => $overallScore,
            'seo_score' => $seoScore,
            'geo_score' => $geoScore,
            'metrics' => [
                'word_count' => $wordCount,
                'title_length' => $titleLength,
                'meta_length' => $metaLength,
                'keyword_density' => $keywordDensity,
                'h2_subheadings' => $h2Count,
                'h3_subheadings' => $h3Count,
                'avg_words_per_sentence' => $avgWordsPerSentence,
                'citations_measured' => false,
            ],
            'recommendations' => $recommendations,
        ];
    }
}
