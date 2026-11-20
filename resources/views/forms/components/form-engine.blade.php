@props([
    'slug' => 'contact',
    'form' => null,
    'title' => null,
    'buttonText' => null,
    'actionUrl' => null,
    'showCard' => true,
])

@if($targetForm)
<div class="{{ $wrapperClass }} font-mono text-xs" data-cms-forms>
    @if(!empty($formTitle))
        <div class="mb-6 pb-3 border-b cms-border">
            <h3 class="text-lg font-bold cms-text flex items-center gap-2">
                <span class="cms-accent">&gt;</span> {{ $formTitle }}
            </h3>
            @if($formDescription)
                <p class="text-xs cms-text-muted mt-1">{{ $formDescription }}</p>
            @endif
        </div>
    @endif

    @if(session('form_success') || session('success'))
        <div class="p-4 rounded-xl cms-accent-soft border cms-border-accent cms-accent mb-6 flex items-center gap-3">
            <span class="text-lg"><i class="fa-solid fa-circle-check"></i></span>
            <div>{{ session('form_success') ?? session('success') }}</div>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-400 mb-6 space-y-1">
            <div class="font-bold">Por favor corrige los siguientes errores:</div>
            <ul class="list-disc pl-5 text-[11px]">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(!$collectionReady)
        <p role="status">{{ __('legal.collection_unavailable') }}</p>
    @else
    <form method="POST" enctype="multipart/form-data" action="{{ $url }}" class="space-y-4" data-cms-form="{{ $targetForm->slug }}" data-form-id="{{ $targetForm->id }}"
        @if(session('form_submitted.slug') === $targetForm->slug)
            data-form-result="{{ json_encode(['detail' => session('form_submitted'), 'actions' => session('form_actions', [])]) }}"
        @endif>
        @csrf

        <div data-form-status role="status" aria-live="polite" tabindex="-1"></div>
        @if($steps === [])
            <x-cms-form.fields :schema="$schema" variant="cyber" layout="grid" honeypot="_hp_check" />
        @else
            <div hidden aria-hidden="true"><input type="text" name="_hp_check" tabindex="-1" autocomplete="off"></div>
            <p data-form-progress aria-live="polite"></p>
            @foreach($steps as $stepIndex => $step)
                <fieldset data-form-step="{{ $stepIndex }}" class="space-y-4">
                    @if($step['label'] !== '')<legend class="font-bold">{{ $step['label'] }}</legend>@endif
                    @foreach($step['groups'] as $group)
                        <fieldset class="space-y-2">
                            @if($group['label'] !== '')<legend>{{ $group['label'] }}</legend>@endif
                            <div class="cms-form-columns" style="--form-mobile:{{ $group['columns']['mobile'] }};--form-tablet:{{ $group['columns']['tablet'] }};--form-desktop:{{ $group['columns']['desktop'] }}">
                                @foreach($group['schema'] as $field)
                                    <x-cms-form.fields :schema="[$field]" variant="cyber" layout="stack" />
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </fieldset>
            @endforeach
            <div data-form-navigation hidden class="flex gap-3">
                <button type="button" data-form-previous class="cms-btn">{{ __('Anterior') }}</button>
                <button type="button" data-form-next class="cms-btn">{{ __('Siguiente') }}</button>
            </div>
        @endif

        <x-cms::privacy-notice owner="forms" />
        <div class="pt-2" data-form-submit>
            <button type="submit" class="cms-btn cms-btn-primary w-full py-3">
                {{ $btnLabel }} &rarr;
            </button>
        </div>
    </form>
    @endif
</div>

@endif

@once
<style nonce="{{ $cspNonce ?? '' }}">
.cms-form-columns{display:grid;grid-template-columns:repeat(var(--form-mobile,1),minmax(0,1fr));gap:1rem}
@media(min-width:640px){.cms-form-columns{grid-template-columns:repeat(var(--form-tablet,2),minmax(0,1fr))}}
@media(min-width:1024px){.cms-form-columns{grid-template-columns:repeat(var(--form-desktop,2),minmax(0,1fr))}}
</style>
@endonce
