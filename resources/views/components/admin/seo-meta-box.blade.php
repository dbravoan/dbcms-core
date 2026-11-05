@props([
    // Si se indica, el bloque queda fijado a ese idioma (modo estático).
    // Si es `null`, se enlaza en vivo a la pestaña de idioma activa (`activeLocale`).
    'locale' => null,
    // Códigos de idioma del formulario: generan los campos ocultos por idioma
    // para que un solo guardado persista el SEO de todos.
    'allLanguages' => [],
    'schemaType' => 'WebPage',
    'schemaEntity' => null,
    'metaTitle' => '',
    'metaDescription' => '',
    'targetKeyword' => '',
])

@php
    $localeCode = $locale ?? ($defaultLocale ?? app()->getLocale());
    $languages = collect($allLanguages ?? $allLangs ?? [])
        ->map(static fn ($code): string => (string) $code)
        ->values()
        ->all();
    // Offer only types this entity's public schema generator implements.
    $schemaEntity = $schemaEntity ?? match ($schemaType) {
        'Product' => 'product', 'ImageGallery' => 'gallery',
        'Article', 'TechArticle', 'BlogPosting', 'NewsArticle' => 'post',
        default => 'page',
    };
    $schemaOptions = match ($schemaEntity) {
        'product' => ['Product' => 'admin.seo.schema_product'],
        'gallery' => ['ImageGallery' => 'admin.seo.schema_imagegallery'],
        'post' => [
            'Article' => 'admin.seo.schema_article', 'TechArticle' => 'admin.seo.schema_techarticle',
            'BlogPosting' => 'admin.seo.schema_blogposting', 'NewsArticle' => 'admin.seo.schema_newsarticle',
        ],
        default => ['WebPage' => 'admin.seo.schema_webpage'],
    };
@endphp

<x-admin.form-card x-data="{
    fixedLocale: {{ Illuminate\Support\Js::from($locale)->toHtml() }},
    fallbackLocale: {{ Illuminate\Support\Js::from($localeCode)->toHtml() }},
    // Idiomas del formulario; vacío en modo estático (bloque ligado a un idioma).
    languages: {{ Illuminate\Support\Js::from($languages)->toHtml() }},

    get locale() {
        if (this.fixedLocale) return this.fixedLocale;
        if (typeof activeLocale !== 'undefined' && activeLocale) return activeLocale;
        return this.fallbackLocale;
    },
    localMetaTitle: {{ Illuminate\Support\Js::from($metaTitle)->toHtml() }},
    localMetaDescription: {{ Illuminate\Support\Js::from($metaDescription)->toHtml() }},
    localSchemaType: {{ Illuminate\Support\Js::from(array_key_exists($schemaType, $schemaOptions) ? $schemaType : array_key_first($schemaOptions))->toHtml() }},
    supportedSchemaTypes: {{ Illuminate\Support\Js::from(array_keys($schemaOptions))->toHtml() }},
    targetKeyword: {{ Illuminate\Support\Js::from($targetKeyword)->toHtml() }},

    // `translations` is shared with the entity form (title, slug…): the SEO
    // fields live in the same per-language object, so one submit saves them.
    bucket() {
        return (typeof translations !== 'undefined' && translations[this.locale]) ? translations[this.locale] : null;
    },

    get activeMetaTitle() {
        return this.bucket()?.meta_title ?? this.localMetaTitle;
    },
    set activeMetaTitle(val) {
        const bucket = this.bucket();
        if (bucket) { bucket.meta_title = val; }
        this.localMetaTitle = val;
    },

    get activeMetaDesc() {
        return this.bucket()?.meta_description ?? this.localMetaDescription;
    },
    set activeMetaDesc(val) {
        const bucket = this.bucket();
        if (bucket) { bucket.meta_description = val; }
        this.localMetaDescription = val;
    },

    get activeSchemaType() {
        const candidate = this.bucket()?.schema_type || this.localSchemaType;
        return this.supportedSchemaTypes.includes(candidate) ? candidate : this.localSchemaType;
    },
    set activeSchemaType(val) {
        const bucket = this.bucket();
        if (bucket) { bucket.schema_type = val; }
        this.localSchemaType = val;
    },

    get previewTitle() {
        if (this.activeMetaTitle && this.activeMetaTitle.trim() !== '') {
            return this.activeMetaTitle;
        }

        const bucket = this.bucket();

        return bucket?.title || bucket?.name || this.localMetaTitle || {{ Illuminate\Support\Js::from(__('admin.seo.preview_title_placeholder'))->toHtml() }};
    },

    get previewDesc() {
        if (this.activeMetaDesc && this.activeMetaDesc.trim() !== '') {
            return this.activeMetaDesc;
        }
        if (typeof activeLocale !== 'undefined' && typeof translations !== 'undefined' && translations[activeLocale]) {
            return translations[activeLocale].summary || translations[activeLocale].description || {{ Illuminate\Support\Js::from(__('admin.seo.preview_desc_placeholder'))->toHtml() }};
        }
        return this.localMetaDescription || {{ Illuminate\Support\Js::from(__('admin.seo.preview_desc_placeholder'))->toHtml() }};
    },

    get previewSlug() {
        return this.bucket()?.slug || {{ Illuminate\Support\Js::from(__('admin.seo.slug_placeholder'))->toHtml() }};
    },

    get titleLength() {
        return this.activeMetaTitle ? this.activeMetaTitle.length : (this.previewTitle !== {{ Illuminate\Support\Js::from(__('admin.seo.preview_title_placeholder'))->toHtml() }} ? this.previewTitle.length : 0);
    },

    get descLength() {
        return this.activeMetaDesc ? this.activeMetaDesc.length : (this.previewDesc === {{ Illuminate\Support\Js::from(__('admin.seo.preview_desc_placeholder'))->toHtml() }} ? 0 : this.previewDesc.length);
    },

    get score() {
        let s = 40;
        if (this.titleLength >= 30 && this.titleLength <= 65) s += 25;
        else if (this.titleLength > 10) s += 10;

        if (this.descLength >= 100 && this.descLength <= 165) s += 25;
        else if (this.descLength > 30) s += 10;

        if (this.activeSchemaType) s += 10;
        return Math.min(100, s);
    }
}" class="space-y-5 font-mono text-xs">

    <!-- Encabezado con puntuación en directo -->
    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-[color:var(--admin-border)]">
        <div class="flex items-center gap-2 min-w-0">
            <i class="fa-solid fa-robot text-base text-[color:var(--admin-accent)] shrink-0"></i>
            <div class="min-w-0">
                <h4 class="font-bold text-[color:var(--admin-text)] text-xs">{{ __('admin.seo.title') }}</h4>
                <p class="text-[10px] text-[color:var(--admin-muted)]">{{ __('admin.seo.subtitle') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <!-- Extension point: external modules may inject SEO block actions here -->
            @stack('seo_block_actions')

            <div class="flex items-center gap-1.5 px-3 py-1 rounded-full border"
                 title="{{ __('admin.seo.editorial_heuristic') }}"
                 :class="score >= 80 ? 'bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border-[color:var(--admin-accent)]/40' : (score >= 50 ? 'bg-amber-500/10 text-amber-400 border-amber-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30')">
                <span class="text-xs font-bold" x-text="score + '/100'"></span>
                <span class="text-[9px] uppercase" x-text="score >= 80 ? @js(__('admin.status.optimal')) : (score >= 50 ? @js(__('admin.status.improving')) : @js(__('admin.status.low')))"></span>
            </div>
        </div>
    </div>

    <!-- Simulador de vista previa en Google SERP -->
    <div class="bg-[color:var(--admin-surface)] p-3.5 rounded-lg border border-[color:var(--admin-border)]/80 space-y-1">
        <div class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold flex items-center justify-between">
            <span>{{ __('admin.seo.preview') }}</span>
            <span class="text-[color:var(--admin-muted)] font-normal">{{ __('admin.seo.simulation') }}</span>
        </div>
        <div class="text-blue-400 text-xs font-semibold truncate hover:underline cursor-pointer" x-text="previewTitle"></div>
        <div class="text-[color:var(--admin-accent)] text-[10px] truncate" x-text="'https://' + window.location.host + '/' + locale + '/' + previewSlug"></div>
        <div class="text-[color:var(--admin-muted)] text-[11px] line-clamp-2" x-text="previewDesc"></div>
    </div>

    <!-- Campos de metadatos SEO -->
    <div class="space-y-3">
        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="admin-field-label text-[10px] font-bold text-[color:var(--admin-muted)]">{{ __('admin.seo.meta_title') }}</label>
                <span :class="titleLength >= 30 && titleLength <= 65 ? 'text-[color:var(--admin-accent)]' : 'text-[color:var(--admin-muted)]'" class="text-[10px]" x-text="titleLength + ' / 60 ' + @js(__('admin.seo.chars'))"></span>
            </div>
            <input type="text"
                   :name="'translations[' + locale + '][meta_title]'"
                   x-model="activeMetaTitle"
                   placeholder="{{ __('admin.seo.title_placeholder') }}"
                   class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs">
        </div>

        <div>
            <div class="flex justify-between items-center mb-1">
                <label class="admin-field-label text-[10px] font-bold text-[color:var(--admin-muted)]">{{ __('admin.seo.meta_description') }}</label>
                <span :class="descLength >= 100 && descLength <= 165 ? 'text-[color:var(--admin-accent)]' : 'text-[color:var(--admin-muted)]'" class="text-[10px]" x-text="descLength + ' / 160 ' + @js(__('admin.seo.chars'))"></span>
            </div>
            <textarea :name="'translations[' + locale + '][meta_description]'"
                      x-model="activeMetaDesc"
                      rows="3"
                      placeholder="{{ __('admin.seo.desc_placeholder') }}"
                      class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="admin-field-label block text-[10px] font-bold text-[color:var(--admin-muted)] mb-1">{{ __('admin.seo.schema_type') }}</label>
                <select :name="'translations[' + locale + '][schema_type]'"
                        x-model="activeSchemaType"
                        class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-2.5 py-2 text-[color:var(--admin-text)] text-xs">
                    @foreach($schemaOptions as $schemaValue => $schemaLabel)
                        <option value="{{ $schemaValue }}">{{ __($schemaLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="admin-field-label block text-[10px] font-bold text-[color:var(--admin-muted)] mb-1">{{ __('admin.seo.target_keyword') }}</label>
                <input type="text"
                       name="target_keyword"
                       x-model="targetKeyword"
                       placeholder="{{ __('admin.seo.target_keyword_placeholder') }}"
                       class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-2.5 py-2 text-[color:var(--admin-text)] text-xs">
            </div>
        </div>

        <!-- Un guardado persiste el SEO de TODOS los idiomas: las claves
             de cada idioma viajan como campos ocultos aunque el bloque
             se edite en la columna derecha enlazado a la pestaña activa. -->
        <div>
            @if($languages !== [])
                <template x-for="code in languages" :key="'seo-hidden-' + code">
                    <div>
                        <input type="hidden" :name="'translations[' + code + '][meta_title]'" :value="(translations[code] || {}).meta_title || ''">
                        <input type="hidden" :name="'translations[' + code + '][meta_description]'" :value="(translations[code] || {}).meta_description || ''">
                        <input type="hidden" :name="'translations[' + code + '][schema_type]'" :value="(translations[code] || {}).schema_type || 'WebPage'">
                    </div>
                </template>
            @endif
        </div>
    </div>

    <!-- Lista de recomendaciones para GEO y buscadores -->
    <div class="pt-2 border-t border-[color:var(--admin-border)] space-y-1.5 text-[11px]">
        <div class="text-[10px] text-[color:var(--admin-muted)] font-bold mb-1">{{ __('admin.seo.recommendations') }}</div>
        <div class="flex items-center justify-between text-[color:var(--admin-text)]">
            <span>{{ __('admin.seo.title_length') }}</span>
            <span :class="titleLength >= 30 && titleLength <= 65 ? 'text-[color:var(--admin-accent)]' : 'text-[color:var(--admin-muted)]'" class="flex items-center gap-1">
                <i x-show="titleLength >= 30 && titleLength <= 65" class="fa-solid fa-check"></i>
                <span x-text="titleLength >= 30 && titleLength <= 65 ? @js(__('admin.seo.correct')) : @js(__('admin.seo.adjust'))"></span>
            </span>
        </div>
        <div class="flex items-center justify-between text-[color:var(--admin-text)]">
            <span>{{ __('admin.seo.desc_length') }}</span>
            <span :class="descLength >= 100 && descLength <= 165 ? 'text-[color:var(--admin-accent)]' : 'text-[color:var(--admin-muted)]'" class="flex items-center gap-1">
                <i x-show="descLength >= 100 && descLength <= 165" class="fa-solid fa-check"></i>
                <span x-text="descLength >= 100 && descLength <= 165 ? @js(__('admin.seo.correct')) : @js(__('admin.seo.adjust'))"></span>
            </span>
        </div>
        <div class="flex items-center justify-between text-[color:var(--admin-text)]">
            <span>{{ __('admin.seo.schema_org') }}</span>
            <span class="text-[color:var(--admin-muted)]">{{ __('admin.seo.schema_on_publication') }} (<span x-text="activeSchemaType"></span>)</span>
        </div>
        <div class="flex items-center justify-between text-[color:var(--admin-text)]">
            <span>{{ __('admin.seo.llms_txt') }}</span>
            <span class="text-[color:var(--admin-muted)]">{{ __('admin.seo.corpus_on_publication') }}</span>
        </div>
    </div>

</x-admin.form-card>
