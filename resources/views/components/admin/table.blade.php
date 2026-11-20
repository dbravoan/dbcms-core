@props([
    'hoverable' => true,
    'striped' => false,
    'empty' => false,
    'emptyTitle' => null,
    'emptyDescription' => null,
    'emptyActionRoute' => null,
    'emptyActionLabel' => null,
    'emptyActionEvent' => null,
])

@php
    $emptyTitle = $emptyTitle ?? __('admin.table.empty_title');
    $emptyDescription = $emptyDescription ?? __('admin.table.empty_description');
@endphp

<div {{ $attributes->merge(['class' => 'cyber-card overflow-hidden font-mono text-xs']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            @isset($header)
                <thead class="bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)] text-[color:var(--admin-muted)]">
                    <tr>
                        {{ $header }}
                    </tr>
                </thead>
            @endisset

            <tbody x-data="{ expanded: null }" class="divide-y divide-[color:var(--admin-border)]/60 {{ $striped ? 'divide-[color:var(--admin-border)]/40' : '' }}">
                @if($empty)
                    <tr>
                        <td colspan="100" class="p-0">
                            @if(isset($emptySlot))
                                {{ $emptySlot }}
                            @else
                                <x-admin.empty-state
                                    :title="$emptyTitle"
                                    :description="$emptyDescription"
                                    :action-route="$emptyActionRoute"
                                    :action-label="$emptyActionLabel"
                                    :action-event="$emptyActionEvent"
                                />
                            @endif
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @isset($pagination)
        <div class="border-t border-[color:var(--admin-border)]/80 bg-[color:var(--admin-surface)]/50">
            {{ $pagination }}
        </div>
    @endisset
</div>
