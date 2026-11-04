@props([
    'label' => '',
    'value' => null,
])

<div class="flex items-center justify-between py-1.5 first:pt-0 last:pb-0 gap-3">
    <span class="text-[color:var(--admin-muted)] font-bold uppercase text-[10px] tracking-wider shrink-0">
        {{ $label }}:
    </span>
    <div class="text-right text-xs truncate">
        {{ $slot->isEmpty() ? $value : $slot }}
    </div>
</div>
