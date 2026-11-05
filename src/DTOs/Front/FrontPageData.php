<?php

declare(strict_types=1);

namespace DbCms\Core\DTOs\Front;

/** Transport-neutral public view, canonical redirect or missing-resource outcome. */
final readonly class FrontPageData
{
    /**
     * @param  view-string|null  $viewName
     * @param  array<string,mixed>  $data
     */
    private function __construct(public ?string $viewName, public array $data, public ?string $redirectUrl, public int $redirectStatus = 301) {}

    /**
     * @param  view-string  $name
     * @param  array<string,mixed>  $data
     */
    public static function view(string $name, array $data): self
    {
        return new self($name, $data, null);
    }

    /** @param array<string,string> $flash */
    public static function redirect(string $url, int $status = 301, array $flash = []): self
    {
        return new self(null, $flash, $url, $status);
    }

    public static function missing(): self
    {
        return new self(null, [], null);
    }
}
