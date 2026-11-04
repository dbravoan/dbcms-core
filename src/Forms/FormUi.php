<?php

declare(strict_types=1);

namespace DbCms\Core\Forms;

/**
 * Visual variants for the shared field renderer.
 *
 * The form framework owns the structure (label, control, error, honeypot) while
 * the caller owns the look: pass a named preset (`cyber`, `light`, `comment`) or
 * an array of class overrides. This is what lets the same component serve the
 * backoffice-styled front form, the light embed widget and the theme comment
 * forms without duplicating a single line of field logic.
 */
final class FormUi
{
    /**
     * @param  string|array<string,string>  $variant
     * @return array<string, string>
     */
    public static function classes(string|array $variant = 'cyber'): array
    {
        $presets = [
            'cyber' => [
                'label' => 'block font-bold text-gray-300 text-[11px]',
                'star' => 'text-red-400 font-bold',
                'input' => 'w-full bg-[#161616] border border-gray-700 focus:border-[#00FF99] focus:ring-1 focus:ring-[#00FF99] rounded-lg px-3 py-2 text-white text-xs transition-colors',
                'checkbox' => 'rounded bg-[#161616] border-gray-700 text-[#00FF99] focus:ring-[#00FF99]',
                'help' => 'text-gray-300 text-xs',
                'error' => 'text-xs text-red-400 mt-1',
            ],
            'light' => [
                'label' => 'block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1',
                'star' => 'text-rose-500',
                'input' => 'w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none text-sm transition-all',
                'checkbox' => 'rounded border-slate-300 text-indigo-600 focus:ring-indigo-500',
                'help' => 'text-slate-600 text-sm',
                'error' => 'text-xs text-rose-500 mt-1',
            ],
            'comment' => [
                'label' => 'block font-bold text-gray-400 uppercase mb-1',
                'star' => 'text-red-400',
                'input' => 'w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white',
                'checkbox' => 'rounded bg-[#1A1A1A] border-gray-700',
                'help' => 'text-gray-300 text-sm',
                'error' => 'text-xs text-red-400 mt-1',
            ],
            'comment-minimal' => [
                'label' => 'block text-neutral-500 font-mono text-[10px] uppercase tracking-wider mb-1',
                'star' => 'text-red-500',
                'input' => 'w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900 focus:outline-none focus:border-neutral-900',
                'checkbox' => 'rounded border-neutral-300',
                'help' => 'text-neutral-500 text-sm',
                'error' => 'text-xs text-red-500 mt-1',
            ],
            'comment-sunset' => [
                'label' => 'block font-semibold text-slate-400 uppercase text-[10px] mb-1',
                'star' => 'text-rose-500',
                'input' => 'w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-rose-500',
                'checkbox' => 'rounded border-white/20 bg-white/5',
                'help' => 'text-slate-300 text-sm',
                'error' => 'text-xs text-rose-400 mt-1',
            ],
            'comment-brutalist' => [
                'label' => 'block font-black text-black uppercase text-[10px] mb-1',
                'star' => 'text-red-600',
                'input' => 'w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20',
                'checkbox' => 'rounded border-2 border-black',
                'help' => 'text-neutral-700 text-sm',
                'error' => 'text-xs text-red-600 mt-1',
            ],
            'comment-editorial' => [
                'label' => 'block font-semibold text-stone-700 uppercase tracking-wider text-[10px] mb-1',
                'star' => 'text-rose-600',
                'input' => 'w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900 focus:outline-none focus:border-stone-500',
                'checkbox' => 'rounded border-stone-300',
                'help' => 'text-stone-600 text-sm',
                'error' => 'text-xs text-rose-600 mt-1',
            ],
            'comment-enterprise' => [
                'label' => 'block font-semibold text-slate-400 uppercase text-[10px] mb-1',
                'star' => 'text-rose-400',
                'input' => 'w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500',
                'checkbox' => 'rounded border-slate-700 bg-slate-950',
                'help' => 'text-slate-300 text-sm',
                'error' => 'text-xs text-rose-400 mt-1',
            ],
        ];

        $classes = $presets['cyber'];

        if (is_string($variant)) {
            $classes = $presets[$variant] ?? $classes;
        } else {
            /** @var array<string, string> $variant */
            $classes = array_merge($classes, array_filter($variant, static fn ($value): bool => is_string($value)));
        }

        return $classes;
    }
}
