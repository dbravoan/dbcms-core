@php($categories = app(\DbCms\Core\Services\Cms\CookieConsentService::class)->getDisclosedCategories())
<div x-data="cookieConsent" data-consent-endpoint="{{ route('privacy.consent.show') }}">
    <section x-cloak x-show="showBanner" aria-label="{{ __('cookies.banner.heading') }}" class="fixed bottom-0 inset-x-0 z-50 p-5 cms-card border-t cms-border shadow-xl">
        <h2 class="font-bold cms-text">{{ __('cookies.banner.heading') }}</h2>
        <p class="text-sm cms-text-muted my-2">{{ __('cookies.banner.message') }}</p>
        <div class="flex flex-wrap gap-3">
            <button type="button" @click="acceptAll()" :disabled="busy" class="cms-btn min-h-11 px-4 py-2 bg-slate-900 text-white border border-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">{{ __('cookies.banner.accept_all') }}</button>
            <button type="button" @click="acceptNecessary()" :disabled="busy" class="cms-btn min-h-11 px-4 py-2 bg-slate-900 text-white border border-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">{{ __('cookies.banner.necessary_only') }}</button>
            <button type="button" @click="openSettings()" class="cms-btn min-h-11 px-4 py-2 border cms-border-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">{{ __('cookies.banner.configure') }}</button>
        </div>
        <p x-show="error" x-text="error" role="alert" class="mt-2 text-sm"></p>
    </section>
    <div x-cloak x-show="showSettings" @keydown.escape.window="if (showSettings) closeSettings()" class="fixed inset-0 z-[60] bg-black/60 flex items-center justify-center p-4">
        <section x-ref="dialog" @keydown.tab="trapFocus($event)" role="dialog" aria-modal="true" aria-labelledby="privacy-dialog-title" class="cms-card cms-text rounded-lg border cms-border w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6">
            <div class="flex justify-between gap-4"><h2 id="privacy-dialog-title" class="text-xl font-bold">{{ __('cookies.banner.modal_title') }}</h2><button type="button" class="cms-btn h-11 w-11 shrink-0 border cms-border-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current" @click="closeSettings()" aria-label="{{ __('Cerrar') }}">×</button></div>
            @foreach($categories as $key => $category)
                <div class="border-b cms-border py-4">
                    <label class="flex items-center gap-3 font-semibold">
                        <input type="checkbox" x-model="preferences.{{ $key }}" @disabled($category['required'])>
                        {{ $category['title'] }}
                    </label>
                    <p class="text-sm mt-2">{{ $category['description'] }}</p>
                    @if($category['cookies'] !== [])
                        <ul class="text-xs space-y-1 mt-2">
                            @foreach($category['cookies'] as $cookie)
                                <li><strong>{{ $cookie['name'] }}</strong> — {{ $cookie['provider'] }}: {{ $cookie['purpose'] }} ({{ $cookie['duration'] }})</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
            <p x-show="error" x-text="error" role="alert" class="my-2"></p>
            <div class="flex gap-3 mt-5"><button type="button" @click="saveCustom()" :disabled="busy" class="cms-btn min-h-11 px-4 py-2 bg-slate-900 text-white border border-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">{{ __('cookies.banner.save') }}</button><button type="button" @click="acceptNecessary()" :disabled="busy" class="cms-btn min-h-11 px-4 py-2 bg-slate-900 text-white border border-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-current">{{ __('cookies.banner.necessary_only') }}</button></div>
        </section>
    </div>
</div>
