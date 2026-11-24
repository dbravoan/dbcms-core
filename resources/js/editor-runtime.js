import { copy, newNodeId, schemaDefaults, cleanEditorValue, getAtPath, treeRows, childSlots, cloneBlockTree, containsUnavailable, EditorHistory } from './block-editor-tree.js';
import { mediaIconButton } from './media-field.js';
import { t, setEditorLabels } from './editor-labels.js';
import { createRichTextEditor } from './rich-text-editor.js';
import { createInspectorFeatures, schemaContentFields, schemaDesignFields, clearOwnerDesignOverrides, DebouncedPreview, canMoveNodeInto } from './block-inspector.js';

const mounted = new WeakMap();
const schemaHosts = new Set();
let blockEditorLoaders = {};
export function setBlockEditorLoaders(loaders) {
    blockEditorLoaders = loaders;
}

async function requestPreview(url, input, signal) {
    const response = await fetch(url, {
        method: 'POST', credentials: 'same-origin', signal,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify(input),
    });
    const body = await response.json();
    if (!response.ok) {
        const error = new Error(body.message || t('preview_failed'));
        error.fields = body.errors;
        throw error;
    }
    return previewNonce(body.document || body.html || '', document.querySelector('script[nonce]')?.nonce);
}
const sortableLists = new WeakMap();
const referenceMetadata = new WeakMap();
const controlClass = 'admin-control w-full';
const buttonClass = 'admin-btn admin-btn--ghost admin-btn--sm';
const raw = value => globalThis.Alpine?.raw?.(value) ?? value;

export function previewNonce(html, nonce) {
    if (!nonce || !/^[A-Za-z0-9+/_=-]+$/.test(nonce)) return html;
    return String(html).replace(/(\snonce\s*=\s*)(["'])[^"']*\2/gi, (_attribute, prefix, quote) => `${prefix}${quote}${nonce}${quote}`);
}

function element(tag, text, className) {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
}
function button(label, callback, { disabled = false, title = label } = {}) {
    const node = element('button', label, buttonClass); node.type = 'button'; node.disabled = disabled; node.title = title;
    node.addEventListener('click', callback); return node;
}
// Compact 192c301 media selector: preview thumb, URL input, library icon and clear icon in one row.
function mediaControl(manager, target, key, field, input) {
    const video = (field.media_kind || 'image') !== 'image';
    const row = element('div', undefined, 'cms-media-field'); row.dataset.mediaField = video ? 'video' : 'image';
    const thumb = element('span', undefined, 'cms-media-field-thumb'); thumb.setAttribute('aria-hidden', 'true');
    const paint = () => {
        const value = String(input.value || '').trim(); thumb.replaceChildren(); thumb.dataset.filled = value ? 'true' : 'false';
        if (value && !video) { const image = element('img'); image.src = value; image.alt = ''; image.loading = 'lazy'; image.addEventListener('error', () => image.replaceWith(element('i', undefined, 'fa-solid fa-image'))); thumb.append(image); }
        else thumb.append(element('i', undefined, `fa-solid ${value ? 'fa-video' : 'fa-image'}`));
    };
    input.classList.add('cms-media-field-input'); if (!field.placeholder) input.placeholder = t('media_placeholder');
    const pick = mediaIconButton(video ? 'fa-film' : 'fa-image', t('media_library'), video ? t('media_pick_video') : t('media_pick_image'), () => manager.openBlockMedia(target, key, field), 'cms-media-field-pick');
    const clear = mediaIconButton('fa-xmark', t('media_clear'), t('media_clear'), () => { manager.clearBlockMedia(target, key); input.value = ''; paint(); }, 'cms-media-field-clear');
    input.addEventListener('input', paint); input._cmsAfterRefresh = paint; paint();
    row.append(thumb, input, pick, clear);
    return [row];
}
function dateTimeInput(target, key, changed) {
    const input = element('input', undefined, controlClass); input.type = 'datetime-local'; input.step = '1'; input.id = `cms-field-${newNodeId()}`;
    const timezone = element('select', undefined, controlClass); timezone.setAttribute('aria-label', t('utc_offset_label'));
    for (let minutes = -720; minutes <= 840; minutes += 15) {
        const absolute = Math.abs(minutes); const value = `${minutes < 0 ? '-' : '+'}${String(Math.floor(absolute / 60)).padStart(2, '0')}:${String(absolute % 60).padStart(2, '0')}`;
        const option = element('option', `UTC${value}`); option.value = value; timezone.append(option);
    }
    const parts = /^(.*?)(Z|[+-]\d\d:\d\d)$/.exec(raw(target)[key] || '');
    input.value = parts?.[1] || ''; timezone.value = parts?.[2] === 'Z' ? '+00:00' : (parts?.[2] || '+00:00');
    input._cmsBinding = { target, key, field: { type: 'text' }, part: 'date' }; timezone._cmsBinding = { target, key, field: { type: 'text' }, part: 'offset' };
    const update = () => { target[key] = input.value ? input.value + (timezone.value || '+00:00') : ''; changed(); };
    input.addEventListener('input', update); timezone.addEventListener('change', update); input._cmsOffsetControl = timezone;
    return input;
}

function boundInput(target, key, field, changed) {
    const input = element(field.type === 'textarea' || ['json', 'array', 'list'].includes(field.type) ? 'textarea' : field.type === 'select' ? 'select' : 'input', undefined, field.type === 'boolean' ? undefined : controlClass);
    input.id = `cms-field-${newNodeId()}`;
    if (input.tagName === 'INPUT') input.type = field.type === 'boolean' ? 'checkbox' : ['number', 'integer'].includes(field.type) ? 'number' : field.type === 'email' ? 'email' : field.type === 'color' ? 'color' : 'text';
    if (field.type === 'select') for (const [value, label] of Object.entries(field.options || {})) { const option = element('option', label); option.value = Array.isArray(field.options) ? label : value; input.append(option); }
    if (field.min !== undefined) input.min = field.min;
    if (field.max !== undefined) input.max = field.max;
    if (input.type === 'number') input.step = field.step ?? (field.type === 'integer' ? 1 : 'any');
    if (field.required) input.required = true;
    if (field.placeholder) input.placeholder = field.placeholder;
    input._cmsBinding = { target, key, field };
    const value = Object.hasOwn(raw(target), key) ? raw(target)[key] : field.default;
    if (field.type === 'boolean') input.checked = !!value;
    else if (['json', 'array'].includes(field.type)) input.value = JSON.stringify(value ?? {}, null, 2);
    else if (field.type === 'list') input.value = (value || []).join('\n');
    else input.value = value ?? '';
    input.addEventListener(field.type === 'boolean' || field.type === 'select' || ['json', 'array', 'list'].includes(field.type) ? 'change' : 'input', () => {
        let next = input.value;
        if (field.type === 'boolean') next = input.checked;
        if (['number', 'integer'].includes(field.type)) next = input.value === '' ? null : Number(input.value);
        if (['json', 'array'].includes(field.type)) {
            try { next = JSON.parse(input.value); if (!next || typeof next !== 'object') throw new Error(); input.setCustomValidity(''); }
            catch { input.setCustomValidity(t('json_invalid')); input.reportValidity(); return; }
        }
        if (field.type === 'list') next = input.value.split('\n').filter(line => line.trim());
        target[key] = next; changed();
    });
    return input;
}

async function attachSortable(list, descriptor, cleanups) {
    cleanups.push(() => { descriptor.cancelled = true; });
    const { default: Sortable } = await import('sortablejs');
    if (!list.isConnected || descriptor.cancelled) return;
    sortableLists.set(list, descriptor);
    const moveContext = event => {
        const from = sortableLists.get(event.from); const to = sortableLists.get(event.to);
        const index = Number.isInteger(event.oldDraggableIndex) ? event.oldDraggableIndex : [...(event.from?.children || [])].filter(child => child.hasAttribute('data-editor-node')).indexOf(event.dragged || event.item);
        const node = from?.nodes[index];
        const allowed = !!from && !!to && !!node && raw(from.manager) === raw(to.manager) && !containsUnavailable(node, from.manager.availableTypes) && canMoveNodeInto(node, to.nodes, from.manager.availableTypes);
        return { from, to, index, node, allowed };
    };
    const rejectMove = (event, context) => {
        const item = event.item || event.dragged;
        if (item && event.from && Number.isInteger(context.index) && context.index >= 0) { item.remove(); const before = [...event.from.children].filter(child => child.hasAttribute('data-editor-node'))[context.index]; event.from.insertBefore(item, before || null); }
        context.from?.render(); if (context.to && context.to !== context.from) context.to.render();
    };
    const groupName = descriptor.root ? 'cms-sections' : descriptor.alpine ? 'cms-column-blocks' : 'cms-nested-slots';
    const sortable = Sortable.create(list, {
        group: groupName, animation: globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 0 : 150,
        handle: '[data-node-drag]', draggable: '[data-editor-node]', filter: '[data-node-unavailable]',
        onMove(event) {
            const context = moveContext(event);
            // Columns share a group so blocks move between columns; never onto the section root.
            if (context.from?.root !== context.to?.root) return false;
            if (!!context.from?.alpine !== !!context.to?.alpine && (context.from?.alpine || context.to?.alpine)) return false;
            return !event.dragged.contains(event.to) && !event.dragged.hasAttribute('data-node-unavailable') && context.allowed;
        },
        onAdd(event) { const context = moveContext(event); if (!context.allowed) rejectMove(event, context); },
        onEnd(event) {
            const context = moveContext(event); const { from, to, node } = context;
            if (!from || !to) return;
            const oldIndex = context.index; const newIndex = event.newDraggableIndex;
            const pointer = event.originalEvent?.changedTouches?.[0] || event.originalEvent;
            const hit = pointer && Number.isFinite(pointer.clientX) ? document.elementFromPoint(pointer.clientX, pointer.clientY) : null;
            const intended = sortableLists.get(hit?.closest('[data-editor-slot-list], [data-editor-root-list]'));
            const sameFamily = !intended || (!!intended.root === !!from.root && !!intended.alpine === !!from.alpine);
            const intendedAllowed = !intended || (sameFamily && raw(intended.manager) === raw(from.manager) && canMoveNodeInto(node, intended.nodes, from.manager.availableTypes));
            if (!context.allowed || !intendedAllowed || !Number.isInteger(newIndex) || !!from.root !== !!to.root) { rejectMove(event, context); return; }
            // Alpine/root own the DOM: revert Sortable's DOM move, then mutate the model.
            if (from.root || to.root || from.alpine || to.alpine) {
                event.item.remove();
                const nodes = [...event.from.children].filter(child => child.hasAttribute('data-editor-node'));
                event.from.insertBefore(event.item, nodes[oldIndex] || null);
            }
            descriptor.manager.historyBoundary();
            from.nodes.splice(oldIndex, 1); to.nodes.splice(newIndex, 0, node);
            descriptor.manager.syncBlocksToLocale();
            from.render(); if (to !== from) to.render();
        },
    });
    cleanups.push(() => { descriptor.cancelled = true; sortable.destroy(); sortableLists.delete(list); });
}

function renderSchema(host, manager, target, fields, path, cleanups, blockType = target.type) {
    for (const [key, field] of Object.entries(fields || {})) {
        if (target.type === 'rich_text' && key === 'format') continue;
        if (target.type === 'rich_text' && key === 'content') {
            const surface = element('div');
            surface.dataset.richTextEditor = '';
            const editor = createRichTextEditor(field.label || key);
            const read = () => ({format: target.format ?? 'markdown', content: target.content ?? ''});
            host.append(surface);
            const effect = globalThis.Alpine.effect(() => editor.refresh(surface, read, source => {
                Object.assign(target, source);
                manager.syncBlocksToLocale();
            }));
            cleanups.push(() => {
                globalThis.Alpine.release(effect);
                editor.destroy();
            });
            continue;
        }
        const group = element('div', undefined, 'admin-field');
        const label = element('label', field.label || key, 'admin-field-label'); group.append(label);
        const fieldPath = [...path, key];
        if (!manager.fieldVisible(target, field)) { group.hidden = true; }
        if (field.type === 'blocks') {
            if (!Array.isArray(target[key])) target[key] = [];
            const slot = element('div', undefined, 'admin-panel space-y-3 p-3');
            const list = element('div', undefined, 'space-y-3 min-h-12'); list.dataset.editorSlotList = 'true';
            const toolbar = element('div', undefined, 'flex flex-wrap gap-2');
            const select = element('select', undefined, controlClass); select.setAttribute('aria-label', t('block_type_for', { label: field.label || key }));
            for (const definition of manager.availableTypes) { const option = element('option', definition.name); option.value = definition.type; select.append(option); }
            toolbar.append(select, button(t('add_block'), () => {
                if (!select.value || !manager.availableTypes.some(item => item.type === select.value)) return;
                manager.historyBoundary();
                target[key].push({ id: newNodeId(), type: select.value, ...manager.initialBlockPayload(select.value, 'empty') });
                manager.syncBlocksToLocale(); render();
            }));
            let localCleanups = [];
            const render = () => {
                localCleanups.splice(0).reverse().forEach(cleanup => cleanup()); list.replaceChildren();
                target[key].forEach((node, index) => {
                    const available = manager.isBlockAvailable(node);
                    const article = element('article', undefined, 'admin-panel space-y-3 p-3'); article.dataset.editorNode = node.id || String(index);
                    if (!available || containsUnavailable(node, manager.availableTypes)) article.dataset.nodeUnavailable = 'true';
                    const tools = element('div', undefined, 'flex flex-wrap items-center gap-2');
                    const handle = button('↕', () => {}, { disabled: !available, title: t('drag_block') }); handle.dataset.nodeDrag = 'true';
                    const move = direction => { if (!available || containsUnavailable(node, manager.availableTypes)) return; const next = index + direction; if (next < 0 || next >= target[key].length) return; manager.historyBoundary(); target[key].splice(index, 1); target[key].splice(next, 0, node); manager.syncBlocksToLocale(); render(); };
                    handle.addEventListener('keydown', event => { if (event.altKey && ['ArrowUp', 'ArrowDown'].includes(event.key)) { event.preventDefault(); move(event.key === 'ArrowUp' ? -1 : 1); } });
                    const title = manager.typeDef(node.type).name || node.type;
                    tools.append(handle, button(title, () => { const entry = manager.structureRows().find(row => raw(row.node) === raw(node)); if (entry) manager.selectNode(entry.path); }), button('↑', () => move(-1), { disabled: index === 0 || !available, title: t('move_block_up') }), button('↓', () => move(1), { disabled: index === target[key].length - 1 || !available, title: t('move_block_down') }), button(t('duplicate'), () => { if (containsUnavailable(node, manager.availableTypes)) return; manager.historyBoundary(); target[key].splice(index + 1, 0, cloneBlockTree(node, manager.availableTypes)); manager.syncBlocksToLocale(); render(); }, { disabled: !available || containsUnavailable(node, manager.availableTypes) }), button(t('delete'), () => { if (containsUnavailable(node, manager.availableTypes)) return; manager.historyBoundary(); target[key].splice(index, 1); manager.syncBlocksToLocale(); render(); }, { disabled: !available || containsUnavailable(node, manager.availableTypes) }));
                    article.append(tools);
                    if (available && !manager.inspectorMode) tools.append(button(t('design'), () => { const entry = manager.structureRows().find(row => raw(row.node) === raw(node)); if (entry) manager.openNodeDesign?.(entry.path); }, { title: t('design_this_block') }));
                    if (available && !manager.inspectorMode) renderSchema(article, manager, node, schemaContentFields(manager.typeDef(node.type)), [...fieldPath, index], localCleanups);
                    else if (available) article.append(button(t('edit_content'), () => { const entry = manager.structureRows().find(row => raw(row.node) === raw(node)); if (entry) { manager.selectNode(entry.path); manager.inspectorTab = 'content'; } }));
                    else article.append(element('p', manager.unavailableBlockMessage(node), 'admin-field-hint'));
                    list.append(article);
                });
                attachSortable(list, { nodes: target[key], manager, render }, localCleanups).catch(() => {});
            };
            slot.append(list, toolbar); group.append(slot); render(); cleanups.push(() => localCleanups.splice(0).reverse().forEach(cleanup => cleanup()));
        } else if (field.type === 'object') {
            if (!target[key] || typeof target[key] !== 'object' || Array.isArray(target[key])) target[key] = schemaDefaults(field.fields);
            const nested = element('fieldset', undefined, 'admin-panel space-y-3 p-3');
            let objectCleanups = [];
            const renderObject = (item, reset = false) => {
                objectCleanups.splice(0).reverse().forEach(cleanup => cleanup()); nested.replaceChildren();
                const projected = item?.properties ? Object.fromEntries(item.properties.map(property => [property.name, { type: property.type, label: property.label || property.name, default: property.default, inheritable: true }])) : field.fields;
                if (reset) for (const existing of Object.keys(target[key])) if (!Object.hasOwn(projected || {}, existing)) delete target[key][existing];
                renderSchema(nested, manager, target[key], projected || {}, fieldPath, objectCleanups, blockType);
            };
            if (field.fields_source) {
                const update = event => { if (event && (raw(event.detail.target) !== raw(target) || event.detail.source !== field.fields_source)) return; const metadata = event?.detail.item || referenceMetadata.get(raw(target))?.get(field.reference_field); renderObject(metadata, event?.detail.changed === true); };
                host.addEventListener('cms-reference-selected', update); cleanups.push(() => host.removeEventListener('cms-reference-selected', update));
                update();
                if (target[field.reference_field] !== undefined && target[field.reference_field] !== '') { const reference = target[field.reference_field]; manager.searchBlockReferences(blockType, { options_source: field.fields_source }, `id:${reference}`).then(body => { if (nested.isConnected && target[field.reference_field] === reference) renderObject(body.items?.[0]); }).catch(() => {}); }
            } else renderObject();
            group.append(nested); cleanups.push(() => objectCleanups.splice(0).reverse().forEach(cleanup => cleanup()));
        } else if (field.type === 'repeater') {
            if (!Array.isArray(target[key])) target[key] = [];
            const rows = element('div', undefined, 'space-y-3');
            let rowCleanups = [];
            const render = () => {
                rowCleanups.splice(0).reverse().forEach(cleanup => cleanup()); rows.replaceChildren();
                target[key].forEach((item, index) => {
                    const row = element('fieldset', undefined, 'admin-panel space-y-3 p-3'); row.append(element('legend', `${field.label || key} ${index + 1}`, 'admin-field-label'));
                    renderSchema(row, manager, item, field.fields, [...fieldPath, index], rowCleanups, blockType);
                    const toolbar = element('div', undefined, 'flex gap-2');
                    const itemUnavailable = () => childSlots(item, { schema: field.fields }).some(slot => slot.nodes.some(node => containsUnavailable(node, manager.availableTypes)));
                    const move = direction => { if (itemUnavailable()) return; const next = index + direction; if (next < 0 || next >= target[key].length) return; manager.historyBoundary(); target[key].splice(index, 1); target[key].splice(next, 0, item); manager.syncBlocksToLocale(); render(); };
                    toolbar.append(button('↑', () => move(-1), { disabled: index === 0, title: t('move_item_up') }), button('↓', () => move(1), { disabled: index === target[key].length - 1, title: t('move_item_down') }), button(t('delete_item'), () => { if (itemUnavailable()) return; manager.historyBoundary(); target[key].splice(index, 1); manager.syncBlocksToLocale(); render(); }));
                    row.append(toolbar); rows.append(row);
                });
            };
            group.append(rows, button(t('add_item'), () => { manager.historyBoundary(); target[key].push(schemaDefaults(field.fields)); manager.syncBlocksToLocale(); render(); })); render(); cleanups.push(() => rowCleanups.splice(0).reverse().forEach(cleanup => cleanup()));
        } else {
            const changed = () => {
                clearOwnerDesignOverrides(target, field);
                manager.syncBlocksToLocale();
                // Conditional fields follow their owning repeater/object scope.
                for (const child of host.children) if (child._cmsVisibility) child.hidden = !manager.fieldVisible(target, child._cmsVisibility);
            };
            const input = field.format === 'datetime-offset' ? dateTimeInput(target, key, changed) : boundInput(target, key, field, changed);
            if (field.type === 'boolean') {
                label.className = 'admin-check-label';
                label.replaceChildren(input, document.createTextNode(field.label || key));
            } else {
                label.htmlFor = input.id;
                if (field.type === 'media') group.append(...mediaControl(manager, target, key, field, input));
                else group.append(input);
            }
            if (input._cmsOffsetControl) group.append(element('span', t('utc_offset_hint'), 'admin-field-hint'), input._cmsOffsetControl);
            if (field.options_source) {
                const search = element('input', undefined, controlClass); search.type = 'search'; search.placeholder = t('reference_search_placeholder'); search.setAttribute('aria-label', t('reference_search_label', { label: field.label || key }));
                const status = element('p', '', 'admin-field-hint'); status.setAttribute('role', 'status');
                const more = button(t('more_results'), () => load(search.value, offset)); more.hidden = true;
                let offset = 0; let items = []; let delay; let request = 0;
                const valueKey = field.option_value || 'id'; const labelKey = field.option_label || 'title';
                const load = async (query = '', start = 0) => {
                    const sequence = ++request; status.textContent = t('searching');
                    try {
                        const body = await manager.searchBlockReferences(blockType, field, query, start);
                        if (sequence !== request || !input.isConnected) return;
                        items = start ? [...items, ...(body.items || [])] : (body.items || []); offset = body.next_offset;
                        const selected = target[key]; input.replaceChildren();
                        const blank = element('option', t('no_selection')); blank.value = ''; input.append(blank);
                        if (selected !== '' && selected !== undefined && !items.some(item => String(item[valueKey]) === String(selected))) { const saved = element('option', t('saved_reference', { id: selected })); saved.value = selected; input.append(saved); }
                        for (const item of items) { const option = element('option', item[labelKey] ?? item.name ?? item[valueKey]); option.value = item[valueKey]; input.append(option); }
                        input.value = selected ?? ''; more.hidden = offset === null || offset === undefined;
                        status.textContent = items.length ? '' : t('no_references');
                        const selectedItem = items.find(item => String(item[valueKey]) === String(selected));
                        if (selectedItem) { const metadata = referenceMetadata.get(raw(target)) || new Map(); metadata.set(key, selectedItem); referenceMetadata.set(raw(target), metadata); host.dispatchEvent(new CustomEvent('cms-reference-selected', { detail: { target, source: field.options_source, item: selectedItem } })); }
                    } catch (error) { if (sequence === request) status.textContent = error.message; }
                };
                input.addEventListener('change', () => { const item = items.find(value => String(value[valueKey]) === String(target[key])); const metadata = referenceMetadata.get(raw(target)) || new Map(); metadata.set(key, item); referenceMetadata.set(raw(target), metadata); host.dispatchEvent(new CustomEvent('cms-reference-selected', { detail: { target, source: field.options_source, item, changed: true } })); manager.syncBlocksToLocale(); });
                search.addEventListener('input', () => { clearTimeout(delay); delay = setTimeout(() => load(search.value), 250); });
                const refreshContext = () => load(target[key] ? `id:${target[key]}` : search.value);
                window.addEventListener('cms-markets-changed', refreshContext); window.addEventListener('cms-locale-switched', refreshContext);
                cleanups.push(() => { window.removeEventListener('cms-markets-changed', refreshContext); window.removeEventListener('cms-locale-switched', refreshContext); });
                group.prepend(search); group.append(status, more); load(target[key] ? `id:${target[key]}` : ''); cleanups.push(() => { clearTimeout(delay); request++; });
            }

            if (field.inheritable) group.append(button(t('inherit_original'), () => { delete target[key]; input.value = field.default ?? ''; if (field.type === 'boolean') input.checked = !!field.default; manager.syncBlocksToLocale(); }));

        }
        group._cmsVisibility = field;
        if (field.description) { const hint = element('p', field.description, 'admin-field-hint'); hint.id = `${newNodeId()}-hint`; group.append(hint); const input = group.querySelector('input,textarea,select'); if (input) input.setAttribute('aria-describedby', hint.id); }
        host.append(group);
    }
}

export function mountSchemaEditor(host, manager, node, group = 'all') {
    if (!node || !manager.isBlockAvailable(node)) return;
    const identity = raw(node);
    if (mounted.get(host)?.node === identity && mounted.get(host)?.group === group) return;
    unmountSchemaEditor(host);
    const cleanups = [];
    mounted.set(host, { node: identity, group, cleanups }); schemaHosts.add(host); host.replaceChildren();
    const row = treeRows(manager.blocks, manager.availableTypes).find(item => raw(item.node) === identity);
    const definition = manager.typeDef(node.type);
    renderSchema(host, manager, node, group === 'content' ? schemaContentFields(definition) : group === 'design' ? schemaDesignFields(definition) : definition.schema, row?.path || [], cleanups);
}
export function unmountSchemaEditor(host) {
    mounted.get(host)?.cleanups.reverse().forEach(cleanup => cleanup()); mounted.delete(host); schemaHosts.delete(host);
}

export function createEditorFeatures(options = {}) {
    setEditorLabels(options.labels);
    const history = new EditorHistory(options.initial || {});
    const compositionPreview = new DebouncedPreview();
    let restoring = false;
    let structural = false;
    let previewRequest;
    let lifecycle;
    const rootCleanups = [];
    const packageEditors = new Map();
    // Las listas Alpine (raíz y columnas) comparten un único gestor: cada x-for da otro proxy de ámbito.
    let sortableManager;
    // Control que abrió el visor: recibe el foco al cerrarlo.
    let previewOpener;
    return {
        editorOptions: options, selectedPath: '[]', selectedId: null, historyVersion: 0, appearanceState: 'base', showStructure: true, showPreview: false, previewScope: 'selected', previewBusy: false, previewError: '', previewHtml: '', dataExport: '', copyStatus: '',
        initializeEditorLifecycle() {
            if (typeof MutationObserver === 'undefined' || !this.$el) return;
            lifecycle = new MutationObserver(() => {
                for (const host of schemaHosts) if (!host.isConnected) unmountSchemaEditor(host);
                for (const [host, editor] of packageEditors) {
                    if (host.isConnected) continue;
                    editor.cleanup?.();
                    packageEditors.delete(host);
                }
            });
            lifecycle.observe(this.$el, { childList: true, subtree: true });
            const receive = event => {
                const frame = this.previewFrame();
                if (!frame || event.source !== frame.contentWindow || event.origin !== location.origin || event.data?.source !== 'cms-block-preview') return;
                if (event.data.type === 'ready') { this.sendPreviewSelection(); return; }
                if (event.data.type !== 'select') return;
                if (Number.isInteger(event.data.column_index)) {
                    const root = this.previewComposition(); const index = this.blocks.indexOf(root);
                    if (index >= 0 && root.columns?.[event.data.column_index]) this.selectColumn([index, 'columns', event.data.column_index]);
                } else if (typeof event.data.node_id === 'string') {
                    const row = this.structureRows().find(item => String(item.node.id) === event.data.node_id);
                    if (row) this.selectNode(row.path);
                }
            };
            window.addEventListener('message', receive); rootCleanups.push(() => window.removeEventListener('message', receive));
            const contextChanged = () => this.scheduleCompositionPreview();
            window.addEventListener('cms-markets-changed', contextChanged); rootCleanups.push(() => window.removeEventListener('cms-markets-changed', contextChanged));
        },
        destroy() { lifecycle?.disconnect(); for (const editor of packageEditors.values()) editor.cleanup?.(); packageEditors.clear(); previewRequest?.abort(); compositionPreview.reset(); this.destroyInspector(); rootCleanups.splice(0).reverse().forEach(cleanup => cleanup()); for (const host of schemaHosts) if (this.$el?.contains(host)) unmountSchemaEditor(host); },
        refreshEditorControls() {
            if (typeof document === 'undefined') return;
            for (const host of schemaHosts) {
                if (!host.isConnected) { unmountSchemaEditor(host); continue; }
                for (const input of host.querySelectorAll('input,textarea,select')) {
                    if (!input._cmsBinding || input === document.activeElement) continue;
                    const { target, key, field, part } = input._cmsBinding; const value = Object.hasOwn(raw(target), key) ? raw(target)[key] : field.default;
                    if (part) { const parts = /^(.*?)(Z|[+-]\d\d:\d\d)$/.exec(value || ''); input.value = part === 'date' ? (parts?.[1] || '') : (parts?.[2] === 'Z' ? '+00:00' : (parts?.[2] || '+00:00')); }
                    else if (field.type === 'boolean') input.checked = !!value;
                    else if (['json', 'array'].includes(field.type)) input.value = JSON.stringify(value ?? {}, null, 2);
                    else if (field.type === 'list') input.value = (value || []).join('\n');
                    else input.value = value ?? '';
                    input._cmsAfterRefresh?.();
                }
            }
        },
        recordHistory() { if (!restoring && history.record(this.sectionsByLocale, structural ? '' : `typing:${this.activeLocale}`)) this.historyVersion++; structural = false; },
        historyBoundary() { history.boundary(); structural = true; },
        rebaseHistory() { history.rebase(this.sectionsByLocale); this.historyVersion++; },
        canUndo() { this.historyVersion; return history.canUndo; }, canRedo() { this.historyVersion; return history.canRedo; },
        undo() { this.clearInspectorDrafts(); this.recordHistory(); const snapshot = history.undo(); if (snapshot) { restoring = true; this.sectionsByLocale = snapshot; this.blocks = snapshot[this.activeLocale] || []; this.historyVersion++; restoring = false; } this.refreshInspectorControls(true); this.scheduleCompositionPreview(); },
        redo() { this.clearInspectorDrafts(); const snapshot = history.redo(); if (snapshot) { restoring = true; this.sectionsByLocale = snapshot; this.blocks = snapshot[this.activeLocale] || []; this.historyVersion++; restoring = false; } this.refreshInspectorControls(true); this.scheduleCompositionPreview(); },
        structureRows() { return treeRows(this.blocks, this.availableTypes); },
        selectedNode() { if (this.selectedId !== null) return this.structureRows().find(row => String(row.node.id) === String(this.selectedId))?.node || null; try { const path = JSON.parse(this.selectedPath); return path.length ? getAtPath(this.blocks, path) : null; } catch { return null; } },
        selectNode(path) { this.inspectorColumnId = null; this.selectedPath = JSON.stringify(path); this.selectedId = getAtPath(this.blocks, path)?.id ?? null; this.expandPath?.(path); this.dataExport = ''; this.copyStatus = ''; this.syncDesignCard?.(); this.sendPreviewSelection(); this.scheduleCompositionPreview(); },
        async mountPackageBlockEditor(host, node) {
            if (packageEditors.has(host)) return;
            const load = blockEditorLoaders[host.dataset.blockEditor];
            if (!load) return;
            const editor = {};
            packageEditors.set(host, editor);
            const manager = this;
            const row = () => manager.structureRows().find(item => String(item.node.id) === String(node.id));
            try {
                const module = await load();
                if (!host.isConnected || !packageEditors.has(host)) return;
                if (typeof module.default !== 'function') throw new Error(t('package_editor_unavailable'));
                editor.cleanup = module.default(host, {
                    read: () => row()?.node || node,
                    locale: () => manager.activeLocale,
                    watch(callback) {
                        let current = raw(node);
                        const effect = globalThis.Alpine.effect(() => {
                            const updated = raw(row()?.node);
                            if (updated && current !== updated) {
                                current = updated;
                                queueMicrotask(callback);
                            }
                        });
                        return () => globalThis.Alpine.release(effect);
                    },
                    notify: () => manager.syncBlocksToLocale(),
                    boundary: () => manager.historyBoundary(),
                    clone: child => cloneBlockTree(child, manager.availableTypes),
                    containsUnavailable: child => containsUnavailable(child, manager.availableTypes),
                    mountField: (targetHost, target, key, field) => {
                        manager.mountSchemaField(targetHost, target, key, field);
                        return () => unmountSchemaEditor(targetHost);
                    },
                    media: onSelect => manager.beginMediaRequest({ mode: 'inspector', kind: 'image', onSelect }),
                    async preview(signal) {
                        const selected = row();
                        const section = selected && manager.blocks[selected.path[0]];
                        if (!section || !manager.editorOptions.previewUrl) return '';
                        return requestPreview(manager.editorOptions.previewUrl, {
                            entity_type: manager.editorOptions.entityType,
                            entity_id: manager.editorOptions.entityId,
                            locale: manager.activeLocale,
                            market: manager.editorMarket(),
                            selected_node_id: selected.node.id,
                            type: section.type,
                            payload: cleanEditorValue(section),
                        }, signal);
                    },
                });
            } catch (error) {
                const status = host.querySelector('[data-package-editor-status]');
                if (status) status.textContent = error.message;
            }
        },
        mountSchemaEditor(host, node, group = 'all') { mountSchemaEditor(host, this, node, group); },
        mountSchemaField(host, target, key, field) { if (mounted.has(host)) return; const cleanups = []; mounted.set(host, { node: raw(target), cleanups }); schemaHosts.add(host); renderSchema(host, this, target, { [key]: field }, [], cleanups); },
        async searchBlockReferences(type, field, search = '', offset = 0) {
            if (!this.editorOptions.referenceUrl) throw new Error(t('reference_search_unavailable'));
            const url = new URL(this.editorOptions.referenceUrl, location.origin);
            const context = { entity_type: this.editorOptions.entityType, entity_id: this.editorOptions.entityId, locale: this.activeLocale, market: this.editorMarket(), block_type: type, source: field.options_source, search, limit: 20, offset };
            for (const [key, value] of Object.entries(context)) if (value !== null && value !== undefined && value !== '') url.searchParams.set(key, value);
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); const body = await response.json();
            if (!response.ok) throw new Error(body.message || t('references_failed')); return body;
        },
        editorMarket() { const selectedId = globalThis.cmsContext?.marketIds?.[0]; return this.editorOptions.markets?.[selectedId] ?? this.editorOptions.market ?? null; },
        bindingFields() { const node = this.selectedNode(); return node ? this.schemaFields(node).filter(field => ['text', 'textarea', 'url', 'media', 'number', 'integer', 'string'].includes(field.type)) : []; },
        bindingItemSource() { const selected = this.structureRows().find(row => row.node === this.selectedNode()); return this.structureRows().filter(row => row.path.length < (selected?.path.length || 0) && row.path.every((part, index) => selected.path[index] === part) && row.node.type === 'collection').at(-1)?.node.source; },
        bindingSourceOptions() { return { '': t('binding_manual'), ...(this.bindingItemSource() ? { item: t('binding_item') } : {}), ...Object.fromEntries(Object.entries(this.editorOptions.sources || {}).map(([key, source]) => [key, source.name || key])) }; },
        bindingFor(key) { return this.selectedNode()?.bindings?.[key] || {}; },
        bindingSourceFields(key) { const binding = this.bindingFor(key); let source = binding.source; if (source === 'item') source = this.bindingItemSource(); return this.editorOptions.sources?.[source]?.fields || {}; },
        setBindingSource(key, source) { const node = this.selectedNode(); if (!node || !this.isBlockAvailable(node)) return; node.bindings ??= {}; if (!source) { delete node.bindings[key]; if (!Object.keys(node.bindings).length) delete node.bindings; } else node.bindings[key] = { source, field: '' }; this.syncBlocksToLocale(); },
        setBindingValue(key, name, value) { const binding = this.selectedNode()?.bindings?.[key]; if (!binding) return; if (name === 'id') { if (value === '') delete binding.id; else binding.id = Number(value); } else binding[name] = value; this.syncBlocksToLocale(); },
        selectedAppearance(create = false) { const node = this.selectedNode(); if (!node || !this.isBlockAvailable(node)) return null; if (create) { node.appearance ??= {}; node.appearance[this.appearanceState] ??= {}; } return node.appearance?.[this.appearanceState] || null; },
        appearanceFields() { return Object.entries(this.editorOptions.appearanceProperties || {}).map(([key, field]) => ({ key, ...(typeof field === 'object' ? field : { type: field, label: key }) })); },
        appearanceValue(key) { return this.selectedAppearance()?.[key] ?? ''; },
        setAppearanceValue(key, value) { const appearance = this.selectedAppearance(true); if (!appearance) return; if (value === '') delete appearance[key]; else appearance[key] = value; this.syncBlocksToLocale(); },
        appearanceClasses() { return (this.selectedNode()?.appearance?.classes || []).join(' '); },
        setAppearanceClasses(value) { const node = this.selectedNode(); if (!node || !this.isBlockAvailable(node)) return; node.appearance ??= {}; node.appearance.classes = value.split(/\s+/).filter(Boolean); this.syncBlocksToLocale(); },
        appearanceHtmlClass() { return (this.selectedNode()?.appearance?.html_class || []).join(' '); },
        setAppearanceHtmlClass(value) {
            const node = this.selectedNode();
            if (!node || !this.isBlockAvailable(node)) return;
            node.appearance ??= {};
            const tokens = String(value || '').trim().split(/\s+/).filter(Boolean);
            if (tokens.length) node.appearance.html_class = tokens;
            else delete node.appearance.html_class;
            this.syncBlocksToLocale();
        },
        htmlClassHint() {
            const options = this.editorOptions.htmlClasses || {};
            const names = Array.isArray(options) ? options : Object.keys(options);
            const max = this.editorOptions.htmlClassMax || 16;
            if (names.length) {
                return t('html_class_hint_allowlist', { max, names: names.map((name) => (options[name] && options[name] !== name ? name + ' · ' + options[name] : name)).join(', ') });
            }
            return t('html_class_hint_open', { max });
        },
        designPresetsHint() {
            const options = this.editorOptions.appearanceClasses || [];
            const names = Array.isArray(options) ? options : Object.keys(options);
            return t('design_presets_hint', { names: names.join(', ') });
        },
        closePreview() { this.inspectorMobilePane = 'edit'; this.showPreview = false; this.previewScope = 'selected'; compositionPreview.reset(); this.previewBusy = false; const opener = previewOpener; previewOpener = null; if (opener?.isConnected) opener.focus?.(); },
        // Toolbar: every root section. Card eye: only the selected section.
        openPreview() { return this.runCompositionPreview('all'); },
        previewSelected() { return this.runCompositionPreview('selected'); },
        compositionPreviewInput(scope = this.previewScope) {
            if (!this.editorOptions.previewUrl) return null;
            const column = this.inspectorColumn();
            const selected = column ? null : String(this.selectedNode()?.id || '');
            const selectedColumn = column?.index ?? null;
            const context = { entity_type: this.editorOptions.entityType, entity_id: this.editorOptions.entityId, locale: this.activeLocale, market: this.editorMarket(), selected_node_id: selected, selected_column_index: selectedColumn };
            if (scope === 'all') {
                if (!this.blocks.length) return null;
                return { ...context, sections: this.blocks.map(block => ({ type: block.type || 'text', payload: cleanEditorValue(block) })) };
            }
            const node = this.previewComposition();
            if (!node) return null;
            return { ...context, type: node.type || 'rich_text', payload: cleanEditorValue(node) };
        },
        scheduleCompositionPreview() {
            if (typeof document === 'undefined') return;
            if (!this.showPreview) return;
            if (this.inspectorHasInvalid()) { compositionPreview.reset(); this.previewBusy = false; return; }
            const input = this.compositionPreviewInput();
            if (!input) { compositionPreview.reset(); return; }
            const { selected_node_id, selected_column_index, ...content } = input;
            compositionPreview.schedule(JSON.stringify(content), (controller, current) => this.fetchCompositionPreview(this.compositionPreviewInput(), controller, current));
        },
        async fetchCompositionPreview(input, controller, current) {
            if (!input || !current()) return;
            this.previewBusy = true; this.previewError = '';
            try {
                const html = await requestPreview(this.editorOptions.previewUrl, input, controller.signal);
                if (current() && this.showPreview) this.previewHtml = html;
            } catch (error) { if (current() && error.name !== 'AbortError') this.previewError = error.message; }
            finally { if (current()) this.previewBusy = false; }
        },
        async runCompositionPreview(scope = 'selected') {
            if (!this.showPreview && typeof document !== 'undefined') previewOpener = document.activeElement;
            this.previewScope = scope === 'all' ? 'all' : 'selected';
            this.showDesignDialog = false;
            this.showMotionDialog = false;
            this.showPreview = true; this.inspectorMobilePane = 'preview';
            if (this.inspectorHasInvalid()) return;
            if (this.previewScope === 'selected' && !this.previewComposition() && this.blocks.length) this.selectNode([0]);
            const input = this.compositionPreviewInput(); if (!input) return;
            const { selected_node_id, selected_column_index, ...content } = input;
            await compositionPreview.runNow(JSON.stringify(content), (controller, current) => this.fetchCompositionPreview(input, controller, current));
        },
        async copySelected(format = 'json') {
            const node = this.selectedNode(); if (!node) return;
            const json = JSON.stringify(cleanEditorValue(node), null, 2);
            const php = value => { if (value === null) return 'null'; if (typeof value === 'boolean') return value ? 'true' : 'false'; if (typeof value === 'number') return String(value); if (typeof value === 'string') return "'" + value.replaceAll('\\', '\\\\').replaceAll("'", "\\'") + "'"; if (Array.isArray(value)) return '[' + value.map(php).join(', ') + ']'; return '[' + Object.entries(value).map(([key, child]) => php(key) + ' => ' + php(child)).join(', ') + ']'; };
            this.dataExport = format === 'blade' ? `@php\n    $cmsBlockPayload = ${php(cleanEditorValue(node)).replaceAll('@', "' . chr(64) . '")};\n@endphp\n<x-cms::block type="${String(node.type).replaceAll('"', '&quot;')}" :payload="$cmsBlockPayload" />` : json;
            try { await navigator.clipboard.writeText(this.dataExport); this.copyStatus = t('copied'); } catch { this.copyStatus = t('copy_manually'); }
        },
        mountRootSortable(host) {
            if (sortableLists.has(host)) return;
            const manager = sortableManager ??= this;
            attachSortable(host, { get nodes() { return manager.blocks; }, manager, render: () => {}, root: true }, rootCleanups).catch(() => {});
        },
        // Sub-bloques de una columna (lista Alpine): la columna se resuelve por id en cada acceso.
        mountColumnSortable(host, blockIndex, colIndex) {
            if (sortableLists.has(host)) return;
            const manager = sortableManager ??= this; const columnId = manager.blocks[blockIndex]?.columns?.[colIndex]?.id;
            const column = () => (columnId !== undefined && manager.structureRows().flatMap(row => row.node.columns || []).find(item => String(item?.id) === String(columnId))) || manager.blocks[blockIndex]?.columns?.[colIndex];
            const cleanups = []; rootCleanups.push(() => cleanups.splice(0).forEach(cleanup => cleanup()));
            attachSortable(host, { get nodes() { const col = column(); if (col && !Array.isArray(col.blocks)) col.blocks = []; return col?.blocks || []; }, manager, render: () => {}, alpine: true }, cleanups).catch(() => {});
        },
        ...createInspectorFeatures(),
        editorKeydown(event) {
            if (!(event.ctrlKey || event.metaKey) || event.altKey) return;
            if (event.target?.isContentEditable) return;
            const key = event.key.toLowerCase();
            if (key === 'z') { event.preventDefault(); event.shiftKey ? this.redo() : this.undo(); }
            if (key === 'y') { event.preventDefault(); this.redo(); }
        },
    };
}
