<?php

declare(strict_types=1);

namespace DbCms\Core\Content\Pages\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Validation\ValidationException;

/** Only developer-registered names can select executable Blade templates. */
final class PageViewRegistry
{
    /** @var array<string,string> */
    private array $core = [];

    public function __construct(private readonly Repository $config, private readonly Factory $views) {}

    public function registerCore(string $key, string $view): void
    {
        $this->assertEntry($key, $view);
        if (isset($this->core[$key])) {
            throw new \LogicException('Duplicate core page view: '.$key);
        }
        $this->core[$key] = $view;
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $project = $this->config->get('cms.page_views', []);
        if (! is_array($project)) {
            throw new \LogicException('cms.page_views must map page keys to Blade view names.');
        }
        $registered = $this->core;
        foreach ($project as $key => $view) {
            if (! is_string($key) || ! is_string($view)) {
                throw new \LogicException('Page view keys and names must be strings.');
            }
            $this->assertEntry($key, $view);
            if (isset($this->core[$key])) {
                throw new \LogicException('A project cannot replace a reserved page view: '.$key);
            }
            $registered[$key] = $view;
        }

        return $registered;
    }

    public static function isBlade(string $template): bool
    {
        return str_starts_with($template, 'blade:');
    }

    /** @return view-string|null */
    public function resolve(string $template): ?string
    {
        if (! self::isBlade($template)) {
            return null;
        }
        $key = substr($template, 6);
        $view = $this->all()[$key] ?? null;
        if ($view === null || ! $this->views->exists($view)) {
            throw ValidationException::withMessages(['template' => 'This registered page view is unavailable: '.$key]);
        }

        return $view;
    }

    private function assertEntry(string $key, string $view): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{0,93}$/D', $key) || ! preg_match('/^[A-Za-z0-9_-]+(?:::[A-Za-z0-9_-]+)?(?:\.[A-Za-z0-9_-]+)*$/D', $view)) {
            throw new \LogicException('Invalid registered page key or Blade view name.');
        }
    }
}
