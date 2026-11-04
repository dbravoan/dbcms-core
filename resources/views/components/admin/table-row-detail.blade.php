@props([
    'colspan' => 100,
])

<tr x-show="expanded === $el.previousElementSibling"
    data-row-detail
    x-cloak
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-1"
    class="md:hidden bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)] font-mono">
    <td colspan="{{ $colspan }}" class="p-3 sm:p-4">
        <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-3.5 shadow-inner space-y-2 divide-y divide-[color:var(--admin-border)]/60">
            {{ $slot }}
        </div>
    </td>
</tr>
