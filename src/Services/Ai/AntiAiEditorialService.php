<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Ai;

class AntiAiEditorialService
{
    /**
     * Curated dictionary of common robotic AI markers and cliché phrases by locale.
     *
     * @var array<string, array<int, string>>
     */
    protected array $localizedAiCliches = [
        'es' => [
            'en el vertiginoso mundo',
            'en la era digital actual',
            'en la era digital',
            'un tapiz de',
            'un faro de',
            'es crucial destacar',
            'es fundamental destacar',
            'es importante destacar que',
            'es crucial recordar que',
            'es importante tener en cuenta que',
            'en conclusión',
            'en resumidas cuentas',
            'a modo de conclusión',
            'adentrémonos en',
            'acompáñame a descubrir',
            'acompáñame en este viaje',
            'juega un papel crucial',
            'juega un papel fundamental',
            'sin lugar a dudas',
            'sin duda alguna',
            'sinergia transformadora',
            'palanca de cambio',
            'un testimonio de',
            'un recordatorio de que',
            'paisaje en constante evolución',
            'no es de extrañar que',
            'cabe resaltar que',
            'cabe destacar que',
            'revolucionar la forma en que',
            'un antes y un después',
            'desentrañar los secretos',
            'abrir un abanico de posibilidades',
            'en este artículo exploraremos',
            'en las siguientes líneas',
            'a continuación te mostramos',
        ],
        'en' => [
            'in today\'s fast-paced world',
            'in today\'s fast paced world',
            'in the digital age',
            'in the modern era',
            'delve into',
            'delves into',
            'delving into',
            'a testament to',
            'tapestry of',
            'rich tapestry',
            'beacon of hope',
            'beacon of',
            'game-changer',
            'game changer',
            'ever-evolving landscape',
            'evolving landscape',
            'harness the power of',
            'harnessing the power',
            'foster',
            'unravel the secrets',
            'unravel',
            'in conclusion',
            'to sum up',
            'it is crucial to note',
            'it is important to remember',
            'without a doubt',
            'plethora of',
            'realm of',
            'at the forefront of',
            'shed light on',
            'pave the way for',
            'embark on a journey',
            'dive deep into',
            'unlock the full potential',
        ],
        'fr' => [
            'dans un monde en constante évolution',
            'dans le monde effréné de',
            'à l\'ère du numérique',
            'il est crucial de noter',
            'il est important de souligner',
            'en conclusion',
            'en somme',
            'force est de constater',
            'joue un rôle primordial',
            'joue un rôle crucial',
            'sans l\'ombre d\'un doute',
            'véritable révolution',
            'au cœur de',
            'ouvrir le champ des possibles',
            'plonger au cœur de',
            'témoignage de',
            'levier de transformation',
        ],
        'de' => [
            'in der heutigen schnelllebigen welt',
            'im zeitalter der digitalisierung',
            'es ist wichtig zu beachten',
            'es ist von entscheidender bedeutung',
            'zusammenfassend lässt sich sagen',
            'abschließend lässt sich sagen',
            'spielt eine entscheidende rolle',
            'spielt eine fundamentale rolle',
            'ohne zweifel',
            'wegweisend',
            'ein meilenstein',
            'die weichen stellen',
            'ein zeugnis für',
            'einen tiefen einblick gewähren',
            'in diesem artikel werfen wir einen blick auf',
        ],
    ];

    /**
     * Get cliches list for a given locale.
     *
     * @return array<int, string>
     */
    public function getClichesForLocale(string $locale = 'es'): array
    {
        $lang = strtolower(substr(trim($locale), 0, 2));

        return $this->localizedAiCliches[$lang] ?? $this->localizedAiCliches['es'];
    }

    /**
     * Evaluates text against anti-AI heuristics: burstiness, clichés, title casing, and punctuation.
     *
     * @return array{
     *     anti_ai_score: int,
     *     is_human_grade: bool,
     *     burstiness: array{
     *         sentence_count: int,
     *         avg_words_per_sentence: float,
     *         std_dev_words: float,
     *         is_bursty: bool
     *     },
     *     cliches_found: array<int, string>,
     *     title_evaluation: array{
     *         is_valid_sentence_case: bool,
     *         suggested_title: string,
     *         issue: string|null
     *     },
     *     punctuation_evaluation: array{
     *         missing_inverted_marks: int,
     *         has_spanish_punctuation: bool
     *     },
     *     recommendations: array<int, string>
     * }
     */
    public function validateEditorialTone(string $title, string $content, string $locale = 'es'): array
    {
        $lang = strtolower(substr(trim($locale), 0, 2));
        $plainText = strip_tags($content);
        $score = 100;
        $recommendations = [];

        // 1. Cliché and AI Marker Analysis (locale-aware)
        $clichesFound = [];
        $lowerText = mb_strtolower($plainText.' '.$title);
        $activeCliches = $this->getClichesForLocale($lang);

        foreach ($activeCliches as $cliche) {
            if (str_contains($lowerText, mb_strtolower($cliche))) {
                $clichesFound[] = $cliche;
            }
        }

        if (! empty($clichesFound)) {
            $penalty = min(40, count($clichesFound) * 12);
            $score -= $penalty;
            $recommendations[] = "Se detectaron expresiones clichés típicas de IA para el idioma [{$lang}]: \"".implode('", "', array_slice($clichesFound, 0, 5)).'". Sustitúyelas por giros más naturales y directos.';
        }

        // 2. Burstiness (Sentence Length Variance) Analysis
        $burstiness = $this->calculateBurstiness($plainText);
        if ($burstiness['sentence_count'] >= 3) {
            if ($burstiness['std_dev_words'] < 6.0) {
                $score -= 20;
                $recommendations[] = 'La cadencia del texto es muy uniforme (desviación de palabras: '.$burstiness['std_dev_words'].'). Alterna frases muy breves y contundentes (4-8 palabras) con frases compuestas explicativas (20-30 palabras) para lograr un ritmo humano natural.';
            } elseif ($burstiness['std_dev_words'] < 7.5) {
                $score -= 10;
                $recommendations[] = 'Aumenta la variabilidad rítmica entre oraciones cortas y largas para evitar monotonía sintética.';
            }
        }

        // 3. Title Casing (Only enforce Spanish Sentence Case when locale is 'es')
        if ($lang === 'es') {
            $titleEval = $this->evaluateTitleCasing($title);
            if (! $titleEval['is_valid_sentence_case']) {
                $score -= 15;
                $recommendations[] = 'El título contiene mayúsculas de estilo anglosajón. En castellano solo debe llevar mayúscula la primera letra y los nombres propios.';
            }
        } else {
            $titleEval = [
                'is_valid_sentence_case' => true,
                'suggested_title' => $title,
                'issue' => null,
            ];
        }

        // 4. Spanish Inverted Punctuation (¿? y ¡!) (Only enforce for 'es')
        if ($lang === 'es') {
            $punctEval = $this->evaluateSpanishPunctuation($plainText);
            if ($punctEval['missing_inverted_marks'] > 0) {
                $score -= 10;
                $recommendations[] = "Se encontraron {$punctEval['missing_inverted_marks']} oraciones interrogativas o exclamativas sin los signos de apertura reglamentarios en español (¿ o ¡).";
            }
        } else {
            $punctEval = [
                'missing_inverted_marks' => 0,
                'has_spanish_punctuation' => true,
            ];
        }

        // 5. Monotonic Bold Bullets Detection
        if (preg_match_all('/(\*|-|\d+\.)\s*\*\*[^*]+\*\*:/u', $content) > 4) {
            $score -= 10;
            $recommendations[] = 'Existe un exceso de listas con viñetas en negrita repetitivas (**Concepto:** definición). Integra algunos puntos en párrafos fluidos.';
        }

        $finalScore = max(0, min(100, $score));

        return [
            'anti_ai_score' => $finalScore,
            'is_human_grade' => $finalScore >= 85 && empty($clichesFound),
            'burstiness' => $burstiness,
            'cliches_found' => $clichesFound,
            'title_evaluation' => $titleEval,
            'punctuation_evaluation' => $punctEval,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Calculates sentence length variance and standard deviation.
     *
     * @return array{sentence_count: int, avg_words_per_sentence: float, std_dev_words: float, is_bursty: bool}
     */
    public function calculateBurstiness(string $text): array
    {
        $rawSentences = preg_split('/[.!?]+[\s\r\n]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $sentences = is_array($rawSentences) ? array_filter(array_map('trim', $rawSentences)) : [];

        $count = count($sentences);
        if ($count === 0) {
            return [
                'sentence_count' => 0,
                'avg_words_per_sentence' => 0.0,
                'std_dev_words' => 0.0,
                'is_bursty' => false,
            ];
        }

        $wordCounts = array_map(fn (string $s) => str_word_count($s), $sentences);
        $avg = array_sum($wordCounts) / $count;

        if ($count === 1) {
            return [
                'sentence_count' => 1,
                'avg_words_per_sentence' => round($avg, 2),
                'std_dev_words' => 0.0,
                'is_bursty' => false,
            ];
        }

        $variance = array_sum(array_map(fn (int $w) => pow($w - $avg, 2), $wordCounts)) / ($count - 1);
        $stdDev = sqrt($variance);

        return [
            'sentence_count' => $count,
            'avg_words_per_sentence' => round($avg, 2),
            'std_dev_words' => round($stdDev, 2),
            'is_bursty' => $stdDev >= 8.0,
        ];
    }

    /**
     * Evaluates whether a title adheres to Spanish sentence case conventions.
     *
     * @return array{is_valid_sentence_case: bool, suggested_title: string, issue: string|null}
     */
    public function evaluateTitleCasing(string $title): array
    {
        $words = preg_split('/\s+/u', trim($title), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($words) || count($words) <= 2) {
            return [
                'is_valid_sentence_case' => true,
                'suggested_title' => $title,
                'issue' => null,
            ];
        }

        $upperCount = 0;
        $totalWords = count($words);

        foreach (array_slice($words, 1) as $word) {
            $firstChar = mb_substr($word, 0, 1);
            if ($firstChar === mb_strtoupper($firstChar) && $firstChar !== mb_strtolower($firstChar)) {
                // If the word isn't a known acronym like IA, CMS, SEO, API, PHP
                $clean = trim($word, '.,:;()[]"\'');
                if (! in_array($clean, ['IA', 'CMS', 'SEO', 'GEO', 'API', 'PHP', 'HTML', 'CSS', 'JS', 'SQL', 'LTS', 'MCP'], true)) {
                    $upperCount++;
                }
            }
        }

        $isInvalidTitleCase = ($upperCount >= 2 && ($upperCount / ($totalWords - 1)) > 0.35);

        // Compute suggested Spanish sentence case title
        $suggested = mb_strtoupper(mb_substr($title, 0, 1)).mb_strtolower(mb_substr($title, 1));
        // Preserve acronyms in uppercase
        $acronyms = ['ia', 'cms', 'seo', 'geo', 'api', 'php', 'html', 'css', 'js', 'sql', 'lts', 'mcp'];
        foreach ($acronyms as $acr) {
            $suggested = preg_replace('/\b'.preg_quote($acr, '/').'\b/ui', mb_strtoupper($acr), $suggested) ?? $suggested;
        }

        return [
            'is_valid_sentence_case' => ! $isInvalidTitleCase,
            'suggested_title' => $suggested,
            'issue' => $isInvalidTitleCase ? 'El título utiliza mayúsculas estilo anglosajón en cada palabra.' : null,
        ];
    }

    /**
     * Checks if interrogative or exclamatory sentences include opening signs (¿ / ¡).
     *
     * @return array{missing_inverted_marks: int, has_spanish_punctuation: bool}
     */
    public function evaluateSpanishPunctuation(string $text): array
    {
        $questionMatches = 0;
        $exclamationMatches = 0;

        preg_match_all('/(?:^|[.!?\n]\s*)([^?]*\?)/u', $text, $qMatches);
        if (! empty($qMatches[1])) {
            foreach ($qMatches[1] as $clause) {
                if (! str_contains($clause, '¿')) {
                    $questionMatches++;
                }
            }
        }

        preg_match_all('/(?:^|[.!?\n]\s*)([^!]*\!)/u', $text, $eMatches);
        if (! empty($eMatches[1])) {
            foreach ($eMatches[1] as $clause) {
                if (! str_contains($clause, '¡')) {
                    $exclamationMatches++;
                }
            }
        }

        $totalMissing = $questionMatches + $exclamationMatches;

        return [
            'missing_inverted_marks' => $totalMissing,
            'has_spanish_punctuation' => $totalMissing === 0,
        ];
    }

    /**
     * Dictionary of replacements by locale for humanizing prose.
     *
     * @var array<string, array<string, string>>
     */
    protected array $localizedReplacements = [
        'es' => [
            'en el vertiginoso mundo de' => 'hoy en día en',
            'en el vertiginoso mundo del' => 'en el sector del',
            'en la era digital actual' => 'en el contexto tecnológico actual',
            'en la era digital' => 'actualmente',
            'un tapiz de' => 'un conjunto diverso de',
            'un faro de' => 'un referente de',
            'es crucial destacar que' => 'conviene subrayar que',
            'es fundamental destacar que' => 'es clave entender que',
            'es importante destacar que' => 'conviene notar que',
            'en conclusión,' => 'en definitiva,',
            'en conclusión' => 'para terminar,',
            'en resumidas cuentas,' => 'en resumen,',
            'a modo de conclusión,' => 'en última instancia,',
            'adentrémonos en' => 'analicemos',
            'acompáñame a descubrir' => 'veamos',
            'juega un papel crucial' => 'resulta determinante',
            'juega un papel fundamental' => 'es clave',
            'sin lugar a dudas,' => 'sin duda,',
            'sin duda alguna,' => 'con certeza,',
            'cabe resaltar que' => 'conviene precisar que',
            'cabe destacar que' => 'vale la pena notar que',
            'revolucionar la forma en que' => 'cambiar sustancialmente la forma en que',
            'abrir un abanico de posibilidades' => 'ofrecer múltiples opciones',
        ],
        'en' => [
            'in today\'s fast-paced world' => 'today',
            'in today\'s fast paced world' => 'today',
            'in the digital age' => 'currently',
            'delve into' => 'examine',
            'delves into' => 'examines',
            'delving into' => 'examining',
            'a testament to' => 'evidence of',
            'tapestry of' => 'combination of',
            'rich tapestry' => 'diverse mix',
            'beacon of hope' => 'positive reference',
            'game-changer' => 'major shift',
            'game changer' => 'significant advance',
            'ever-evolving landscape' => 'industry dynamics',
            'harness the power of' => 'use',
            'harnessing the power of' => 'using',
            'unravel the secrets of' => 'understand',
            'in conclusion,' => 'finally,',
            'to sum up,' => 'in summary,',
            'it is crucial to note that' => 'notably,',
            'it is important to remember that' => 'keep in mind that',
            'without a doubt,' => 'clearly,',
            'plethora of' => 'many',
            'unlock the full potential of' => 'maximize',
            'dive deep into' => 'explore',
        ],
        'fr' => [
            'dans un monde en constante évolution' => 'aujourd\'hui',
            'à l\'ère du numérique' => 'actuellement',
            'il est crucial de noter que' => 'il convient de souligner que',
            'en conclusion,' => 'en definitiva,',
            'joue un rôle primordial' => 'est déterminant',
            'sans l\'ombre d\'un doute,' => 'certainement,',
            'au cœur de' => 'au centre de',
        ],
        'de' => [
            'in der heutigen schnelllebigen welt' => 'heute',
            'im zeitalter der digitalisierung' => 'derzeit',
            'es ist wichtig zu beachten, dass' => 'bemerkenswert ist, dass',
            'zusammenfassend lässt sich sagen,' => 'schließlich,',
            'spielt eine entscheidende rolle' => 'ist ausschlaggebend',
            'ohne zweifel,' => 'sicherlich,',
        ],
    ];

    /**
     * Get humanization replacements for a given locale.
     *
     * @return array<string, string>
     */
    public function getReplacementsForLocale(string $locale = 'es'): array
    {
        $lang = strtolower(substr(trim($locale), 0, 2));

        return $this->localizedReplacements[$lang] ?? $this->localizedReplacements['es'];
    }

    /**
     * Rewrites or guides the humanization of a draft, replacing AI clichés with organic phrasing for the given locale.
     *
     * @return array{humanized_content: string, changes_applied: array<int, string>}
     */
    public function humanizeProse(string $content, string $locale = 'es', string $style = 'periodistico'): array
    {
        $lang = strtolower(substr(trim($locale), 0, 2));
        $replacements = $this->getReplacementsForLocale($lang);

        $changes = [];
        $result = $content;

        foreach ($replacements as $cliche => $natural) {
            if (stripos($result, $cliche) !== false) {
                $result = (string) preg_replace('/\b'.preg_quote($cliche, '/').'\b/ui', $natural, $result);
                $changes[] = "Sustituido cliché '{$cliche}' por '{$natural}' [{$lang}]";
            }
        }

        return [
            'humanized_content' => $result,
            'changes_applied' => $changes,
        ];
    }
}
