@props([
    'page' => null,
    'markets' => [],
    'languages' => [],
])

@php
    $isEdit = !empty($page && $page->id);
    $actionUrl = $isEdit ? route('admin.pages.update', $page) : route('admin.pages.store');

    $allLangs = !empty($languages) && count($languages) > 0 ? $languages : ($activeLanguages ?? collect());
    $defaultLocale = $defaultLocale ?? $allLangs->firstWhere('is_default', true)?->code ?? $allLangs->first()?->code ?? config('app.fallback_locale');
    $translationsData = [];
    foreach ($allLangs as $lang) {
        $trans = $page ? $page->translations->where('locale', $lang->code)->first() : null;
        $translationsData[$lang->code] = [
            'title' => old("translations.{$lang->code}.title", $trans?->title ?? ''),
            'slug' => old("translations.{$lang->code}.slug", $trans?->slug ?? ''),
            'meta_title' => old("translations.{$lang->code}.meta_title", $trans?->meta_title ?? ''),
            'meta_description' => old("translations.{$lang->code}.meta_description", $trans?->meta_description ?? ''),
            'schema_type' => old("translations.{$lang->code}.schema_type", $trans?->schema_type ?? 'WebPage'),
        ];
    }

    $assignedMarketIds = old('market_ids', $page ? $page->markets->pluck('id')->all() : []);
    if (empty($assignedMarketIds) && !empty($markets)) {
        $assignedMarketIds = collect($markets)->pluck('id')->all();
    }


    $sectionsData = old('sections_json', $sectionsData ?? []);
    $selectedTemplate = old('template', $page?->template ?? 'default');
    $selectedTemplate = is_string($selectedTemplate) ? $selectedTemplate : ($page?->template ?? 'default');
    $isBladePage = str_starts_with($selectedTemplate, 'blade:');
@endphp

<form method="POST" action="{{ $actionUrl }}" class="space-y-8 font-mono text-xs"
      data-cms-translation-editor
      x-on:cms-translation-apply.window="if ($event.detail.form === $el && allLanguages.includes($event.detail.targetLocale)) { translations[$event.detail.targetLocale] = {...translations[$event.detail.targetLocale], ...$event.detail.values}; switchLocale($event.detail.targetLocale); }"
      x-on:locale-tab-change="switchLocale($event.detail.locale)"
      x-data="{
    activeLocale: @js($defaultLocale),
    selectedTemplate: @js($selectedTemplate),
    defaultLocale: @js($defaultLocale),
    translations: @js($translationsData),
    allLanguages: @js($allLangs->pluck('code')),
    copySourceLocale: @js($defaultLocale),
    copySuccessMessage: '',
    slugTouched: {},

    init() {
        for (const code of this.allLanguages) {
            if (!this.translations[code]) {
                this.translations[code] = {
                    title: '',
                    slug: '',
                    meta_title: '',
                    meta_description: '',
                    schema_type: 'WebPage'
                };
            }
        }
    },


    hasData(code) {
        return !!(this.translations[code]?.title && this.translations[code].title.trim() !== '');
    },

    generateSlug(text) {
        if (!text) return '';
        return text.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)+/g, '');
    },

    switchLocale(code) {
        if (!this.translations[code]) {
            this.translations[code] = { title: '', slug: '', meta_title: '', meta_description: '', schema_type: 'WebPage' };
        }
        this.activeLocale = code;
        window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } }));
    },

    copyFrom(sourceCode) {
        if (!this.translations[sourceCode] || !this.translations[sourceCode].title) {
            alert('El idioma de origen no contiene datos para copiar.');
            return;
        }

        const src = this.translations[sourceCode];
        this.translations[this.activeLocale].title = src.title;
        this.translations[this.activeLocale].slug = this.generateSlug(src.title);
        this.translations[this.activeLocale].meta_title = src.meta_title || src.title;
        this.translations[this.activeLocale].meta_description = src.meta_description || '';
        this.translations[this.activeLocale].schema_type = src.schema_type || 'WebPage';

        window.dispatchEvent(new CustomEvent('cms-copy-locale', {
            detail: { from: sourceCode, to: this.activeLocale }
        }));

        this.copySuccessMessage = 'Contenidos y bloques copiados correctamente desde ' + sourceCode.toUpperCase() + '.';
        setTimeout(() => { this.copySuccessMessage = ''; }, 4000);
    }
}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Column: Content, Languages & Block Manager (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Title & Slug Card -->
            <x-admin.form-card class="space-y-4">
                <!-- Language Selector Tabs & Copy Action -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[color:var(--admin-border)] pb-3">
                    <x-admin.locale-tabs :languages="$allLangs" :active="$defaultLocale" group="page-form" />

                    <!-- Quick Copy from Language Dropdown -->
                    <div class="flex items-center gap-2">
                        <select x-model="copySourceLocale" class="admin-control bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] text-xs rounded px-2.5 py-1">
                            @foreach($allLangs as $lang)
                                <option value="{{ $lang->code }}" x-show="'{{ $lang->code }}' !== activeLocale">{{ __('admin.entity_form.copy_from') }} {{ $lang->name }} ({{ strtoupper($lang->code) }})</option>
                            @endforeach
                        </select>
                        <button type="button" @click="copyFrom(copySourceLocale)" class="px-2.5 py-1 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-xs font-bold border border-[color:var(--admin-border)]">
                            Copiar a <span x-text="activeLocale.toUpperCase()"></span>
                        </button>
                    </div>
                </div>

                <!-- Empty Language Helper Banner -->
                <div x-show="!hasData(activeLocale)" class="p-3.5 rounded-xl bg-blue-950/40 border border-blue-500/30 text-blue-200 text-xs flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-globe text-base text-blue-400"></i>
                        <span>{{ __('admin.entity_form.info_prefix') }}<strong x-text="activeLocale.toUpperCase()"></strong>{{ __('admin.entity_form.info_suffix') }}</span>
                    </div>
                    <x-admin.button variant="primary" type="button" @click="copyFrom(copySourceLocale)" class="text-xs py-1 px-3">
                        Copiar contenidos y bloques &rarr;
                    </x-admin.button>
                </div>

                <!-- Success Toast -->
                <div x-show="copySuccessMessage" x-cloak class="p-2.5 rounded-lg bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/30 text-[color:var(--admin-accent)] text-xs flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span x-text="copySuccessMessage"></span>
                    </span>
                    <button type="button" @click="copySuccessMessage = ''" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]">&times;</button>
                </div>

                <!-- Dynamic Language Inputs -->
                @foreach($allLangs as $lang)
                    <div x-show="activeLocale === '{{ $lang->code }}'" class="space-y-4">
                        <div>
                            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                                {{ __('admin.entity_form.page_title') }} ({{ strtoupper($lang->code) }}) @if($lang->code === $defaultLocale)*@endif
                            </label>
                            <input type="text"
                                   name="translations[{{ $lang->code }}][title]"
                                   x-model="translations['{{ $lang->code }}'].title"
                                   @input="if(!slugTouched['{{ $lang->code }}']) translations['{{ $lang->code }}'].slug = generateSlug(translations['{{ $lang->code }}'].title)"
                                   @if($lang->code === $defaultLocale) required @endif
                                   placeholder="{{ __('admin.entity_form.ph_page_title') }}"
                                   class="w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-4 py-2.5 text-[color:var(--admin-text)] font-bold text-sm">
                        </div>
                        <div>
                            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                                {{ __('admin.entity_form.route_slug') }} ({{ strtoupper($lang->code) }})
                            </label>
                            <div class="flex items-center gap-2">
                                <span class="text-[color:var(--admin-muted)] text-xs">/</span>
                                <input type="text"
                                       name="translations[{{ $lang->code }}][slug]"
                                       x-model="translations['{{ $lang->code }}'].slug"
                                       @input="slugTouched['{{ $lang->code }}'] = true"
                                       placeholder="{{ __('admin.entity_form.ph_slug') }}"
                                       class="admin-control flex-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-accent)] font-mono">
                                <button type="button"
                                        @click="translations['{{ $lang->code }}'].slug = generateSlug(translations['{{ $lang->code }}'].title); slugTouched['{{ $lang->code }}'] = false"
                                        class="px-2.5 py-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[10px]">
                                    Generar desde título
                                </button>
                            </div>
                        </div>

                    </div>
                @endforeach
            </x-admin.form-card>

            <!-- Dynamic Block Manager Component -->
            <div x-show="selectedTemplate.startsWith('blade:')" x-cloak>
                <x-admin.form-card>
                    <p>El contenido de esta página se define en una vista Blade del proyecto. Puedes editar sus metadatos; los bloques guardados se conservan.</p>
                </x-admin.form-card>
            </div>
            @if(!$isBladePage)
                <fieldset :disabled="selectedTemplate.startsWith('blade:')" x-show="!selectedTemplate.startsWith('blade:')">
                    <x-admin.form-card>
                        <x-admin.block-manager entity-type="page" :entity-id="$page?->exists ? $page->getKey() : null" :sections="$sectionsData" name="sections_json" :languages="$allLangs" :target="request('section')" />
                    </x-admin.form-card>
                </fieldset>
            @else
                <p x-show="!selectedTemplate.startsWith('blade:')" x-cloak>Guarda el cambio de plantilla para volver a editar los bloques conservados.</p>
            @endif

        </div>

        <!-- Right Column: Publishing Controls, Markets & SEO Copilot (1 Col) -->
        <div class="space-y-6">

            <!-- Publishing & Visibility Card -->
            <x-admin.form-card class="space-y-4">
                <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                    &gt; {{ __('admin.entity_form.publication_visibility') }}
                </h4>

                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)] font-bold">{{ __('admin.entity_form.publish_state') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_published" value="1" class="sr-only peer" {{ old('is_published', $page?->is_published ?? true) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-[color:var(--admin-surface-raised)] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[color:var(--admin-accent)]"></div>
                    </label>
                </div>

                <div>
                    <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.render_template') }}</label>
                    <select name="template" x-model="selectedTemplate" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                        <option value="default" {{ old('template', $page?->template) === 'default' ? 'selected' : '' }}>{{ __('admin.entity_form.template_default') }}</option>
                        <option value="landing" {{ old('template', $page?->template) === 'landing' ? 'selected' : '' }}>{{ __('admin.entity_form.template_landing') }}</option>
                        <option value="full-width" {{ old('template', $page?->template) === 'full-width' ? 'selected' : '' }}>{{ __('admin.entity_form.template_full') }}</option>
                        @foreach(($pageViews ?? []) as $key => $view)
                            <option value="blade:{{ $key }}" @selected($selectedTemplate === 'blade:'.$key)>Blade · {{ $key }}</option>
                        @endforeach
                        @if($isBladePage && !array_key_exists(substr($selectedTemplate, 6), $pageViews ?? []))
                            <option value="{{ $selectedTemplate }}" selected>{{ $selectedTemplate }} · no disponible</option>
                        @endif
                    </select>
                </div>

                <!-- Market Association Badges -->
                <x-admin.market-picker name="market_ids" :selected="$assignedMarketIds" :markets="$markets" group="page-form" />

                <x-admin.form-actions :submit="false" class="pt-3 border-t border-[color:var(--admin-border)]">
                    <x-admin.button variant="primary" type="submit" class="w-full py-2.5">
                        {{ $isEdit ? __('admin.actions.save') : __('admin.entity_form.save_page') }} &rarr;
                    </x-admin.button>
                </x-admin.form-actions>
            </x-admin.form-card>

            <!-- Real-time SEO & GEO Copilot Box (per language, live with the tab) -->
            <x-admin.seo-meta-box :allLanguages="$allLangs->pluck('code')->all()" />


            <!-- Modular Sidebar Extension Stack -->
            @stack('admin_form_sidebar')
        </div>

    </div>
</form>
