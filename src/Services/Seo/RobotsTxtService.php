<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

final class RobotsTxtService
{
    public function generate(): string
    {
        $base = rtrim((string) config('app.url', 'http://localhost'), '/');
        $isProduction = app()->isProduction();

        $lines = ['User-agent: *'];

        if (! $isProduction) {
            // Staging/preview deployments must never be indexed.
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Disallow: /'.trim((string) config('cms.admin_path', 'admin'), '/').'/';
            $lines[] = 'Disallow: /install';
            $lines[] = 'Disallow: /api/';
            $lines[] = 'Allow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$base.'/sitemap.xml';
        $lines[] = 'LLMs: '.$base.'/llms.txt';

        return implode("\n", $lines)."\n";
    }
}
