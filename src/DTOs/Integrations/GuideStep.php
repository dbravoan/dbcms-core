<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Integrations;

use InvalidArgumentException;

final readonly class GuideStep
{
    /** @param array<string,string> $links
     * @param  array<string,string>  $callbackParameters
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $externalAction,
        public string $cmsAction,
        public string $expectedResult,
        public array $links = [],
        public ?string $callbackRoute = null,
        public array $callbackParameters = [],
    ) {
        if (! preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/', $id) || trim($title) === '') {
            throw new InvalidArgumentException('Guide steps require a stable ID and title.');
        }
        self::validateLinks($links);
    }

    /** @param array<string,string> $links */
    public static function validateLinks(array $links): void
    {
        foreach ($links as $label => $url) {
            $parts = parse_url($url);
            if (trim($label) === '' || ! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
                || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                throw new InvalidArgumentException('Guide links must be labelled HTTPS documentation URLs.');
            }
        }
    }
}
