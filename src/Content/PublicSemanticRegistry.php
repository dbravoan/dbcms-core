<?php

declare(strict_types=1);

namespace DbCms\Core\Content;

use DbCms\Core\Blocks\Contracts\BlockRegistryInterface;
use DbCms\Core\Content\Contracts\PublicSemanticContributorInterface;
use DbCms\Core\Content\Contracts\PublicUrlContributorInterface;
use DbCms\Core\Content\Contracts\PublicWidgetSemanticContributorInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Modules\ModuleManager;
use InvalidArgumentException;

final class PublicSemanticRegistry
{
    /** @var array<string,PublicSemanticContributorInterface> */
    private array $contributors = [];

    /** @var array<string,PublicWidgetSemanticContributorInterface> */
    private array $widgets = [];

    public function __construct(private readonly ModuleManager $modules, private readonly BlockRegistryInterface $blocks) {}

    /** @var array<string, PublicUrlContributorInterface> */
    private array $urlContributors = [];

    public function registerUrls(PublicUrlContributorInterface $contributor): void
    {
        $key = $contributor::class;
        if (isset($this->urlContributors[$key])) {
            throw new InvalidArgumentException('Duplicate public URL contributor.');
        }
        $this->urlContributors[$key] = $contributor;
    }

    /** @return array<string, string> */
    public function urls(Market $market, string $locale): array
    {
        if (! $this->contextIsPublic($market, $locale)) {
            return [];
        }
        $urls = [];
        foreach ($this->urlContributors as $contributor) {
            if ($this->modules->isModuleEnabled($contributor->ownerModule())) {
                $urls += $contributor->urls($market, $locale);
            }
        }

        return $urls;
    }

    public function register(PublicSemanticContributorInterface $contributor): void
    {
        if (isset($this->contributors[$contributor->key()])) {
            throw new InvalidArgumentException('Duplicate public semantic contributor.');
        }
        $this->contributors[$contributor->key()] = $contributor;
    }

    public function registerWidget(PublicWidgetSemanticContributorInterface $contributor): void
    {
        foreach ($contributor->keys() as $key) {
            if (isset($this->widgets[$key])) {
                throw new InvalidArgumentException('Duplicate public widget semantic contributor.');
            }
            $this->widgets[$key] = $contributor;
        }
    }

    public function index(Market $market, string $locale): string
    {
        return $this->collect($market, $locale, false);
    }

    public function corpus(Market $market, string $locale): string
    {
        return $this->collect($market, $locale, true);
    }

    /** @param array<string,mixed> $payload */
    public function widgetText(array $payload, Market $market, string $locale): string
    {
        $key = $payload['type'] ?? '';
        if (! is_string($key) || ! $this->blocks->isAvailable($key)) {
            return '';
        }
        $widget = $this->widgets[$key] ?? null;
        if ($widget === null || ! $this->contextIsPublic($market, $locale)) {
            return '';
        }

        return $this->modules->isModuleEnabled($widget->ownerModule()) ? $widget->text($payload, $market, $locale) : '';
    }

    private function collect(Market $market, string $locale, bool $full): string
    {
        if (! $this->contextIsPublic($market, $locale)) {
            return '';
        }
        $output = '';
        foreach ($this->contributors as $contributor) {
            if ($this->modules->isModuleEnabled($contributor->ownerModule())) {
                $output .= $full ? $contributor->corpus($market, $locale) : $contributor->index($market, $locale);
            }
        }

        return $output;
    }

    private function contextIsPublic(Market $market, string $locale): bool
    {
        return $market->is_active && $market->languages()->where('code', $locale)->where('is_active', true)->exists();
    }
}
