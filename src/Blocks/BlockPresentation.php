<?php

declare(strict_types=1);

namespace DbCms\Core\Blocks;

use DbCms\Core\Sections\SectionAppearance;
use DbCms\Core\Services\Content\SafeContentRenderer;
use DbCms\Core\Services\Privacy\ExternalContentPolicy;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

/** Shared owned surfaces for sections, columns, nested nodes and Blade blocks. */
final class BlockPresentation
{
    /** @param array<string,mixed> $payload
     * @param array<string,string|int> $attributes */
    public function render(string $html, array $payload, string $kind = 'block', array $attributes = [], ?string $id = null): string
    {
        $payload = SectionAppearance::sanitize($payload);
        $id ??= 'cms-presentation-'.Str::uuid();
        $selector = '#'.$id;
        $content = $selector.' > [data-cms-presentation-content]';
        $type = (string) ($attributes['data-cms-type'] ?? $payload['type'] ?? '');
        if ($kind === 'section' && ! empty($payload['columns'])) {
            $type = ''; // A legacy row type does not turn its columns into a drawer.
        }
        $visualContent = $content;
        if ($kind === 'section' && empty($payload['columns'])) {
            $visualContent .= ' > [data-cms-presentation="block"] > [data-cms-presentation-content]';
        }
        $visual = $visualContent.($type === 'drawer' ? ' > [data-cms-overlay] > .cms-drawer-panel' : ' > :first-child');
        if ($kind === 'column') {
            $visual = $content;
        }
        $media = $selector.' > [data-cms-presentation-media] :is(img,video)';
        if ($type === 'image') {
            $media .= ','.$visualContent.' > .cms-image > img,'.$visualContent.' > .cms-image > a > img';
        } elseif ($type === 'video') {
            $media .= ','.$visualContent.' > div > div > video';
            $media .= ','.$visualContent.' > [data-cms-consent] > [data-consent-content] > div > div > video';
        }
        $appearance = (array) ($payload['appearance'] ?? []);
        $surface = $type === 'drawer' ? $visual : $selector;
        if ($type === 'drawer') {
            $content = $surface.' > [data-cms-presentation-content]';
            $visual = $content;
            $media = $surface.' > [data-cms-presentation-media] :is(img,video)';
        }
        $css = $this->legacyCss($surface, $content, $payload).BlockStyle::stylesheet($surface, $appearance, $visual, $media);
        $extent = ($appearance['extent'] ?? 'theme') === 'viewport' ? 'viewport' : 'theme';
        $classes = ['cms-presentation', 'cms-'.$kind, 'cms-extent-'.$extent];
        if ($kind === 'block') {
            $classes[] = 'cms-block-instance';
        }
        $container = $payload['container_style'] ?? '';
        if (in_array($container, ['cms-card', 'cyber-card', 'cyber-glass', 'neon-glow', 'full-width-dark'], true)) {
            $classes[] = match ($container) {
                'cyber-glass' => 'cms-card cms-presentation-glass',
                'neon-glow' => 'cms-card cms-presentation-glow',
                'full-width-dark' => 'cms-surface-2',
                default => 'cms-card',
            };
        }
        $attributes = ['id' => $id, 'class' => trim(implode(' ', $classes).' '.($attributes['class'] ?? '')), 'data-cms-presentation' => $kind, 'data-cms-extent' => $extent] + $attributes;
        $attributes += $type === 'drawer' ? [] : $this->motionAttributes($payload);
        $attrs = '';
        foreach ($attributes as $key => $value) {
            $attrs .= ' '.$key.'="'.$this->escape((string) $value).'"';
        }
        $style = $css === '' ? '' : '<style nonce="'.$this->escape((string) Vite::cspNonce()).'">'.$css.'</style>';
        $tag = $kind === 'section' ? 'section' : 'div';
        $mediaHtml = $type === 'drawer' ? '' : $this->mediaMarkup($payload);
        $before = $this->divider('top', (array) ($payload['divider_top'] ?? []));
        $after = $this->divider('bottom', (array) ($payload['divider_bottom'] ?? []));

        return $style.'<'.$tag.$attrs.'>'.$mediaHtml.$before.'<div data-cms-presentation-content data-cms-presentation-entrance data-cms-style-content>'.$html.'</div>'.$after.'</'.$tag.'>';
    }

    /** @param array<string,mixed> $payload
     * @return array<string,string> */
    public function motionAttributes(array $payload): array
    {
        $payload = SectionAppearance::sanitize($payload);
        $attributes = [];
        $motion = (array) ($payload['scroll_magic'] ?? []);
        $effect = $payload['animation'] ?? $motion['behavior'] ?? 'none';
        $scrub = $motion['scrub'] ?? 'none';
        if (($payload['bg_type'] ?? '') === 'image' && ($payload['bg_behavior'] ?? '') === 'parallax') {
            $scrub = 'parallax';
        }
        if ($effect !== 'none') {
            $attributes += ['data-scroll-magic' => (string) $effect, 'data-scroll-effect' => (string) $effect, 'data-scroll-delay' => (string) ($payload['animation_delay'] ?? $motion['delay'] ?? '0s'), 'data-scroll-duration' => (string) ($payload['animation_duration'] ?? $motion['duration'] ?? '1s')];
        }
        if ($scrub !== 'none') {
            $attributes['data-scroll-scrub'] = (string) $scrub;
        }
        if (($payload['bg_type'] ?? '') === 'video') {
            $attributes['data-scroll-video'] = 'playback';
        }

        return $attributes;
    }

    /** Legacy values only generate declarations when explicitly authored.
     * @param array<string,mixed> $payload */
    private function legacyCss(string $selector, string $content, array $payload): string
    {
        $declarations = '';
        if (! empty($payload['bg_color'])) {
            $declarations .= 'background-color:'.$payload['bg_color'].';';
        }
        if (isset($payload['padding']) && $payload['padding'] !== '') {
            $declarations .= 'padding:'.$payload['padding'].';';
        }
        foreach (['top', 'bottom'] as $side) {
            $border = (array) ($payload['border_'.$side] ?? []);
            if (! empty($border['enabled'])) {
                $declarations .= 'border-'.$side.':'.($border['width'] ?? '1px').' '.($border['style'] ?? 'solid').' '.($border['color'] ?? 'var(--cms-accent)').';';
            }
        }
        $css = $declarations === '' ? '' : $selector.'{'.$declarations.'}';
        $width = match ($payload['content_width'] ?? '') {
            'narrow' => 'max-width:56rem;margin-inline:auto;',
            'centered' => 'max-width:80rem;margin-inline:auto;',
            'full' => 'width:100%;max-width:none;',
            'custom' => empty($payload['content_max_width']) ? '' : 'width:100%;max-width:'.$payload['content_max_width'].';margin-inline:auto;',
            default => '',
        };

        return $css.($width === '' ? '' : $content.'{'.$width.'}');
    }

    /** @param array<string,mixed> $payload */
    public function mediaMarkup(array $payload): string
    {
        $payload = SectionAppearance::sanitize($payload);
        $type = (string) ($payload['bg_type'] ?? 'none');
        if (! in_array($type, ['image', 'video'], true)) {
            return '';
        }
        $url = (string) ($payload[$type === 'image' ? 'bg_image_url' : 'bg_video_url'] ?? '');
        $poster = $type === 'video' ? (string) ($payload['bg_video_poster'] ?? '') : '';
        if ($url === '') {
            return '';
        }
        $policy = app(ExternalContentPolicy::class);
        $origins = null;
        $native = function (string $candidate) use ($policy, &$origins): bool {
            if ($candidate === '') {
                return true;
            }
            if (! app(SafeContentRenderer::class)->isSafeUrl($candidate, true)) {
                return false;
            }
            $host = parse_url($candidate, PHP_URL_HOST);
            if (! is_string($host)) {
                return ! str_starts_with($candidate, '//') && preg_match('/^[a-z][a-z0-9+.-]*:/i', $candidate) !== 1;
            }
            $parts = parse_url($candidate);
            if (isset($parts['user']) || isset($parts['pass']) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
                return false;
            }
            $origin = ($parts['scheme'] ?? 'https').'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
            if ($origin === rtrim((string) config('app.url'), '/')) {
                return true;
            }
            $origins ??= array_merge([], ...array_column($policy->assetServices(), 'origins'));

            return in_array($origin, $origins, true);
        };
        $service = (string) ($payload['bg_external_service'] ?? '');
        $optional = false;
        foreach ([$url, $poster] as $candidate) {
            if (! $native($candidate)) {
                if (! $policy->permits($service, $candidate)) {
                    return '';
                }
                $optional = true;
            }
        }
        $overlay = $this->escape((string) ($payload[$type === 'image' ? 'bg_image_overlay' : 'bg_video_overlay'] ?? 'transparent'));
        if ($type === 'image') {
            $style = 'background-image:url(\''.$this->escape($url).'\');background-position:'.($payload['bg_position'] ?? 'center').';background-size:'.($payload['bg_size'] ?? 'cover').';background-repeat:'.($payload['bg_repeat'] ?? 'no-repeat').';';
            if (($payload['bg_behavior'] ?? '') === 'fixed') {
                $style .= 'background-attachment: fixed;';
            }
            $inner = '<div data-cms-presentation-motion class="cms-bg-layer" style="'.$style.'"></div>';
        } else {
            $style = 'object-fit:'.($payload['bg_video_fit'] ?? 'cover').';object-position:'.($payload['bg_video_position'] ?? 'center').';';
            $inner = '<video data-cms-presentation-motion data-cms-presentation-video class="cms-bg-video" muted loop playsinline preload="none" style="'.$style.'"'.($poster === '' ? '' : ' poster="'.$this->escape($poster).'"').'><source src="'.$this->escape($url).'"></video>';
        }
        $inner .= '<div data-cms-presentation-overlay style="background-color:'.$overlay.'"></div>';
        if ($optional) {
            $inner = $policy->render($inner, $service);
        }

        return '<div data-cms-presentation-media aria-hidden="true">'.$inner.'</div>';
    }

    /** @param array<string,mixed> $configuration */
    private function divider(string $side, array $configuration): string
    {
        $path = match ($configuration['type'] ?? 'none') {
            'waves' => 'M0,50 C150,100 350,0 500,50 S850,100 1000,50 S1150,0 1200,50 L1200,120 L0,120 Z',
            'tilt' => 'M0,120 L1200,0 L1200,120 Z',
            'curve' => 'M0,0 Q600,180 1200,0 L1200,120 L0,120 Z',
            'triangle' => 'M0,0 L600,120 L1200,0 L1200,120 L0,120 Z',
            'spikes' => 'M0,0 L100,100 L200,0 L300,100 L400,0 L500,100 L600,0 L700,100 L800,0 L900,100 L1000,0 L1100,100 L1200,0 L1200,120 L0,120 Z',
            default => '',
        };
        if ($path === '') {
            return '';
        }
        $style = 'height:'.($configuration['height'] ?? 60).'px;'.$side.':0;'.(! empty($configuration['flip']) ? 'transform:scaleX(-1);' : '');

        return '<div data-cms-presentation-divider="'.$side.'" aria-hidden="true" style="'.$style.'"><svg viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="'.$path.'" fill="'.$this->escape((string) ($configuration['color'] ?? 'var(--cms-accent)')).'"/></svg></div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
