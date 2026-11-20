<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Media;

use Illuminate\Validation\ValidationException;

final class CaptionDocument
{
    public function validate(string $content): void
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        if (strlen($content) > 1048576 || ! mb_check_encoding($content, 'UTF-8')
            || ! preg_match('/\AWEBVTT(?:[ \t][^\r\n]*)?\r?\n/', $content)
            || str_contains($content, "\0") || preg_match('/(?:^|\n)STYLE(?:[ \t\r\n]|$)/', $content)) {
            throw ValidationException::withMessages(['file' => 'Usa subtítulos WebVTT UTF-8, de hasta 1 MB y sin hojas de estilo.']);
        }
    }
}
