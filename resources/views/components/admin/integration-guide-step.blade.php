@props(['step', 'module', 'guideId', 'number', 'completed' => false, 'callbackUrl' => null, 'callbackEligible' => false, 'previousStep' => null, 'nextStep' => null])

<article id="step-{{ $step->id }}" class="cyber-card p-6 space-y-5 scroll-mt-6" tabindex="-1" aria-labelledby="step-title-{{ $step->id }}">
    <div class="flex flex-wrap items-center gap-3">
        <x-admin.chip variant="brand" size="md">{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}</x-admin.chip>
        <h2 id="step-title-{{ $step->id }}" class="text-lg font-bold text-[color:var(--admin-text)]">{{ $step->title }}</h2>
        @if($completed)<x-admin.chip variant="success">{{ __('integrations.marked') }}</x-admin.chip>@endif
    </div>
    <dl class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-2"><dt class="font-bold text-[color:var(--admin-text)]">{{ __('integrations.provider_action') }}</dt><dd class="text-sm text-[color:var(--admin-muted)] leading-relaxed">{{ $step->externalAction }}</dd></div>
        <div class="space-y-2"><dt class="font-bold text-[color:var(--admin-text)]">{{ __('integrations.cms_action') }}</dt><dd class="text-sm text-[color:var(--admin-muted)] leading-relaxed">{{ $step->cmsAction }}</dd></div>
        <div class="space-y-2"><dt class="font-bold text-[color:var(--admin-text)]">{{ __('integrations.expected_result') }}</dt><dd class="text-sm text-[color:var(--admin-muted)] leading-relaxed">{{ $step->expectedResult }}</dd></div>
    </dl>
    @if($callbackEligible)
        @if($callbackUrl !== null)
            <x-admin.integration-guide-clipboard />
            <div class="space-y-2" x-data="integrationGuideClipboard(@js(['copied' => __('integrations.copied'), 'failed' => __('integrations.copy_failed')]))">
                <label class="admin-field-label font-bold text-[color:var(--admin-text)] text-sm" for="callback-{{ $step->id }}">{{ __('integrations.callback_url') }}</label>
                <div class="flex flex-wrap items-center gap-3">
                    <input id="callback-{{ $step->id }}" x-ref="callback" class="admin-control w-full flex-1 min-w-0 font-mono text-xs" type="text" readonly value="{{ $callbackUrl }}" aria-describedby="callback-help-{{ $step->id }} callback-status-{{ $step->id }}">
                    <x-admin.button type="button" size="sm" x-on:click="copy()" :aria-describedby="'callback-status-'.$step->id">{{ __('integrations.copy_url') }}</x-admin.button>
                </div>
                <p id="callback-help-{{ $step->id }}" class="text-xs text-[color:var(--admin-muted)]">{{ __('integrations.callback_help') }}</p>
                <p id="callback-status-{{ $step->id }}" class="text-xs text-[color:var(--admin-muted)]" role="status" aria-live="polite" aria-atomic="true" x-text="feedback"></p>
            </div>
        @else
            <p class="text-sm text-[color:var(--admin-muted)]">{{ __('integrations.callback_unavailable') }}</p>
        @endif
    @endif
    @if($step->links !== [])
        <div class="flex flex-wrap gap-2">
            @foreach($step->links as $label => $url)<x-admin.button :href="$url" target="_blank" rel="noopener noreferrer" size="sm">{{ $label }} ↗</x-admin.button>@endforeach
        </div>
    @endif
    <form action="{{ route('admin.modules.guides.progress', ['identifier' => $module, 'guide' => $guideId]) }}" method="POST" class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-[color:var(--admin-border)]">
        @csrf
        <input type="hidden" name="step_id" value="{{ $step->id }}">
        <input type="hidden" name="completed" value="0">
        <label class="inline-flex items-center gap-2 text-sm text-[color:var(--admin-muted)]" for="completed-{{ $step->id }}">
            <input id="completed-{{ $step->id }}" type="checkbox" name="completed" value="1" @checked($completed) class="w-4 h-4">
            {{ __('integrations.complete_step') }}
        </label>
        <x-admin.button type="submit" variant="neutral" size="sm" :aria-label="__('integrations.save_step_named', ['number' => $number])">{{ __('integrations.save_step') }}</x-admin.button>
    </form>
    @if($previousStep !== null || $nextStep !== null)
        <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="{{ __('integrations.step_navigation') }}">
            @if($previousStep !== null)
                <x-admin.button :href="'#step-'.$previousStep->id" size="sm" icon="fa-solid fa-arrow-left" :aria-label="__('integrations.previous_named', ['title' => $previousStep->title])">{{ __('integrations.previous') }}</x-admin.button>
            @endif
            @if($nextStep !== null)
                <x-admin.button :href="'#step-'.$nextStep->id" size="sm" icon="fa-solid fa-arrow-right" :aria-label="__('integrations.next_named', ['title' => $nextStep->title])">{{ __('integrations.next') }}</x-admin.button>
            @endif
        </nav>
    @endif
</article>
