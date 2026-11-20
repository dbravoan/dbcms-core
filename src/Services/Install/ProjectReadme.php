<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Install;

final class ProjectReadme
{
    private const PENDING = <<<'MARKDOWN'
        <!-- dbcms:site:pending -->
        La identidad del sitio se añadirá aquí al completar la instalación.
        <!-- /dbcms:site -->
        MARKDOWN;

    private const COMPLETED = '<!-- dbcms:site:configured -->';

    /** Missing or customized documentation is a successful no-op. */
    public function complete(string $path, string $siteName, string $holder): bool
    {
        if (is_link($path) || ! is_file($path)) {
            return true;
        }
        $original = @file_get_contents($path);
        if ($original === false) {
            return false;
        }
        $pending = $this->pendingBlock($original);
        if ($pending === null) {
            return true;
        }

        $identity = implode(str_contains($pending, "\r\n") ? "\r\n" : "\n", [
            self::COMPLETED,
            '## Identidad del sitio',
            '',
            '- **Nombre del sitio** — '.$this->literal($siteName),
            '- **Titular** — '.$this->literal($holder),
            '<!-- /dbcms:site -->',
        ]);
        $updated = str_replace($pending, $identity, $original);

        return $this->replace($path, $original, $updated);
    }

    public function isPending(string $path): bool
    {
        if (is_link($path) || ! is_file($path)) {
            return false;
        }
        $contents = @file_get_contents($path);

        return $contents === false || $this->pendingBlock($contents) !== null;
    }

    private function pendingBlock(string $contents): ?string
    {
        if (str_contains($contents, self::COMPLETED) || substr_count($contents, '<!-- dbcms:site:pending -->') !== 1) {
            return null;
        }
        if (str_contains($contents, self::PENDING)) {
            return self::PENDING;
        }
        $crlf = str_replace("\n", "\r\n", self::PENDING);

        return str_contains($contents, $crlf) ? $crlf : null;
    }

    private function replace(string $path, string $original, string $updated): bool
    {
        $directory = dirname($path);
        $mode = @fileperms($path);
        if ($mode === false || ($mode & 0222) === 0 || ! is_writable($path) || ! is_writable($directory)) {
            return false;
        }
        $temporary = @tempnam($directory, '.dbcms-readme-');
        if ($temporary === false) {
            return false;
        }
        try {
            if (realpath(dirname($temporary)) !== realpath($directory)
                || @file_put_contents($temporary, $updated) !== strlen($updated)
                || ! @chmod($temporary, $mode & 0777)) {
                return false;
            }
            clearstatcache(true, $path);
            if (is_link($path) || ! is_file($path) || @file_get_contents($path) !== $original) {
                return false;
            }

            return @rename($temporary, $path);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function literal(string $name): string
    {
        $line = trim(preg_replace('/[\p{Cc}\p{Zl}\p{Zp}]+/u', ' ', $name) ?? '');

        // Character references prevent Markdown, HTML and autolinks in the identity.
        return preg_replace_callback('/[&*:<>@\x5b-\x5d_\x60|~]|(?<=\bwww)\./iu', static fn (array $match): string => '&#'.ord($match[0]).';', $line) ?? '';
    }
}
