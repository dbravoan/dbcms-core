@props([
    'sections' => [],
    'legacyBodies' => [],
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

@endphp



<div x-data="blockManager(@js($sectionsByLocale), @js($editorDefaultLocale), @js($availableTypesJs), @js($languagesJs), @json($target ? (int) $target : null), @js(['previewUrl' => $blockPreviewUrl ?? null, 'entityType' => $entityType, 'entityId' => $entityId, 'market' => $market ?? $blockEditorMarket ?? null, 'markets' => $blockEditorMarkets ?? [], 'referenceUrl' => $blockReferenceUrl ?? null, 'sources' => $blockSources ?? [], 'appearanceProperties' => $blockAppearanceProperties ?? [], 'appearanceVariables' => $blockAppearanceVariables ?? [], 'presentationProperties' => $blockPresentationProperties ?? [], 'appearanceClasses' => $blockAppearanceClasses ?? [], 'htmlClasses' => $blockHtmlClasses ?? [], 'htmlClassMax' => $blockHtmlClassMax ?? 16, 'templates' => $blockTemplates ?? [], 'labels' => __('admin.block_editor_js')]))"
     @keydown="editorKeydown($event)"
     @keydown.escape.window="if (isFullscreen && !dialogBusy() && $el.contains($event.target)) toggleFullscreen()"
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
            <div class="cms-inspector-toolbar-group" role="group" aria-label="{{ __('admin.block_manager.canvas') }}">
                <span class="admin-field-hint px-1.5 uppercase font-bold tracking-wide">{{ __('admin.block_manager.canvas') }}</span>
                <x-admin.button type="button" variant="ghost" size="sm" @click="canvasWidth = 'container'" alpine-active="canvasWidth === 'container'" x-bind:aria-pressed="canvasWidth === 'container'">Centrado (1280px)</x-admin.button>
                <x-admin.button type="button" variant="ghost" size="sm" @click="canvasWidth = 'full'" alpine-active="canvasWidth === 'full'" x-bind:aria-pressed="canvasWidth === 'full'">100% Panorámico</x-admin.button>
            </div>
            <span class="text-[color:var(--admin-muted)] text-xs font-mono" x-text="blocks.length + ' secciones en ' + activeLocale.toUpperCase()"></span>
        </div>

        <!-- Right: Actions & Exit Fullscreen -->
        <div class="flex flex-wrap items-center gap-2">
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(true)" icon="fa-solid fa-angles-up" title="{{ __('admin.block_manager.collapse_all_tooltip') }}">
                {{ __('admin.block_manager.collapse_all') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(false)" icon="fa-solid fa-angles-down" title="{{ __('admin.block_manager.expand_all_tooltip') }}">
                {{ __('admin.block_manager.expand_all') }}
            </x-admin.button>

            <!-- Extension point: external modules may inject toolbar actions here -->
            @stack('block_manager_actions')

            <x-admin.button variant="primary" size="sm" @click="addEmptySection(null)" icon="fa-solid fa-plus">
                {{ __('admin.block_manager.add_section') }}
            </x-admin.button>
            <x-admin.button variant="neutral" size="sm" @click="saveForm()" icon="fa-solid fa-floppy-disk" title="{{ __('admin.block_manager.save_tooltip') }}">
                {{ __('admin.block_manager.save') }}
            </x-admin.button>
            <x-admin.button variant="danger" size="sm" @click="toggleFullscreen()" icon="fa-solid fa-xmark" title="{{ __('admin.block_manager.exit_fullscreen_tooltip') }}">
                {{ __('admin.block_manager.exit_fullscreen') }}
            </x-admin.button>
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
                {{ __('admin.block_manager.subtitle') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Extension point: external modules may inject toolbar actions here -->
            @stack('block_manager_actions')

            <x-admin.button variant="ghost" size="sm" @click="toggleFullscreen()" icon="fa-solid fa-expand" title="{{ __('admin.block_manager.fullscreen_tooltip') }}">
                {{ __('admin.block_manager.fullscreen') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(true)" icon="fa-solid fa-angles-up" title="{{ __('admin.block_manager.collapse_all_tooltip') }}">
                {{ __('admin.block_manager.collapse_all') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(false)" icon="fa-solid fa-angles-down" title="{{ __('admin.block_manager.expand_all_tooltip') }}">
                {{ __('admin.block_manager.expand_all') }}
            </x-admin.button>
        </div>
    </div>

    @include('components.admin.blocks.inspector.workspace')

    <!-- CANVAS BODY WRAPPER (Scrollable in Fullscreen, Normal in Embedded) -->
    <div :class="isFullscreen ? 'flex-1 overflow-y-auto px-4 sm:px-8 py-6 sidebar-scroll' : ''" data-block-canvas>
        <div :class="isFullscreen ? (canvasWidth === 'container' ? 'max-w-7xl mx-auto w-full space-y-6' : 'w-full space-y-6') : 'space-y-6'" :data-canvas-width="isFullscreen ? canvasWidth : null">

            @if($legacyBodies !== [])
                <div x-data="{ legacyBodies: @js($legacyBodies) }" x-show="blocks.length === 0 && legacyBodies[activeLocale]" x-cloak class="admin-block-copy space-y-3" data-legacy-section-conversion>
                    <p class="text-sm text-[color:var(--admin-muted)]">{{ __('admin.block_manager.legacy_body') }}</p>
                    <x-admin.button type="button" variant="primary" size="sm" @click="convertLegacyBody(legacyBodies[activeLocale])">
                        {{ __('admin.block_manager.convert_legacy_body') }}
                    </x-admin.button>
                </div>
            @endif

            <!-- Empty State for Active Locale (Centered in Canvas) -->
            <div x-show="blocks.length === 0" class="my-6 p-10 border border-dashed border-[color:var(--admin-border)] rounded-2xl text-center space-y-4 bg-[color:var(--admin-surface)]/50 max-w-xl mx-auto transition-all">
                <div class="w-14 h-14 rounded-2xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-accent)]/30 flex items-center justify-center text-2xl text-[color:var(--admin-accent)] mx-auto shadow-sm">
                    <i class="fa-solid fa-cubes" aria-hidden="true"></i>
                </div>
                <div>
                    <h4 class="text-[color:var(--admin-text)] font-bold text-sm">{{ __('admin.block_manager.canvas_empty_title') }} <span x-text="activeLocale.toUpperCase()" class="text-[color:var(--admin-accent)]"></span></h4>
                    <p class="text-xs text-[color:var(--admin-muted)] mt-1">{{ __('admin.block_manager.canvas_empty_desc') }}</p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <x-admin.button type="button" variant="primary" size="sm" @click="addEmptySection(0)" icon="fa-solid fa-plus">
                        {{ __('admin.block_manager.add_first') }}
                    </x-admin.button>
                </div>
            </div>

            <!-- Copy from another language: visible whenever another locale has content -->
            <div x-show="hasCopySources()" x-cloak class="admin-block-copy flex flex-wrap items-center justify-end gap-2" data-block-copy>
                <label class="text-[11px] text-[color:var(--admin-muted)]" :for="'block-copy-source-' + activeLocale">{{ __('admin.block_manager.copy_blocks') }}</label>
                <select x-model="copySourceLocale" :id="'block-copy-source-' + activeLocale" class="admin-control">
                    <template x-for="(locBlocks, locCode) in sectionsByLocale" :key="locCode">
                        <option :value="locCode" x-show="locCode !== activeLocale && locBlocks.length > 0" :disabled="locCode === activeLocale || locBlocks.length === 0" x-text="@js(__('admin.block_manager.copy_from')).replace(':locale', locCode.toUpperCase()).replace(':count', locBlocks.length)"></option>
                    </template>
                </select>
                <x-admin.button type="button" variant="neutral" size="sm" @click="confirmCopyBlocksFrom(copySourceLocale)">
                    {{ __('admin.block_manager.copy_blocks') }} &rarr;
                </x-admin.button>
            </div>

            <!-- Blocks List (cards) -->
            <div class="admin-block-list space-y-4 pb-6" data-editor-root-list x-init="mountRootSortable($el)">
                <template x-for="(block, index) in blocks" :key="block.id || index">
                    <div :data-block-index="index" :data-editor-node="block.id || index" :data-node-unavailable="containsUnavailableBlock(block) ? 'true' : null" class="admin-block-card-wrap">
                        <!-- Top Insertion Zone (before the first block) -->
                        <div x-show="index === 0" class="admin-block-insert" data-insert-zone="0">
                            <div class="admin-block-insert-line" aria-hidden="true"></div>
                            <button type="button" @click="addEmptySection(0)" class="admin-block-insert-btn">
                                <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i>
                                <span>{{ __('admin.block_manager.add_section_start') }}</span>
                            </button>
                        </div>

                        <article class="admin-block-card bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-2xl overflow-hidden transition-all shadow-xl" :aria-label="(typeDef(block.type).name || block.type) + ' #' + (index + 1)" :data-selected="isNodeInSelection([index]) ? 'true' : null">

                            <!-- Block Header Toolbar -->
                            <div class="admin-block-heading bg-[color:var(--admin-surface)] px-4 py-3 border-b border-[color:var(--admin-border)] flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-3 min-w-0">
                                    <button type="button" data-node-drag class="admin-block-drag p-1 rounded text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] cursor-grab disabled:opacity-20" :disabled="containsUnavailableBlock(block)" @keydown.alt.arrow-up.prevent="moveUp(index)" @keydown.alt.arrow-down.prevent="moveDown(index)" :aria-label="@js(__('admin.block_manager.drag_section'))" :title="@js(__('admin.block_manager.drag_section'))"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></button>
                                    <button type="button" class="flex items-center gap-3 select-none text-left min-w-0" @click="toggleCollapse(block, [index])" :aria-expanded="!isCollapsed(block, [index])">
                                        <span class="text-[color:var(--admin-muted)] font-bold font-mono" x-text="'#' + (index + 1)"></span>
                                        <i x-show="hasColumnContent(block)" class="fa-solid fa-table-columns text-base text-[color:var(--admin-accent)]" aria-hidden="true" x-cloak></i>
                                        <i x-show="!hasColumnContent(block) && isFontAwesomeIcon(typeDef(block.type).icon)" :class="typeDef(block.type).icon" class="text-base text-[color:var(--admin-accent)]" aria-hidden="true" x-cloak></i>
                                        <span x-show="!hasColumnContent(block) && !isFontAwesomeIcon(typeDef(block.type).icon)" class="text-base text-[color:var(--admin-accent)] font-bold" x-text="typeGlyph(block.type)" aria-hidden="true" x-cloak></span>
                                        <span class="min-w-0">
                                            <span class="font-bold text-[color:var(--admin-text)] uppercase text-xs" x-text="sectionTitle(block)"></span>
                                            <span x-show="block.title" class="text-[color:var(--admin-muted)] text-[11px] ml-2" x-text="'- ' + block.title"></span>
                                        </span>
                                    </button>
                                </div>

                                <div class="admin-block-tools flex flex-wrap items-center gap-2" x-show="isBlockAvailable(block)">
                                    <!-- Column Layout Selector -->
                                    <div class="admin-block-layout-tools flex flex-wrap items-center gap-1 bg-[color:var(--admin-surface)] p-1 rounded-lg border border-[color:var(--admin-border)]">
                                        <span class="text-[10px] text-[color:var(--admin-muted)] font-bold px-1 uppercase font-mono">{{ __('admin.block_manager.columns') }}</span>
                                        <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '1')" x-bind:disabled="!canSetColumnsLayout(block, '1')" x-bind:aria-describedby="'block-layout-restriction-' + index" :alpine-active="$layoutActiveExpr('1')" title="1 Columna (100%)">1</x-admin.button>
                                        <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '2')" x-bind:disabled="!canSetColumnsLayout(block, '2')" x-bind:aria-describedby="'block-layout-restriction-' + index" :alpine-active="$layoutActiveExpr('2')" title="2 Columnas (50/50)">2</x-admin.button>
                                        <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '1-2')" x-bind:disabled="!canSetColumnsLayout(block, '1-2')" x-bind:aria-describedby="'block-layout-restriction-' + index" :alpine-active="$layoutActiveExpr('1-2')" title="2 Columnas (33/66)">1:2</x-admin.button>
                                        <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '2-1')" x-bind:disabled="!canSetColumnsLayout(block, '2-1')" x-bind:aria-describedby="'block-layout-restriction-' + index" :alpine-active="$layoutActiveExpr('2-1')" title="2 Columnas (66/33)">2:1</x-admin.button>
                                        <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '3')" x-bind:disabled="!canSetColumnsLayout(block, '3')" x-bind:aria-describedby="'block-layout-restriction-' + index" :alpine-active="$layoutActiveExpr('3')" title="3 Columnas">3</x-admin.button>
                                        <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '4')" x-bind:disabled="!canSetColumnsLayout(block, '4')" x-bind:aria-describedby="'block-layout-restriction-' + index" :alpine-active="$layoutActiveExpr('4')" title="4 Columnas">4</x-admin.button>
                                    </div>

                                    <p :id="'block-layout-restriction-' + index" x-show="containsUnavailableBlock(block)" x-cloak role="status" class="text-[11px] text-amber-300">
                                        Reactiva los bloques no disponibles antes de moverlos a otra columna, duplicar o eliminar su sección. Puedes seguir editando los demás bloques.
                                    </p>

                                    <x-admin.button type="button" variant="icon" size="sm" x-show="isBlockAvailable(block)" @click="openAppearanceModal(index)" icon="fa-solid fa-palette" title="{{ __('admin.block_manager.appearance_tooltip') }}" aria-label="{{ __('admin.block_manager.appearance_tooltip') }}" />
                                    <x-admin.button type="button" variant="icon" size="sm" x-show="isBlockAvailable(block)" @click="openMotionDialog(index)" icon="fa-solid fa-bolt" title="{{ __('admin.block_manager.motion_tooltip') }}" aria-label="{{ __('admin.block_manager.motion_tooltip') }}" />
                                    <x-admin.button type="button" variant="icon" size="sm" x-show="isBlockAvailable(block) && editorOptions.previewUrl" data-card-preview @click="selectNode([index]); previewSelected()" icon="fa-solid fa-eye" title="{{ __('admin.block_manager.preview_open') }}" aria-label="{{ __('admin.block_manager.preview_open') }}" />
                                    <x-admin.button type="button" variant="icon" size="sm" @click="moveUp(index)" x-bind:disabled="index === 0" title="{{ __('admin.block_manager.move_up') }}" aria-label="{{ __('admin.block_manager.move_up') }}">↑</x-admin.button>
                                    <x-admin.button type="button" variant="icon" size="sm" @click="moveDown(index)" x-bind:disabled="index === blocks.length - 1" title="{{ __('admin.block_manager.move_down') }}" aria-label="{{ __('admin.block_manager.move_down') }}">↓</x-admin.button>
                                    <x-admin.button type="button" variant="icon" size="sm" @click="cloneBlock(index)" x-bind:disabled="containsUnavailableBlock(block)" x-bind:aria-describedby="'block-layout-restriction-' + index" icon="fa-solid fa-copy" title="{{ __('admin.block_manager.duplicate') }}" aria-label="{{ __('admin.block_manager.duplicate') }}" />
                                    <x-admin.button type="button" variant="icon" size="sm" @click="toggleCollapse(block, [index])" x-bind:aria-expanded="!isCollapsed(block, [index])" title="{{ __('admin.block_manager.collapse_toggle') }}" aria-label="{{ __('admin.block_manager.collapse_toggle') }}">
                                        <svg class="w-4 h-4 transition-transform duration-200" :class="isCollapsed(block, [index]) ? '-rotate-90' : 'rotate-0 text-[color:var(--admin-accent)]'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                    </x-admin.button>
                                    <x-admin.button type="button" variant="icon" size="sm" @click="removeBlock(index)" x-bind:disabled="containsUnavailableBlock(block)" x-bind:aria-describedby="'block-layout-restriction-' + index" class="text-red-400 hover:text-red-300" title="{{ __('admin.block_manager.delete_section') }}" aria-label="{{ __('admin.block_manager.delete_section') }}">&times;</x-admin.button>
                                </div>
                            </div>

                            <!-- Block Body (Collapsible) -->
                            <div x-show="!isCollapsed(block, [index])" class="admin-block-body p-5 space-y-6">
                                <div x-show="isBlockAvailable(block)" class="space-y-6">
                                    @include('components.admin.blocks._card_body')
                                </div>
                            </div>
                        </article>

                        <!-- Intermediate / Bottom Insertion Zone -->
                        <div class="admin-block-insert" :data-insert-zone="index + 1">
                            <div class="admin-block-insert-line" aria-hidden="true"></div>
                            <button type="button" @click="addEmptySection(index + 1)" class="admin-block-insert-btn">
                                <i class="fa-solid fa-plus text-[10px]" aria-hidden="true"></i>
                                <span x-text="index === blocks.length - 1 ? @js(__('admin.block_manager.add_section_end')) : @js(__('admin.block_manager.add_section_here'))"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

        </div>
    </div>

    <x-admin.modal
        show="showAddModal"
        :title="__('admin.block_manager.add_block')"
        icon="fa-solid fa-plus"
        max-width="2xl"
        z-index="z-[120]"
        on-close="closeAddModal()"
        :maximizable="false"
        data-add-block-dialog
    >
        <x-slot:subtitleSlot>
            <span x-show="activeCategory !== 'templates'">{{ __('admin.block_manager.insert_block_desc') }}</span>
            <span x-show="activeCategory === 'templates'" x-cloak>{{ __('admin.block_manager.example_templates_hint') }}</span>
            <span x-show="columnTarget" x-cloak class="ml-2 px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30 text-[10px] font-bold"
                  x-text="@js(__('admin.block_manager.scope_column')) + ' ' + (columnTarget ? (columnTarget.colIndex + 1) : 1)"></span>
        </x-slot:subtitleSlot>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div role="tablist" aria-label="{{ __('admin.block_manager.add_block') }}" class="cms-inspector-toolbar-group">
                <x-admin.button type="button" variant="ghost" size="sm" role="tab" @click="activeCategory = 'all'" alpine-active="activeCategory === 'all'" x-bind:aria-selected="activeCategory === 'all'">{{ __('admin.block_manager.cat_all') }}</x-admin.button>
                <x-admin.button type="button" variant="ghost" size="sm" role="tab" @click="activeCategory = 'content'" alpine-active="activeCategory === 'content'" x-bind:aria-selected="activeCategory === 'content'">{{ __('admin.block_manager.tab_content') }}</x-admin.button>
                <x-admin.button type="button" variant="ghost" size="sm" role="tab" @click="activeCategory = 'dynamic'" alpine-active="activeCategory === 'dynamic'" x-bind:aria-selected="activeCategory === 'dynamic'">{{ __('admin.block_manager.tab_dynamic') }}</x-admin.button>
                <x-admin.button type="button" variant="ghost" size="sm" role="tab" @click="activeCategory = 'layout'" alpine-active="activeCategory === 'layout'" x-bind:aria-selected="activeCategory === 'layout'">{{ __('admin.block_manager.tab_layout') }}</x-admin.button>
                <x-admin.button type="button" variant="ghost" size="sm" role="tab" x-show="hasCompositionTemplates()" x-cloak @click="activeCategory = 'templates'" alpine-active="activeCategory === 'templates'" x-bind:aria-selected="activeCategory === 'templates'">{{ __('admin.block_manager.example_templates') }}</x-admin.button>
            </div>

            <input type="text" x-model="searchQuery" :placeholder="activeCategory === 'templates' ? @js(__('admin.block_manager.search_template')) : @js(__('admin.block_manager.search_block'))" class="admin-control w-48">
        </div>

        <div x-show="activeCategory !== 'templates'" class="max-h-[50vh] overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-3 pr-1" role="tabpanel">
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
                            <p x-show="item.fullWidth" x-cloak class="text-[10px] text-[color:var(--admin-accent)] mt-1">{{ __('admin.block_manager.full_width_block_hint') }}</p>
                        </div>
                    </div>
                    <div class="mt-3 pt-2.5 border-t border-[color:var(--admin-border)] flex items-center justify-end gap-2">
                        <x-admin.button type="button" variant="ghost" size="sm" @click="insertType(item.type, 'empty')" :title="__('admin.block_manager.empty_tooltip')">
                            <i class="fa-solid fa-file mr-1" aria-hidden="true"></i> {{ __('admin.block_manager.mode_empty') }}
                        </x-admin.button>
                        <x-admin.button type="button" variant="primary" size="sm" @click="insertType(item.type, 'demo')" :title="__('admin.block_manager.demo_tooltip')">
                            <i class="fa-solid fa-wand-magic-sparkles mr-1" aria-hidden="true"></i> {{ __('admin.block_manager.mode_demo_short') }}
                        </x-admin.button>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="activeCategory === 'templates'" x-cloak class="max-h-[50vh] overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-3 pr-1" role="tabpanel" data-templates-panel>
            <p x-show="!filteredTemplates().length" class="admin-field-hint sm:col-span-2">{{ __('admin.block_manager.templates_empty') }}</p>
            <template x-for="template in filteredTemplates()" :key="'tpl-' + template.id">
                <div class="p-3.5 rounded-xl bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)] transition-all flex flex-col justify-between hover:bg-[color:var(--admin-surface-raised)]/40">
                    <div class="flex items-start gap-3">
                        <span class="text-xl shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-[color:var(--admin-surface)]/50 border border-[color:var(--admin-border)] text-[color:var(--admin-accent)]">
                            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="font-bold text-[color:var(--admin-text)] text-xs" x-text="template.title"></div>
                            <div class="text-[11px] text-[color:var(--admin-muted)] mt-0.5 truncate" x-show="template.slug" x-text="template.slug"></div>
                            <p class="text-[10px] text-[color:var(--admin-muted)] mt-1" x-show="template.section_count" x-text="template.section_count + ' ' + @js(__('admin.block_manager.template_sections'))"></p>
                        </div>
                    </div>
                    <div class="mt-3 pt-2.5 border-t border-[color:var(--admin-border)] flex items-center justify-end gap-2">
                        <x-admin.button type="button" variant="ghost" size="sm" @click="openTemplatePreview(template.id)" :title="__('admin.block_manager.preview_template')">
                            <i class="fa-solid fa-eye mr-1" aria-hidden="true"></i> {{ __('admin.block_manager.preview_open') }}
                        </x-admin.button>
                        <x-admin.button type="button" variant="primary" size="sm" @click="insertTemplate(template.id)" :title="__('admin.block_manager.insert_template')">
                            <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i> {{ __('admin.block_manager.insert_template') }}
                        </x-admin.button>
                    </div>
                </div>
            </template>
        </div>

        <x-slot:footer>
            <x-admin.button type="button" variant="ghost" @click="closeAddModal()">{{ __('admin.common.cancel') }}</x-admin.button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal
        show="showTemplatePreview"
        :title="__('admin.block_manager.preview_template')"
        icon="fa-solid fa-eye"
        max-width="7xl"
        z-index="z-[130]"
        on-close="closeTemplatePreview()"
        panel-class="!p-0 sm:!p-0 overflow-hidden"
        body-class="!space-y-0"
        data-template-preview-dialog
    >
        <x-slot:subtitleSlot>
            <span x-text="previewingTemplate()?.title || ''"></span>
        </x-slot:subtitleSlot>

        <div class="min-h-[60vh] bg-[color:var(--admin-surface)]" data-template-preview>
            <iframe
                x-show="templatePreviewSrc()"
                :src="templatePreviewSrc()"
                class="w-full h-[60vh] border-0 bg-white"
                referrerpolicy="no-referrer"
                title="{{ __('admin.block_manager.preview_template_frame') }}"
            ></iframe>
            <p x-show="!templatePreviewSrc()" class="admin-field-hint p-6">{{ __('admin.block_manager.preview_empty') }}</p>
        </div>

        <x-slot:footer>
            <x-admin.button type="button" variant="ghost" @click="closeTemplatePreview()">{{ __('admin.common.cancel') }}</x-admin.button>
            <x-admin.button type="button" variant="primary" @click="insertPreviewedTemplate()" x-bind:disabled="!previewingTemplate()">{{ __('admin.block_manager.insert_template') }}</x-admin.button>
        </x-slot:footer>
    </x-admin.modal>

</div>
