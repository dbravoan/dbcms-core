<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Seo;

use LengthException;

final class SitemapDocumentPartitioner
{
    public const MAX_URLS = 50000;

    public const MAX_BYTES = 52428800;

    private const HEADER = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

    private const FOOTER = '</urlset>';

    /**
     * @param  iterable<string>  $entries
     * @return list<string>
     */
    public function partition(iterable $entries, int $maxUrls = self::MAX_URLS, int $maxBytes = self::MAX_BYTES): array
    {
        $maxUrls = min(self::MAX_URLS, max(1, $maxUrls));
        $maxBytes = min(self::MAX_BYTES, $maxBytes);
        $documents = [];
        $body = '';
        $count = 0;
        $overhead = strlen(self::HEADER.self::FOOTER);
        if ($maxBytes < $overhead) {
            throw new LengthException('The sitemap byte limit cannot fit an empty XML document.');
        }
        foreach ($entries as $entry) {
            if (strlen($entry) + $overhead > $maxBytes) {
                throw new LengthException('One sitemap entry exceeds the uncompressed document limit.');
            }
            if ($count >= $maxUrls || strlen($body) + strlen($entry) + $overhead > $maxBytes) {
                $documents[] = self::HEADER.$body.self::FOOTER;
                $body = '';
                $count = 0;
            }
            $body .= $entry;
            $count++;
        }
        if ($count > 0 || $documents === []) {
            $documents[] = self::HEADER.$body.self::FOOTER;
        }

        return $documents;
    }
}
