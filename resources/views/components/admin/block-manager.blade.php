@props([
    'sections' => [],
    'name' => 'sections_json',
    'languages' => [],
    'target' => null,
    'entityType' => 'page',
    'entityId' => null,
    'market' => null,
])

@php
    // Expresiones Alpine para el estado activo de los botones de layout: se
    // construyen como texto en PHP porque los atributos de un componente <x-…>
    // se copian literalmente (no compilan directivas).
    $layoutActiveExpr = static fn (string $layout): string => "activeLayout(block) === '".$layout."'";

    $rawSections = !empty($sections) ? (is_string($sections) ? json_decode($sections, true) : $sections) : [];
    if (!is_array($rawSections)) {
        $rawSections = [];
    }

    $allActiveLanguages = !empty($languages) && count($languages) > 0
        ? $languages
        : ($activeLanguages ?? collect());

    if ($allActiveLanguages->isEmpty()) {
        // Sin idiomas en la BD se usa el idioma por defecto real (reactivo),
        // nunca una lista fija que no coincide con el backoffice.
        $allActiveLanguages = collect([$defaultLanguage ?? (object) ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸']]);
    }

    $languagesJs = collect($allActiveLanguages)->map(function ($lang) {
        $code = is_object($lang) ? $lang->code : ($lang['code'] ?? 'es');
        $name = is_object($lang) ? $lang->name : ($lang['name'] ?? strtoupper((string) $code));
        // Prefer the flag assigned in the backoffice (Language::flag accessor).
        $assigned = is_object($lang) ? ($lang->flag ?? null) : ($lang['flag'] ?? null);
        $flag = ! empty($assigned) ? (string) $assigned : match($code) {
            'es' => '🇪🇸',
            'en' => '🇺🇸',
            'ca' => '🏴󠁥󠁳󠁣󠁴󠁿',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'pt' => '🇵🇹',
            default => '🌐'
        };
        return [
            'code' => $code,
            'name' => $name,
            'flag' => $flag,
        ];
    })->values()->all();

    $editorDefaultLocale = $allActiveLanguages->firstWhere('is_default', true)?->code ?? $allActiveLanguages->first()?->code ?? config('app.fallback_locale');

    // Determine if $rawSections is keyed by locale
    $isLocaleKeyed = false;
    foreach (array_keys($rawSections) as $k) {
        if (is_string($k) && !is_numeric($k)) {
            $isLocaleKeyed = true;
            break;
        }
    }

    $sectionsByLocale = [];
    foreach ($allActiveLanguages as $lang) {
        $code = is_object($lang) ? $lang->code : ($lang['code'] ?? 'es');
        $sectionsByLocale[$code] = [];
    }

    if ($isLocaleKeyed) {
        foreach ($sectionsByLocale as $code => $val) {
            $sectionsByLocale[$code] = isset($rawSections[$code]) && is_array($rawSections[$code])
                ? $rawSections[$code]
                : [];
        }
    } else {
        $sectionsByLocale[$editorDefaultLocale] = $rawSections;
    }

    // Normalize each block in each locale
    foreach ($sectionsByLocale as $code => &$locBlocks) {
        foreach ($locBlocks as &$b) {
            if (!isset($activeBlockTypes[(string) ($b['type'] ?? 'text')])) { continue; }
            $b['is_collapsed'] = false;
        }
    }
    unset($locBlocks, $b);
@endphp



<div x-data="blockManager(@js($sectionsByLocale), @js($editorDefaultLocale), @js($availableTypesJs), @js($languagesJs), @json($target ? (int) $target : null), @js(['previewUrl' => $blockPreviewUrl ?? null, 'entityType' => $entityType, 'entityId' => $entityId, 'market' => $market ?? $blockEditorMarket ?? null, 'markets' => $blockEditorMarkets ?? [], 'referenceUrl' => $blockReferenceUrl ?? null, 'sources' => $blockSources ?? [], 'appearanceProperties' => $blockAppearanceProperties ?? [], 'appearanceVariables' => $blockAppearanceVariables ?? [], 'presentationProperties' => $blockPresentationProperties ?? [], 'appearanceClasses' => $blockAppearanceClasses ?? [], 'presets' => $blockPresets ?? []]))"
     @keydown="editorKeydown($event)"
     @keydown.escape.window="if (isFullscreen && !showAddModal && $el.contains($event.target)) toggleFullscreen()"
     @keydown.window="if ((event.ctrlKey || event.metaKey) && event.shiftKey && (event.key === 'F' || event.key === 'f')) { event.preventDefault(); toggleFullscreen(); }"
     :class="isFullscreen ? 'fixed inset-0 z-[100] bg-[color:var(--admin-surface)] w-screen h-screen flex flex-col overflow-hidden text-xs' : 'space-y-6 text-xs'"
     class="admin-block-manager font-mono transition-all duration-200">

    <!-- Hidden Input containing JSON Payload -->
    <input type="hidden" name="{{ $name }}" :value="getCleanSerializedSections()">
    <input type="hidden" name="sections_order_locale" :value="activeLocale">

    <!-- FULLSCREEN STICKY TOP BAR (Visible only in Fullscreen Mode) -->
    <div x-show="isFullscreen"
         class="shrink-0 bg-[color:var(--admin-surface)]/95 backdrop-blur-md border-b border-[color:var(--admin-accent)]/30 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 z-30 shadow-2xl">
        <!-- Brand & Language Switcher in Fullscreen -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-[color:var(--admin-text)] tracking-tight">{{ config('cms.name', 'CMS') }}</span>
                <span class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--admin-accent)] animate-pulse"></span>
                    <span>{{ __('admin.block_manager.fullscreen_canvas') }}</span>
                </span>
            </div>

            <!-- Language Switcher Pills -->
            <div class="flex items-center gap-1 bg-[color:var(--admin-surface)]/60 p-1 rounded-lg border border-[color:var(--admin-border)]">
                <template x-for="lang in languages" :key="lang.code">
                    <button type="button"
                            x-show="isLanguageActive(lang.code)"
                            @click="switchLocale(lang.code)"
                            :class="activeLocale === lang.code ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold shadow-sm' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)]'"
                            class="px-2.5 py-1 rounded text-xs transition-all flex items-center gap-1.5">
                        <span x-text="lang.flag"></span>
                        <span x-text="lang.code.toUpperCase()"></span>
                        <span x-show="sectionsByLocale[lang.code] && sectionsByLocale[lang.code].length > 0"
                              :class="activeLocale === lang.code ? 'bg-[color:var(--admin-on-accent)]' : 'bg-[color:var(--admin-accent)]'"
                              class="w-1.5 h-1.5 rounded-full" title="{{ __('admin.block_manager.has_sections') }}"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Center: Canvas Width Toggle & Block Stats -->
        <div class="hidden md:flex items-center gap-3">
            <div class="flex items-center gap-1 bg-[color:var(--admin-surface)]/60 p-1 rounded-lg border border-[color:var(--admin-border)]">
                <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold px-1.5">{{ __('admin.block_manager.canvas') }}</span>
                <button type="button" @click="canvasWidth = 'container'"
                        :class="canvasWidth === 'container' ? 'bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/40 font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                        class="px-2 py-0.5 rounded text-[11px] transition-all">
                    Centrado (1280px)
                </button>
                <button type="button" @click="canvasWidth = 'full'"
                        :class="canvasWidth === 'full' ? 'bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/40 font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                        class="px-2 py-0.5 rounded text-[11px] transition-all">
                    100% Panorámico
                </button>
            </div>
            <span class="text-[color:var(--admin-muted)] text-xs font-mono" x-text="blocks.length + ' secciones en ' + activeLocale.toUpperCase()"></span>
        </div>

        <!-- Right: Actions & Exit Fullscreen -->
        <div class="flex items-center gap-2">
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(true)" icon="fa-solid fa-angles-up" title="{{ __('admin.block_manager.collapse_all_tooltip') }}">
                {{ __('admin.block_manager.collapse_all') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(false)" icon="fa-solid fa-angles-down" title="{{ __('admin.block_manager.expand_all_tooltip') }}">
                {{ __('admin.block_manager.expand_all') }}
            </x-admin.button>

            <!-- Extension point: external modules may inject toolbar actions here -->
            @stack('block_manager_actions')

            <!-- Add Section Button -->
            <button type="button"
                    @click="openAddModalAt(null)"
                    class="btn-neon text-xs py-1.5 px-3 flex items-center gap-1.5">
                <span>+</span>
                <span>{{ __('admin.block_manager.add_section') }}</span>
            </button>

            <!-- Direct Save Button from Fullscreen -->
            <button type="button"
                    @click="saveForm()"
                    class="px-3 py-1.5 bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent)]/90 text-[color:var(--admin-on-accent)] font-bold rounded text-xs flex items-center gap-1.5 shadow-sm transition-all"
                    title="{{ __('admin.block_manager.save_tooltip') }}">
                <i class="fa-solid fa-floppy-disk"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.save') }}</span>
            </button>

            <!-- Exit Fullscreen Button -->
            <button type="button"
                    @click="toggleFullscreen()"
                    class="px-3 py-1.5 rounded bg-red-950/40 hover:bg-red-900/60 border border-red-500/40 text-red-300 hover:text-[color:var(--admin-text)] font-bold text-xs flex items-center gap-1.5 transition-colors"
                    title="{{ __('admin.block_manager.exit_fullscreen_tooltip') }}">
                <i class="fa-solid fa-xmark"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.exit_fullscreen') }}</span>
            </button>
        </div>
    </div>

    <!-- STANDARD TOP ACTION BAR (Visible when NOT in Fullscreen) -->
    <div x-show="!isFullscreen" class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-[color:var(--admin-border)]">
        <div>
            <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <span class="text-[color:var(--admin-accent)]">&gt;_</span>
                <span>{{ __('admin.block_manager.title') }}</span>
                <span class="px-2 py-0.5 rounded bg-[color:var(--admin-surface)]/60 border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] text-[10px] uppercase font-bold" x-text="@js(__('admin.block_manager.language')) + ' ' + activeLocale.toUpperCase()"></span>
            </h3>
            <p class="text-[color:var(--admin-muted)] text-[11px] mt-0.5">
                Organiza los bloques de contenido y widgets dinámicos para este idioma. Puedes personalizar fondos, separadores y animaciones.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Extension point: external modules may inject toolbar actions here -->
            @stack('block_manager_actions')

            <!-- Fullscreen Trigger Button -->
            <button type="button"
                    @click="toggleFullscreen()"
                    class="px-2.5 py-1 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-text)] hover:text-[color:var(--admin-accent)] rounded border border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)] text-[11px] font-bold flex items-center gap-1.5 transition-colors"
                    title="{{ __('admin.block_manager.fullscreen_tooltip') }}">
                <i class="fa-solid fa-expand"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.fullscreen') }}</span>
            </button>

            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(true)" icon="fa-solid fa-angles-up" title="{{ __('admin.block_manager.collapse_all_tooltip') }}">
                {{ __('admin.block_manager.collapse_all') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(false)" icon="fa-solid fa-angles-down" title="{{ __('admin.block_manager.expand_all_tooltip') }}">
                {{ __('admin.block_manager.expand_all') }}
            </x-admin.button>
        </div>
    </div>

    @include('components.admin.blocks.inspector.workspace')

    <!-- Add Section / Widget Modal with Demo vs Blank Choice -->
    <div x-show="showAddModal"
         @click.self="showAddModal = false"
         @keydown.escape.window="showAddModal = false"
         x-transition
         x-cloak
         class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-[color:var(--admin-surface)]/40 backdrop-blur-md"
         style="display: none;">
        <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-accent)]/40 max-w-2xl w-full rounded-2xl p-6 font-mono space-y-4 shadow-sm max-h-[90vh] flex flex-col"
             @click.outside="showAddModal = false">

            <!-- Modal Header with Insertion Mode Selector & Position Indicator -->
            <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                            <span>&gt;</span>
                            <span x-show="!columnTarget">{{ __('admin.block_manager.add_section') }} / Widget</span>
                            <span x-show="columnTarget" x-cloak>{{ __('admin.block_manager.add_block_to_column') }}</span>
                        </h3>
                        <span x-show="columnTarget" x-cloak class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30 text-[10px] font-bold"
                              x-text="'Columna ' + (columnTarget ? (columnTarget.colIndex + 1) : 1)"></span>
                        <span x-show="insertIndex === 0" class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30 text-[10px] font-bold">
                            Al principio (#1)
                        </span>
                        <span x-show="insertIndex !== null && insertIndex > 0 && insertIndex < blocks.length" class="px-2 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/30 text-[10px] font-bold" x-text="@js(__('admin.block_manager.at_position')).replace(':n', insertIndex + 1)"></span>
                        <span x-show="insertIndex === null || insertIndex >= blocks.length" class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30 text-[10px] font-bold">
                            Al final
                        </span>
                    </div>
                    <p class="text-[11px] text-[color:var(--admin-muted)] mt-0.5">{{ __('admin.block_manager.insert_block_desc') }}</p>
                </div>
                <button @click="showAddModal = false" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] text-xl">&times;</button>
            </div>

            <!-- Insertion Mode Tabs (Demo Data vs Empty) -->
            <div class="flex items-center justify-between p-2.5 bg-[color:var(--admin-surface)] rounded-xl border border-[color:var(--admin-border)]">
                <span class="text-xs text-[color:var(--admin-text)] font-bold flex items-center gap-1.5">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-bolt text-[color:var(--admin-accent)]"></i> {{ __('admin.block_manager.insertion_mode') }}</span>
                </span>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="insertionMode = 'demo'"
                            :class="insertionMode === 'demo' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)]'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-wand-magic-sparkles text-amber-400"></i> {{ __('admin.block_manager.mode_demo') }}</span>
                    </button>
                    <button type="button"
                            @click="insertionMode = 'empty'"
                            :class="insertionMode === 'empty' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)]'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-file text-[color:var(--admin-muted)]"></i> {{ __('admin.block_manager.mode_empty') }}</span>
                    </button>
                </div>
            </div>

            <div x-show="(editorOptions.presets || []).length" class="space-y-2"><h4 class="admin-field-label">Composiciones de ejemplo</h4><p class="admin-field-hint">Revisa textos, cifras y medios antes de publicar.</p><div class="flex flex-wrap gap-2"><template x-for="preset in editorOptions.presets || []" :key="preset.id || preset.name"><button type="button" class="px-3 py-2 rounded border border-[color:var(--admin-border)] text-[color:var(--admin-text)] hover:border-[color:var(--admin-accent)]" @click="insertPreset(preset)" x-text="preset.name"></button></template></div></div>

            <!-- Search and Filter Tabs -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1 bg-[color:var(--admin-surface)] p-1 rounded-lg border border-[color:var(--admin-border)]">
                    <button type="button" @click="activeCategory = 'all'"
                            :class="activeCategory === 'all' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.cat_all') }}</button>
                    <button type="button" @click="activeCategory = 'content'"
                            :class="activeCategory === 'content' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.tab_content') }}</button>
                    <button type="button" @click="activeCategory = 'widget'"
                            :class="activeCategory === 'widget' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.tab_widgets') }}</button>
                </div>

                <input type="text" x-model="searchQuery" placeholder="{{ __('admin.block_manager.search_component') }}" class="admin-control bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-1.5 text-[color:var(--admin-text)] text-xs w-48">
            </div>

            <!-- Component Grid with Quick Insertion Options -->
            <div class="flex-1 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-3 pr-1">
                <template x-for="item in filteredTypes()" :key="item.type">
                    <div class="p-3.5 rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)] transition-all group flex flex-col justify-between hover:bg-[color:var(--admin-surface-raised)]/40">
                        <div class="flex items-start gap-3">
                            <span class="text-xl shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-[color:var(--admin-surface)]/50 border border-[color:var(--admin-border)] group-hover:border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)]">
                                <i x-show="isFontAwesomeIcon(item.icon)" :class="item.icon" x-cloak></i>
                                <span x-show="!isFontAwesomeIcon(item.icon)" class="font-bold" x-text="item.icon || (item.name ? item.name.trim().charAt(0).toUpperCase() : '?')" x-cloak></span>
                            </span>
                            <div>
                                <div class="font-bold text-[color:var(--admin-text)] group-hover:text-[color:var(--admin-accent)] text-xs" x-text="item.name"></div>
                                <div class="text-[11px] text-[color:var(--admin-muted)] mt-0.5 leading-tight" x-text="item.desc"></div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2.5 border-t border-[color:var(--admin-border)] flex items-center justify-end gap-2">
                            <button type="button"
                                     @click="insertType(item.type, 'empty')"
                                     class="px-2.5 py-1 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[10px]"
                                     title="{{ __('admin.block_manager.empty_tooltip') }}">
                                <i class="fa-solid fa-file mr-1" aria-hidden="true"></i> En blanco
                            </button>
                            <button type="button"
                                     @click="insertType(item.type, 'demo')"
                                     class="px-2.5 py-1 bg-[color:var(--admin-accent)]/20 hover:bg-[color:var(--admin-accent)] text-[color:var(--admin-accent)] hover:text-[color:var(--admin-on-accent)] font-bold rounded text-[10px] border border-[color:var(--admin-accent)]/40"
                                     title="{{ __('admin.block_manager.demo_tooltip') }}">
                                <i class="fa-solid fa-wand-magic-sparkles mr-1" aria-hidden="true"></i> Con datos de ejemplo
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="pt-3 border-t border-[color:var(--admin-border)] flex justify-end">
                <button type="button" @click="closeAddModal()" class="px-4 py-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded-lg text-xs">
                    Cancelar
                </button>
            </div>

        </div>
    </div>


</div>
