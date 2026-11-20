import { isSectionContainer, schemaDefaults, cleanEditorValue, cloneBlockTree, containsUnavailable, getAtPath, identifyTree, hasRetiredPayloadKey, columnCount, resizeSectionColumns } from './block-editor-tree.js';
import { createEditorFeatures } from './editor-runtime.js';
import { t } from './editor-labels.js';

export const createBlockManager = (initialLocaleMap = {}, defaultLocale = 'es', initialAvailableTypes = [], availableLanguages = [], focusTarget = null, options = {}) => ({
            ...createEditorFeatures({ ...options, initial: initialLocaleMap }),
            activeLocale: defaultLocale,
            sectionsByLocale: initialLocaleMap,
            languages: availableLanguages,
            focusTargetId: focusTarget,
            blocks: [],
            isFullscreen: false,
            canvasWidth: 'container',
            activeCategory: 'all',
            searchQuery: '',
            showAddModal: false,
            showTemplatePreview: false,
            previewingTemplateId: null,
            insertIndex: null,
            columnTarget: null,
            compositionTemplates: Array.isArray(options.templates) ? options.templates : [],
            copySourceLocale: 'es',
            showDesignDialog: false,
            showMotionDialog: false,
            pendingMediaRequest: null,
            // Las tarjetas son la superficie principal: los slots anidados se
            // editan en línea y la estructura es un panel opcional plegado.
            inspectorMode: false,
            showStructure: false,
            // Estado de interfaz por idioma; nunca forma parte del contenido.
            collapsedByLocale: {},
            designCardKey: null,

            // Emergent for blocks inside a column (or full-width blocks that become a section).
            openAddModalAt(index = null, columnTarget = null) {
                this.insertIndex = index;
                this.columnTarget = columnTarget;
                this.showAddModal = true;
                this.searchQuery = '';
                this.activeCategory = 'all';
            },

            closeAddModal() {
                this.showAddModal = false;
                this.columnTarget = null;
                this.insertIndex = null;
                this.closeTemplatePreview();
            },

            // Empty section with one column; layout and appearance live on the canvas.
            addEmptySection(atIndex = null) {
                this.historyBoundary();
                const section = {
                    id: 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    type: 'text',
                    columns_layout: '1',
                    columns: [{ id: this.newColumnId(0), blocks: [] }],
                };
                const index = atIndex === null || atIndex < 0 || atIndex > this.blocks.length
                    ? this.blocks.length
                    : atIndex;
                this.blocks.splice(index, 0, section);
                this.syncBlocksToLocale();
                this.selectNode([index]);
                this.inspectorTab = 'content';
            },

            convertLegacyBody(content) {
                if (this.blocks.length || typeof content !== 'string' || !content.trim()) return;
                this.addSectionWithBlock('rich_text');
                const block = this.blocks[0]?.columns[0]?.blocks[0];
                if (!block) return;
                Object.assign(block, { format: 'markdown', content });
                this.syncBlocksToLocale();
                this.$dispatch('cms-legacy-body-converted');
            },

            // A block with its own grid becomes a one-column section; otherwise it joins the column.
            insertType(type, mode) {
                const def = this.availableTypes.find(t => t.type === type);

                if (this.columnTarget && !(def && def.fullWidth)) {
                    this.addColumnBlock(this.columnTarget.blockIndex, this.columnTarget.colIndex, type, mode);
                    return;
                }

                this.addSectionWithBlock(type, mode);
            },

            // Copy-on-insert: clone published plantilla sections into the page root list.
            insertTemplate(templateId) {
                if (this.editorOptions?.entityType === 'block_component') return;
                const template = this.compositionTemplates.find(item => String(item.id) === String(templateId));
                if (!template) return;
                const byLocale = template.sectionsByLocale || {};
                const rows = byLocale[this.activeLocale] || byLocale[Object.keys(byLocale)[0]] || [];
                if (!Array.isArray(rows) || !rows.length) return;

                this.historyBoundary();
                let index = this.blocks.length;
                if (this.columnTarget && Number.isInteger(this.columnTarget.blockIndex)) {
                    index = Math.min(this.columnTarget.blockIndex + 1, this.blocks.length);
                } else if (this.insertIndex !== null && this.insertIndex >= 0 && this.insertIndex <= this.blocks.length) {
                    index = this.insertIndex;
                }

                const clones = rows.map(section => cloneBlockTree(section, this.availableTypes));
                this.blocks.splice(index, 0, ...clones);
                this.syncBlocksToLocale();
                this.selectNode([index]);
                this.inspectorTab = 'content';
                this.closeAddModal();
            },

            filteredTemplates() {
                if (this.editorOptions?.entityType === 'block_component') return [];
                const query = (this.searchQuery || '').trim().toLowerCase();
                return this.compositionTemplates.filter(item => {
                    if (!query) return true;
                    return String(item.title || '').toLowerCase().includes(query)
                        || String(item.slug || '').toLowerCase().includes(query);
                });
            },

            hasCompositionTemplates() {
                return this.editorOptions?.entityType !== 'block_component' && this.compositionTemplates.length > 0;
            },

            previewingTemplate() {
                if (this.previewingTemplateId === null || this.previewingTemplateId === undefined) return null;
                return this.compositionTemplates.find(item => String(item.id) === String(this.previewingTemplateId)) || null;
            },

            templatePreviewSrc(template = null) {
                const item = template || this.previewingTemplate();
                if (!item?.preview_url) return '';
                const url = new URL(item.preview_url, globalThis.location?.origin || 'http://localhost');
                url.searchParams.set('locale', this.activeLocale || 'es');
                const marketId = globalThis.cmsContext?.marketIds?.[0];
                if (marketId) url.searchParams.set('market', String(marketId));
                return url.toString();
            },

            openTemplatePreview(templateId) {
                const template = this.compositionTemplates.find(item => String(item.id) === String(templateId));
                if (!template?.preview_url) return;
                this.previewingTemplateId = template.id;
                this.showTemplatePreview = true;
            },

            closeTemplatePreview() {
                this.showTemplatePreview = false;
                this.previewingTemplateId = null;
            },

            insertPreviewedTemplate() {
                const id = this.previewingTemplateId;
                this.closeTemplatePreview();
                if (id !== null && id !== undefined) this.insertTemplate(id);
            },

            toggleFullscreen() {
                this.isFullscreen = !this.isFullscreen;
                if (this.isFullscreen) {
                    document.body.classList.add('overflow-hidden');
                } else {
                    document.body.classList.remove('overflow-hidden');
                }
            },

            saveForm() {
                this.syncBlocksToLocale();
                const form = this.$el.closest('form');
                if (form) {
                    if (form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            },

            availableTypes: initialAvailableTypes,

            // Resolución de iconos de widget: nunca vacío.
            // 1) clase FontAwesome (fa-*), 2) emoji/símbolo, 3) inicial del nombre.
            isBlockAvailable(block) {
                return isSectionContainer(block) || (!!block && this.availableTypes.some(def => def.type === (block.type || 'rich_text')) && !this.isRetiredBlockPayload(block));
            },

            isRetiredBlockPayload(block) {
                return !!block && hasRetiredPayloadKey(block, this.typeDef(block.type).retiredPayloadKeys || []);
            },

            unavailableBlockMessage(block) {
                return this.isRetiredBlockPayload(block)
                    ? t('retired_block')
                    : t('unavailable_block');
            },

            schemaFields(block) {
                return Object.entries(this.typeDef(block.type).schema || {}).map(([key, field]) => ({ key, ...field }));
            },

            fieldVisible(block, field) {
                const rules = Array.isArray(field?.show_when) ? field.show_when : (field?.show_when ? [field.show_when] : []);
                if (rules.length === 0) return true;

                return rules.every((rule) => {
                    if (!rule || typeof rule !== 'object') return true;
                    const value = block?.[rule.field];
                    if (Object.hasOwn(rule, 'equals')) return value === rule.equals;
                    if (Object.hasOwn(rule, 'not_equals')) return value !== rule.not_equals;
                    if (Array.isArray(rule.in)) return rule.in.includes(value);
                    if (Array.isArray(rule.not_in)) return !rule.not_in.includes(value);
                    if (rule.truthy === true) return !!value;
                    if (rule.falsy === true) return !value;
                    return true;
                });
            },

            updateJsonValue(targetObj, key, input) {
                try {
                    const value = JSON.parse(input.value);
                    if (!Array.isArray(value) && (typeof value !== 'object' || value === null)) throw new Error();
                    targetObj[key] = value;
                    input.setCustomValidity('');
                    this.syncBlocksToLocale();
                } catch {
                    input.setCustomValidity(t('json_invalid'));
                    input.reportValidity();
                }
            },

            typeDef(type) {
                return this.availableTypes.find(t => t.type === type) || {};
            },

            isFontAwesomeIcon(icon) {
                return typeof icon === 'string' && icon.includes('fa-');
            },

            typeGlyph(type) {
                const def = this.typeDef(type);
                if (def.icon && !this.isFontAwesomeIcon(def.icon)) {
                    return def.icon;
                }
                const name = (def.name || type || '?').toString().trim();
                return name.charAt(0).toUpperCase();
            },

            animationOptions: [
                { value: 'none', label: t('animation_none') },
                { value: 'fadeIn', label: t('animation_fade_in') },
                { value: 'fadeInUp', label: t('animation_fade_in_up') },
                { value: 'fadeInDown', label: t('animation_fade_in_down') },
                { value: 'fadeInLeft', label: t('animation_fade_in_left') },
                { value: 'fadeInRight', label: t('animation_fade_in_right') },
                { value: 'zoomIn', label: t('animation_zoom_in') },
                { value: 'bounceIn', label: t('animation_bounce_in') },
                { value: 'flipInX', label: t('animation_flip_in_x') }
            ],

            containerStyles: [
                { value: 'cyber-card', label: t('container_cyber_card') },
                { value: 'cyber-glass', label: t('container_cyber_glass') },
                { value: 'neon-glow', label: t('container_neon_glow') },
                { value: 'transparent', label: t('container_transparent') },
                { value: 'full-width-dark', label: t('container_full_width_dark') }
            ],

            dividerTypes: [
                { value: 'none', label: t('divider_none') },
                { value: 'waves', label: t('divider_waves') },
                { value: 'tilt', label: t('divider_tilt') },
                { value: 'curve', label: t('divider_curve') },
                { value: 'triangle', label: t('divider_triangle') },
                { value: 'spikes', label: t('divider_spikes') }
            ],

            init() {
                if (!this.sectionsByLocale[this.activeLocale]) {
                    this.sectionsByLocale[this.activeLocale] = [];
                }
                // Contenido antiguo sin ids en anidados: ids en memoria, sin entrada de historial.
                const identified = Object.values(this.sectionsByLocale).reduce((total, nodes) => total + identifyTree(Array.isArray(nodes) ? nodes : [], this.availableTypes), 0);
                if (identified) this.rebaseHistory();
                this.restoreCollapsed();
                this.blocks = this.sectionsByLocale[this.activeLocale];
                this.initializeEditorLifecycle();

                // Deep-link from the front office: open and reveal the target block.
                this.focusTargetBlock();

                // Listen to external language switches from parent form tabs
                window.addEventListener('cms-locale-switched', (e) => {
                    const newLocale = e.detail?.locale;
                    if (newLocale && newLocale !== this.activeLocale) {
                        this.switchLocale(newLocale);
                    }
                });

                // Listen to external copy command from parent form
                window.addEventListener('cms-copy-locale', (e) => {
                    const fromLocale = e.detail?.from;
                    const toLocale = e.detail?.to || this.activeLocale;
                    if (fromLocale && toLocale) {
                        this.copyBlocksFrom(fromLocale, toLocale);
                    }
                });

                // Generic listener for external block insertion (decoupled from any specific module)
                window.addEventListener('cms-insert-block', (e) => {
                    const block = e.detail?.block;
                    const index = e.detail?.index ?? this.insertIndex ?? null;
                    if (block) {
                        this.insertExternalBlock(block, index);
                    }
                });

                // Generic listener for external block replacement (AI form filling, imports...)
                window.addEventListener('cms-replace-blocks', (e) => {
                    const blocks = e.detail?.blocks;
                    if (Array.isArray(blocks)) {
                        this.setBlocks(blocks);
                    }
                });

                // Translation targets its own locale, preserving section and column identities.
                window.addEventListener('cms-translation-blocks', (event) => {
                    const { form, targetLocale, blocks } = event.detail || {};
                    if (form !== this.$el.closest('form') || !this.languages.some(language => language.code === targetLocale) || !Array.isArray(blocks)) return;
                    this.syncBlocksToLocale();
                    this.sectionsByLocale[targetLocale] = JSON.parse(JSON.stringify(blocks));
                    if (this.activeLocale === targetLocale) this.blocks = this.sectionsByLocale[targetLocale];
                });

                // Listen to market changes from parent entity form to filter fullscreen languages
                window.addEventListener('cms-markets-changed', (e) => {
                    const activeLocales = e.detail?.activeLocales || [];
                    if (activeLocales.length > 0) {
                        this.activeLocales = activeLocales;
                        if (!this.activeLocales.includes(this.activeLocale)) {
                            this.switchLocale(this.activeLocales[0]);
                        }
                    }
                });

                window.addEventListener('file-selected', (e) => this.handleFileSelected(e.detail));
                window.addEventListener('filemanager-closed', (e) => this.handleFileManagerClosed(e.detail));
            },

            activeLocales: [],

            isLanguageActive(code) {
                if (!this.activeLocales || this.activeLocales.length === 0) return true;
                return this.activeLocales.includes(code);
            },

            getCleanSerializedSections() {
                this.syncBlocksToLocale();
                const clean = cleanEditorValue(this.sectionsByLocale);
                const ordered = { [this.activeLocale]: clean[this.activeLocale], ...Object.fromEntries(Object.entries(clean).filter(([locale]) => locale !== this.activeLocale)) };
                return JSON.stringify(ordered);
            },

            insertExternalBlock(block, targetIndex = null) {
                if (!this.isBlockAvailable(block)) return;
                if (!block.id) {
                    block.id = 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                }
                if (targetIndex !== null && targetIndex >= 0 && targetIndex <= this.blocks.length) {
                    this.blocks.splice(targetIndex, 0, block);
                } else {
                    this.blocks.push(block);
                }
                this.insertIndex = null;
                this.syncBlocksToLocale();
            },

            // Replace the whole block sequence (used by generic external producers).
            setBlocks(list) {
                this.clearInspectorDrafts();
                this.blocks = [];
                list.forEach((block) => {
                    if (!block || typeof block !== 'object') {
                        return;
                    }
                    if (!block.id) {
                        block.id = 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                    }
                    this.blocks.push(block);
                });
                this.syncBlocksToLocale();
            },

            syncBlocksToLocale() {
                this.sectionsByLocale[this.activeLocale] = this.blocks;
                this.reconcileInspectorDrafts();
                this.recordHistory();
                this.refreshEditorControls();
                this.refreshInspectorControls();
                this.scheduleCompositionPreview();
            },

            switchLocale(code) {
                this.syncBlocksToLocale();
                this.activeLocale = code;
                if (!this.sectionsByLocale[code]) {
                    this.sectionsByLocale[code] = [];
                }
                this.blocks = this.sectionsByLocale[code];
                this.historyBoundary();
                this.selectedPath = '[]';
                this.selectedId = null;
                this.inspectorColumnId = null;

                const globalContext = window.cmsContext = window.cmsContext || { locale: null, marketIds: [] };
                globalContext.locale = code;
                window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } }));
            },

            /**
             * Focus the block requested via `?section=<id>` (front-office
             * deep-link). Expands it, scrolls it into view and flashes a ring.
             */
            focusTargetBlock() {
                if (! this.focusTargetId) {
                    return;
                }

                const normalize = (value) => String(value ?? '');
                const id = normalize(this.focusTargetId);

                if (id === '') {
                    return;
                }

                let locale = this.activeLocale;
                let index = (this.sectionsByLocale[locale] || []).findIndex((b) => normalize(b.id) === id);

                if (index === -1) {
                    for (const code of Object.keys(this.sectionsByLocale)) {
                        const found = (this.sectionsByLocale[code] || []).findIndex((b) => normalize(b.id) === id);
                        if (found !== -1) {
                            locale = code;
                            index = found;
                            break;
                        }
                    }
                }

                if (index === -1) {
                    return;
                }

                if (locale !== this.activeLocale) {
                    this.switchLocale(locale);
                }

                if (this.blocks[index]) {
                    this.selectNode([index]);
                }

                this.$nextTick(() => {
                    const el = this.$el.querySelector('[data-block-index="' + index + '"]');
                    if (! el) {
                        return;
                    }

                    el.classList.add('ring-2', 'ring-[#00FF99]/60', 'rounded-2xl');
                    window.setTimeout(() => el.classList.remove('ring-2', 'ring-[#00FF99]/60'), 2400);

                    // The admin content lives in an `overflow-y-auto` <main>, so
                    // window-level scrollIntoView is a no-op: scroll the nearest
                    // scrollable ancestor explicitly. Runs twice because images and
                    // fonts keep growing the layout right after init.
                    const doScroll = () => {
                        let scroller = el.parentElement;
                        while (scroller && scroller !== document.body) {
                            const style = window.getComputedStyle(scroller);
                            if (/(auto|scroll)/.test(style.overflowY) && scroller.scrollHeight > scroller.clientHeight) {
                                break;
                            }
                            scroller = scroller.parentElement;
                        }

                        if (scroller && scroller !== document.body) {
                            const offset = el.getBoundingClientRect().top - scroller.getBoundingClientRect().top;
                            // Instant (not `behavior: smooth`): deterministic across
                            // engines and headless renderers.
                            scroller.scrollTop = Math.max(0, scroller.scrollTop + offset - Math.round(scroller.clientHeight / 3));
                        } else {
                            el.scrollIntoView({ block: 'center' });
                        }
                    };

                    doScroll();
                    window.setTimeout(doScroll, 400);
                });
            },

            copyBlocksFrom(sourceLocale, targetLocale = null) {
                const target = targetLocale || this.activeLocale;
                if (!this.sectionsByLocale[sourceLocale]) return;

                this.historyBoundary();
                // Translation copies retain structural identities across languages.
                const clonedBlocks = JSON.parse(JSON.stringify(this.sectionsByLocale[sourceLocale]));

                this.sectionsByLocale[target] = clonedBlocks;
                if (target === this.activeLocale) {
                    this.blocks = this.sectionsByLocale[target];
                }
                this.recordHistory();
            },

            addSectionWithBlock(type, forceMode = 'empty') {
                this.historyBoundary();
                if (!this.availableTypes.some(def => def.type === type)) return;

                const child = Object.assign({
                    id: 'sub_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    type: type,
                }, this.initialBlockPayload(type, forceMode));

                const section = {
                    id: 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    type: type,
                    columns_layout: '1',
                    columns: [{ id: this.newColumnId(0), blocks: [child] }],
                };

                let index = this.blocks.length;
                if (this.columnTarget && Number.isInteger(this.columnTarget.blockIndex)) {
                    index = Math.min(this.columnTarget.blockIndex + 1, this.blocks.length);
                } else if (this.insertIndex !== null && this.insertIndex >= 0 && this.insertIndex <= this.blocks.length) {
                    index = this.insertIndex;
                }

                this.blocks.splice(index, 0, section);
                this.syncBlocksToLocale();
                this.selectNode([index]);
                this.inspectorTab = 'content';
                this.closeAddModal();
            },

            initialBlockPayload(type, mode) {
                const definition = this.typeDef(type);
                const payload = schemaDefaults(definition.schema);
                if (mode !== 'demo') return payload;
                const example = definition.defaultPayloadByLocale?.[this.activeLocale] || definition.defaultPayload || {};
                Object.assign(payload, JSON.parse(JSON.stringify(example)));
                // Nested example children (tabs, accordion, carousel…) need their own ids.
                identifyTree([{ type, ...payload }], this.availableTypes);
                return payload;
            },

            removeBlock(index) {
                this.historyBoundary();
                if (this.containsUnavailableBlock(this.blocks[index])) return;
                if (confirm(t('confirm_delete_block'))) {
                    this.blocks.splice(index, 1);
                    this.syncBlocksToLocale();
                    if (!this.selectedNode()) { this.selectNode(this.blocks.length ? [Math.min(index, this.blocks.length - 1)] : []); if (!this.blocks.length) this.previewHtml = ''; }
                }
            },

            cloneBlock(index) {
                if (this.containsUnavailableBlock(this.blocks[index])) return;
                this.historyBoundary();
                const cloned = cloneBlockTree(this.blocks[index], this.availableTypes);
                if (cloned.title) cloned.title += ' (Copia)';
                this.blocks.splice(index + 1, 0, cloned);
                this.syncBlocksToLocale();
            },

            moveUp(index) {
                this.historyBoundary();
                if (index > 0) {
                    const temp = this.blocks[index];
                    this.blocks[index] = this.blocks[index - 1];
                    this.blocks[index - 1] = temp;
                    this.syncBlocksToLocale();
                }
            },

            moveDown(index) {
                this.historyBoundary();
                if (index < this.blocks.length - 1) {
                    const temp = this.blocks[index];
                    this.blocks[index] = this.blocks[index + 1];
                    this.blocks[index + 1] = temp;
                    this.syncBlocksToLocale();
                }
            },

            nodeKey(node, path = []) {
                return node && node.id !== undefined && node.id !== null && node.id !== '' ? String(node.id) : 'path:' + JSON.stringify(path);
            },

            collapsedMap(locale = this.activeLocale) {
                this.collapsedByLocale[locale] ??= {};
                return this.collapsedByLocale[locale];
            },

            isCollapsed(node, path = []) {
                return !!this.collapsedByLocale[this.activeLocale]?.[this.nodeKey(node, path)];
            },

            setCollapsed(node, path, collapsed, persist = true) {
                const map = this.collapsedMap(); const key = this.nodeKey(node, path);
                if (collapsed) map[key] = true; else delete map[key];
                if (persist) this.persistCollapsed();
            },

            // El plegado es estado de interfaz: vive en sessionStorage, nunca en el contenido ni en el historial.
            collapseStorageKey() {
                return 'dbcms:block-collapse:' + (this.editorOptions?.entityType || 'page') + ':' + (this.editorOptions?.entityId ?? 'new');
            },

            restoreCollapsed() {
                try {
                    const stored = JSON.parse(globalThis.sessionStorage?.getItem(this.collapseStorageKey()) || '{}');
                    if (stored && typeof stored === 'object' && !Array.isArray(stored)) this.collapsedByLocale = stored;
                } catch { /* almacenamiento no disponible */ }
            },

            persistCollapsed() {
                try { globalThis.sessionStorage?.setItem(this.collapseStorageKey(), JSON.stringify(this.collapsedByLocale)); } catch { /* almacenamiento no disponible */ }
            },

            // Abre cada ancestro (y el propio nodo) de una ruta seleccionada.
            expandPath(path = []) {
                const map = this.collapsedByLocale[this.activeLocale];
                if (!map || !Array.isArray(path)) return;
                let changed = false;
                for (let length = 1; length <= path.length; length++) {
                    const prefix = path.slice(0, length); const node = getAtPath(this.blocks, prefix);
                    if (!node || typeof node !== 'object' || Array.isArray(node)) continue;
                    const key = this.nodeKey(node, prefix);
                    if (map[key]) { delete map[key]; changed = true; }
                }
                if (changed) this.persistCollapsed();
            },

            toggleCollapse(node, path = []) {
                this.setCollapsed(node, path, !this.isCollapsed(node, path));
            },

            toggleCollapseAll(collapse) {
                this.blocks.forEach((block, index) => this.setCollapsed(block, [index], collapse, false));
                this.persistCollapsed();
            },

            selectedRowPath() {
                if (this.selectedId !== null && this.selectedId !== undefined) {
                    const row = this.structureRows().find(item => String(item.node.id) === String(this.selectedId));
                    return row ? row.path : [];
                }
                try { return JSON.parse(this.selectedPath || '[]'); } catch { return []; }
            },

            isNodeInSelection(path) {
                const selected = this.selectedRowPath();
                return selected.length >= path.length && path.every((part, index) => selected[index] === part) && !!this.selectedNode();
            },

            dialogBusy() {
                return this.showDesignDialog || this.showMotionDialog || this.showPreview || this.showAddModal || this.showTemplatePreview;
            },

            resolveDesignTarget(index, target = null) {
                if (target && target.subIndex === undefined) {
                    const path = [index, 'columns', target.colIndex];
                    if (!getAtPath(this.blocks, path)) return false;
                    this.selectColumn(path);
                    return true;
                }
                const path = target ? [index, 'columns', target.colIndex, 'blocks', target.subIndex] : [index];
                const node = getAtPath(this.blocks, path);
                if (!node || ((target || node.type) && !this.isBlockAvailable(node))) return false;
                this.selectNode(path);
                return true;
            },

            openDesignDialog(index, target = null) {
                const previous = this.inspectorSelectionKey();
                if (!this.resolveDesignTarget(index, target)) return;
                if (this.showDesignDialog && this.inspectorSelectionKey() === previous) {
                    this.closeDesignDialog();
                    return;
                }
                this.showMotionDialog = false;
                this.showDesignDialog = true;
                this.inspectorTab = 'design';
            },

            closeDesignDialog() {
                this.showDesignDialog = false;
                this.inspectorTab = 'content';
                this.syncBlocksToLocale();
            },

            openMotionDialog(index, target = null) {
                if (target && target.subIndex === undefined) return;
                const previous = this.inspectorSelectionKey();
                if (!this.resolveDesignTarget(index, target) || !this.canEditMotion()) return;
                if (this.showMotionDialog && this.inspectorSelectionKey() === previous) {
                    this.closeMotionDialog();
                    return;
                }
                this.showDesignDialog = false;
                this.showMotionDialog = true;
            },

            closeMotionDialog() {
                this.showMotionDialog = false;
            },

            openNodeDesign(path) {
                const index = path[0];
                if (path.length >= 5 && path[1] === 'columns' && path[3] === 'blocks') {
                    this.openDesignDialog(index, { colIndex: path[2], subIndex: path[4] });
                    return;
                }
                this.openDesignDialog(index);
            },

            toggleColumnDesign(path) {
                const column = getAtPath(this.blocks, path);
                if (!column) return;
                if (this.showDesignDialog && this.inspectorColumnId === column.id) {
                    this.closeDesignDialog();
                    return;
                }
                this.selectColumn(path);
                this.showMotionDialog = false;
                this.showDesignDialog = true;
                this.inspectorTab = 'design';
            },

            syncDesignCard() {},

            revealNode(path) {
                this.selectNode(path);
                this.scrollToCard(path[0]);
            },

            revealColumn(path) {
                this.selectColumn(path);
                this.scrollToCard(path[0]);
            },

            scrollToCard(index) {
                this.$nextTick?.(() => this.$el?.querySelector('[data-block-index="' + index + '"]')?.scrollIntoView?.({ block: 'start' }));
            },

            copySources() {
                return Object.keys(this.sectionsByLocale).filter(code => code !== this.activeLocale && (this.sectionsByLocale[code] || []).length > 0);
            },

            hasCopySources() {
                return this.copySources().length > 0;
            },

            confirmCopyBlocksFrom(source) {
                const sources = this.copySources();
                const from = sources.includes(source) ? source : sources[0];
                if (!from) return;
                if (this.blocks.length && !confirm(t('confirm_copy_locale', { locale: from.toUpperCase() }))) return;
                this.copyBlocksFrom(from);
            },

            // «Diseño» abre el emergente compartido sobre <x-admin.modal>.
            openAppearanceModal(index, target = null) {
                if (target && target.subIndex === undefined) {
                    this.toggleColumnDesign([index, 'columns', target.colIndex]);
                    return;
                }
                this.openDesignDialog(index, target);
            },

            // Abre la apariencia de UNA columna (ámbito `column`).
            openColumnAppearanceModal(index, colIndex) {
                this.openAppearanceModal(index, { colIndex });
            },

            // Cambia el número de columnas sin perder nada: al volver a una
            // columna los bloques se apilan en orden, y al expandir se crean (o
            // recuperan) las columnas necesarias. El contenido propio de la
            // sección pasa a ser el primer bloque de la primera columna.
            containsUnavailableBlock(block) {
                return containsUnavailable(block, this.availableTypes);
            },

            canSetColumnsLayout(block, layout) {
                if (!block) return false;
                const columns = Array.isArray(block.columns) ? block.columns : [];
                if (!columns.length) return this.isBlockAvailable(block) && !this.containsUnavailableBlock(block);
                const count = columnCount(layout);
                // Reducing columns would re-parent these nodes; the server preserves
                // unavailable instances in their stored column until reactivation.
                return !columns.slice(count).some(column => (column?.blocks || []).some(child => this.containsUnavailableBlock(child)));
            },

            setColumnsLayout(blockIndex, layout) {
                this.historyBoundary();
                const block = this.blocks[blockIndex];
                if (!this.canSetColumnsLayout(block, layout)) return;

                const count = columnCount(layout);
                block.columns_layout = layout;

                const hasColumns = Array.isArray(block.columns) && block.columns.length > 0;
                const sectionPayload = this.sectionAsBlock(block);
                const sectionHasContent = !!block.type;

                // Primera vez que se abre a columnas: el contenido de la sección
                // se convierte en el primer bloque de la primera columna.
                if (!hasColumns && count > 1 && sectionHasContent) {
                    block.columns = [{ id: this.newColumnId(0), blocks: [sectionPayload] }];
                    this.clearSectionContent(block);
                }

                block.columns = resizeSectionColumns(block.columns, count, index => this.newColumnId(index));
                this.syncBlocksToLocale();
            },

            // Layout efectivo de un bloque: `columns_layout` o '1' (una columna).
            activeLayout(block) {
                return (block && block.columns_layout) ? block.columns_layout : '1';
            },

            // Columnas de un bloque: siempre el contrato canónico `columns`.
            blockColumns(block) {
                if (!block) return [];

                if (!Array.isArray(block.columns)) {
                    block.columns = [];
                }

                block.columns.forEach((c, i) => {
                    if (!c || typeof c !== 'object') return;
                    c.id = c.id || this.newColumnId(i);
                    c.blocks = Array.isArray(c.blocks) ? c.blocks : [];
                });

                return block.columns;
            },

            newColumnId(index) {
                return 'col_' + index + '_' + Math.random().toString(36).substring(2, 6);
            },

            // La sección vista como bloque (sin las claves del contenedor).
            sectionAsBlock(block) {
                const payload = JSON.parse(JSON.stringify(block));
                delete payload.columns;
                delete payload._columns;
                delete payload.columns_layout;
                delete payload.id;
                delete payload.is_collapsed;
                delete payload._col_buffer;
                return payload;
            },

            hasColumnContent(block) {
                return Array.isArray(block?.columns) && block.columns.length > 0;
            },

            clearSectionContent(block) {
                const fields = new Set([
                    ...Object.keys(this.typeDef(block.type).schema || {}),
                    ...Object.keys(this.typeDef(block.type).defaultPayload || {}),
                    'title', 'headline', 'content', 'body', 'summary', 'subtitle', 'items',
                ]);
                fields.forEach((key) => { delete block[key]; });
            },

            addColumnBlock(blockIndex, colIndex, type, forceMode = null) {
                this.historyBoundary();
                if (!this.availableTypes.some(def => def.type === type)) return;
                const block = this.blocks[blockIndex];
                const cols = this.blockColumns(block);
                if (!block || !cols || !cols[colIndex]) return;

                const newSubBlock = Object.assign({
                    id: 'sub_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    type: type,
                }, this.initialBlockPayload(type, forceMode || 'empty'));

                const list = cols[colIndex].blocks ??= [];
                const at = this.columnTarget && Number.isInteger(this.columnTarget.insertAt)
                    ? Math.max(0, Math.min(this.columnTarget.insertAt, list.length))
                    : list.length;
                list.splice(at, 0, newSubBlock);
                this.syncBlocksToLocale();
                this.closeAddModal();
            },

            removeColumnBlock(blockIndex, colIndex, subIndex) {
                this.historyBoundary();
                const block = this.blocks[blockIndex];
                const cols = this.blockColumns(block);
                if (!block || !cols || this.containsUnavailableBlock(cols[colIndex]?.blocks?.[subIndex])) return;
                cols[colIndex].blocks.splice(subIndex, 1);
                this.syncBlocksToLocale();
            },

            moveColumnBlockUp(blockIndex, colIndex, subIndex) {
                this.historyBoundary();
                if (subIndex <= 0) return;
                const cols = this.blockColumns(this.blocks[blockIndex]);
                const col = cols[colIndex];
                if (this.containsUnavailableBlock(col?.blocks?.[subIndex])) return;
                const item = col.blocks.splice(subIndex, 1)[0];
                col.blocks.splice(subIndex - 1, 0, item);
                this.syncBlocksToLocale();
            },

            moveColumnBlockDown(blockIndex, colIndex, subIndex) {
                this.historyBoundary();
                const cols = this.blockColumns(this.blocks[blockIndex]);
                const col = cols[colIndex];
                if (this.containsUnavailableBlock(col?.blocks?.[subIndex]) || subIndex >= col.blocks.length - 1) return;
                const item = col.blocks.splice(subIndex, 1)[0];
                col.blocks.splice(subIndex + 1, 0, item);
                this.syncBlocksToLocale();
            },

            openBlockMedia(targetObj, fieldName, field = {}) {
                const altKey = field.alt_key || (fieldName === 'image_url' && Object.hasOwn(targetObj, 'image_alt') ? 'image_alt' : null);
                this.openMediaPickerForObject(targetObj, fieldName, field.media_kind || 'image', altKey);
            },

            openMediaPickerForObject(targetObj, fieldName, kind = 'all', altKey = null) {
                this.beginMediaRequest({
                    mode: 'url',
                    targetObj,
                    fieldName,
                    kind,
                    altKey,
                });
            },

            beginMediaRequest(request) {
                const targetId = 'media_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                this.pendingMediaRequest = Object.assign({}, request, { targetId });

                window.dispatchEvent(new CustomEvent('open-filemanager', {
                    detail: { target: targetId, kind: request.kind || 'all' }
                }));
            },

            handleFileSelected(detail) {
                const request = this.pendingMediaRequest;
                if (!request || !detail || detail.target !== request.targetId || !detail.file) return;

                const file = detail.file;
                if (request.mode === 'inspector') request.onSelect?.(file);
                else {
                    request.targetObj[request.fieldName] = file.url;
                    if (request.altKey && !request.targetObj[request.altKey]) {
                        request.targetObj[request.altKey] = file.alt_text || file.title || '';
                    }
                }

                this.pendingMediaRequest = null;
                this.syncBlocksToLocale();
            },

            handleFileManagerClosed(detail) {
                if (!this.pendingMediaRequest) return;
                if (!detail?.target || detail.target === this.pendingMediaRequest.targetId) {
                    this.pendingMediaRequest = null;
                }
            },

            clearBlockMedia(targetObj, fieldName) {
                targetObj[fieldName] = '';
                this.syncBlocksToLocale();
            },

            filteredTypes() {
                if (this.activeCategory === 'templates') return [];
                return this.availableTypes.filter(item => {
                    const itemCat = (item.category === 'dynamic' || item.category === 'interactive' || item.category === 'widget')
                        ? 'dynamic'
                        : (item.category === 'layout' ? 'layout' : 'content');
                    const matchesCategory = this.activeCategory === 'all'
                        || this.activeCategory === itemCat
                        || this.activeCategory === item.category;
                    const q = this.searchQuery ? this.searchQuery.toLowerCase().trim() : '';
                    const matchesSearch = !q ||
                        (item.name && item.name.toLowerCase().includes(q)) ||
                        (item.desc && item.desc.toLowerCase().includes(q)) ||
                        (item.type && item.type.toLowerCase().includes(q));

                    return matchesCategory && matchesSearch;
                });
            },

            sectionTitle(block) {
                if (this.hasColumnContent(block)) {
                    return t('section_label');
                }

                return this.typeDef(block.type).name || block.type;
            },


        });
