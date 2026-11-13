import { copy, newNodeId, schemaDefaults, cleanEditorValue, getAtPath, treeRows, childSlots, cloneBlockTree, containsUnavailable, EditorHistory } from './block-editor-tree.js';
import { createInspectorFeatures, schemaContentFields, schemaDesignFields, clearOwnerDesignOverrides, DebouncedPreview, canMoveNodeInto } from './block-inspector.js';

const mounted = new WeakMap();
const schemaHosts = new Set();
const richEditors = new WeakMap();
const richHosts = new Set();
const sortableLists = new WeakMap();
const referenceMetadata = new WeakMap();
const controlClass = 'admin-control w-full rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] px-3 py-2 text-sm';
const buttonClass = 'rounded border border-[color:var(--admin-border)] bg-[color:var(--admin-surface-raised)] px-2 py-1 text-xs text-[color:var(--admin-text)] disabled:opacity-40';
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
function dateTimeInput(target, key, changed) {
    const input = element('input', undefined, controlClass); input.type = 'datetime-local'; input.step = '1'; input.id = `cms-field-${newNodeId()}`;
    const timezone = element('select', undefined, controlClass); timezone.setAttribute('aria-label', 'Desplazamiento UTC de la fecha');
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
    const input = element(field.type === 'textarea' || ['json', 'array', 'list'].includes(field.type) ? 'textarea' : field.type === 'select' ? 'select' : 'input', undefined, controlClass);
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
    if (field.type === 'boolean') { input.checked = !!value; input.className = 'rounded border border-[color:var(--admin-border)]'; }
    else if (['json', 'array'].includes(field.type)) input.value = JSON.stringify(value ?? {}, null, 2);
    else if (field.type === 'list') input.value = (value || []).join('\n');
    else input.value = value ?? '';
    input.addEventListener(field.type === 'boolean' || field.type === 'select' || ['json', 'array', 'list'].includes(field.type) ? 'change' : 'input', () => {
        let next = input.value;
        if (field.type === 'boolean') next = input.checked;
        if (['number', 'integer'].includes(field.type)) next = input.value === '' ? null : Number(input.value);
        if (['json', 'array'].includes(field.type)) {
            try { next = JSON.parse(input.value); if (!next || typeof next !== 'object') throw new Error(); input.setCustomValidity(''); }
            catch { input.setCustomValidity('Introduce una lista u objeto JSON válido.'); input.reportValidity(); return; }
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
    const sortable = Sortable.create(list, {
        group: 'cms-composable-nodes', animation: globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 0 : 150,
        handle: '[data-node-drag]', draggable: '[data-editor-node]', filter: '[data-node-unavailable]',
        onMove(event) { return !event.dragged.contains(event.to) && !event.dragged.hasAttribute('data-node-unavailable') && moveContext(event).allowed; },
        onAdd(event) { const context = moveContext(event); if (!context.allowed) rejectMove(event, context); },
        onEnd(event) {
            const context = moveContext(event); const { from, to, node } = context;
            if (!from || !to) return;
            const oldIndex = context.index; const newIndex = event.newDraggableIndex;
            const pointer = event.originalEvent?.changedTouches?.[0] || event.originalEvent;
            const hit = pointer && Number.isFinite(pointer.clientX) ? document.elementFromPoint(pointer.clientX, pointer.clientY) : null;
            const intended = sortableLists.get(hit?.closest('[data-editor-slot-list], [data-editor-root-list]'));
            const intendedAllowed = !intended || (raw(intended.manager) === raw(from.manager) && canMoveNodeInto(node, intended.nodes, from.manager.availableTypes));
            if (!context.allowed || !intendedAllowed || !Number.isInteger(newIndex)) { rejectMove(event, context); return; }
            if (from.root || to.root) {
                event.item.remove();
                const before = event.from.querySelectorAll(':scope > [data-editor-node]')[oldIndex];
                event.from.insertBefore(event.item, before || null);
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
        const group = element('div', undefined, 'admin-field space-y-2');
        const label = element('label', field.label || key, 'admin-field-label'); group.append(label);
        const fieldPath = [...path, key];
        if (!manager.fieldVisible(target, field)) { group.hidden = true; }
        if (field.type === 'blocks') {
            if (!Array.isArray(target[key])) target[key] = [];
            const slot = element('div', undefined, 'space-y-3 rounded-lg border border-[color:var(--admin-border)] p-3');
            const list = element('div', undefined, 'space-y-3 min-h-12'); list.dataset.editorSlotList = 'true';
            const toolbar = element('div', undefined, 'flex flex-wrap gap-2');
            const select = element('select', undefined, controlClass); select.setAttribute('aria-label', `Tipo de bloque: ${field.label || key}`);
            for (const definition of manager.availableTypes) { const option = element('option', definition.name); option.value = definition.type; select.append(option); }
            toolbar.append(select, button('Añadir bloque', () => {
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
                    const article = element('article', undefined, 'rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] p-3 space-y-3'); article.dataset.editorNode = node.id || String(index);
                    if (!available || containsUnavailable(node, manager.availableTypes)) article.dataset.nodeUnavailable = 'true';
                    const tools = element('div', undefined, 'flex flex-wrap items-center gap-2');
                    const handle = button('↕', () => {}, { disabled: !available, title: 'Arrastrar bloque; usa las flechas para moverlo con teclado' }); handle.dataset.nodeDrag = 'true';
                    const move = direction => { if (!available || containsUnavailable(node, manager.availableTypes)) return; const next = index + direction; if (next < 0 || next >= target[key].length) return; manager.historyBoundary(); target[key].splice(index, 1); target[key].splice(next, 0, node); manager.syncBlocksToLocale(); render(); };
                    handle.addEventListener('keydown', event => { if (event.altKey && ['ArrowUp', 'ArrowDown'].includes(event.key)) { event.preventDefault(); move(event.key === 'ArrowUp' ? -1 : 1); } });
                    const title = manager.typeDef(node.type).name || node.type;
                    tools.append(handle, button(title, () => { const entry = manager.structureRows().find(row => raw(row.node) === raw(node)); if (entry) manager.selectNode(entry.path); }), button('↑', () => move(-1), { disabled: index === 0 || !available, title: 'Subir bloque' }), button('↓', () => move(1), { disabled: index === target[key].length - 1 || !available, title: 'Bajar bloque' }), button('Duplicar', () => { if (containsUnavailable(node, manager.availableTypes)) return; manager.historyBoundary(); target[key].splice(index + 1, 0, cloneBlockTree(node, manager.availableTypes)); manager.syncBlocksToLocale(); render(); }, { disabled: !available || containsUnavailable(node, manager.availableTypes) }), button('Eliminar', () => { if (containsUnavailable(node, manager.availableTypes)) return; manager.historyBoundary(); target[key].splice(index, 1); manager.syncBlocksToLocale(); render(); }, { disabled: !available || containsUnavailable(node, manager.availableTypes) }));
                    article.append(tools);
                    if (available && !manager.inspectorMode) renderSchema(article, manager, node, manager.typeDef(node.type).schema, [...fieldPath, index], localCleanups);
                    else if (available) article.append(button('Editar contenido', () => { const entry = manager.structureRows().find(row => raw(row.node) === raw(node)); if (entry) { manager.selectNode(entry.path); manager.inspectorTab = 'content'; } }));
                    else article.append(element('p', manager.unavailableBlockMessage(node), 'admin-field-hint'));
                    list.append(article);
                });
                attachSortable(list, { nodes: target[key], manager, render }, localCleanups).catch(() => {});
            };
            slot.append(list, toolbar); group.append(slot); render(); cleanups.push(() => localCleanups.splice(0).reverse().forEach(cleanup => cleanup()));
        } else if (field.type === 'object') {
            if (!target[key] || typeof target[key] !== 'object' || Array.isArray(target[key])) target[key] = schemaDefaults(field.fields);
            const nested = element('fieldset', undefined, 'space-y-3 rounded-lg border border-[color:var(--admin-border)] p-3');
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
                    const row = element('fieldset', undefined, 'rounded-lg border border-[color:var(--admin-border)] p-3 space-y-3'); row.append(element('legend', `${field.label || key} ${index + 1}`, 'admin-field-label'));
                    renderSchema(row, manager, item, field.fields, [...fieldPath, index], rowCleanups, blockType);
                    const toolbar = element('div', undefined, 'flex gap-2');
                    const itemUnavailable = () => childSlots(item, { schema: field.fields }).some(slot => slot.nodes.some(node => containsUnavailable(node, manager.availableTypes)));
                    const move = direction => { if (itemUnavailable()) return; const next = index + direction; if (next < 0 || next >= target[key].length) return; manager.historyBoundary(); target[key].splice(index, 1); target[key].splice(next, 0, item); manager.syncBlocksToLocale(); render(); };
                    toolbar.append(button('↑', () => move(-1), { disabled: index === 0, title: 'Subir elemento' }), button('↓', () => move(1), { disabled: index === target[key].length - 1, title: 'Bajar elemento' }), button('Eliminar elemento', () => { if (itemUnavailable()) return; manager.historyBoundary(); target[key].splice(index, 1); manager.syncBlocksToLocale(); render(); }));
                    row.append(toolbar); rows.append(row);
                });
            };
            group.append(rows, button('Añadir elemento', () => { manager.historyBoundary(); target[key].push(schemaDefaults(field.fields)); manager.syncBlocksToLocale(); render(); })); render(); cleanups.push(() => rowCleanups.splice(0).reverse().forEach(cleanup => cleanup()));
        } else {
            const changed = () => {
                clearOwnerDesignOverrides(target, field);
                manager.syncBlocksToLocale();
                // Conditional fields follow their owning repeater/object scope.
                for (const child of host.children) if (child._cmsVisibility) child.hidden = !manager.fieldVisible(target, child._cmsVisibility);
            };
            const input = field.format === 'datetime-offset' ? dateTimeInput(target, key, changed) : boundInput(target, key, field, changed);
            label.htmlFor = input.id; group.append(input);
            if (input._cmsOffsetControl) group.append(element('span', 'Zona horaria: desplazamiento UTC explícito', 'admin-field-hint'), input._cmsOffsetControl);
            if (field.options_source) {
                const search = element('input', undefined, controlClass); search.type = 'search'; search.placeholder = 'Buscar referencias del idioma y mercado actuales'; search.setAttribute('aria-label', `Buscar: ${field.label || key}`);
                const status = element('p', '', 'admin-field-hint'); status.setAttribute('role', 'status');
                const more = button('Más resultados', () => load(search.value, offset)); more.hidden = true;
                let offset = 0; let items = []; let delay; let request = 0;
                const valueKey = field.option_value || 'id'; const labelKey = field.option_label || 'title';
                const load = async (query = '', start = 0) => {
                    const sequence = ++request; status.textContent = 'Buscando…';
                    try {
                        const body = await manager.searchBlockReferences(blockType, field, query, start);
                        if (sequence !== request || !input.isConnected) return;
                        items = start ? [...items, ...(body.items || [])] : (body.items || []); offset = body.next_offset;
                        const selected = target[key]; input.replaceChildren();
                        const blank = element('option', 'Sin selección'); blank.value = ''; input.append(blank);
                        if (selected !== '' && selected !== undefined && !items.some(item => String(item[valueKey]) === String(selected))) { const saved = element('option', `Referencia guardada (#${selected})`); saved.value = selected; input.append(saved); }
                        for (const item of items) { const option = element('option', item[labelKey] ?? item.name ?? item[valueKey]); option.value = item[valueKey]; input.append(option); }
                        input.value = selected ?? ''; more.hidden = offset === null || offset === undefined;
                        status.textContent = items.length ? '' : 'No hay referencias disponibles en este contexto.';
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

            if (field.inheritable) group.append(button('Heredar valor original', () => { delete target[key]; input.value = field.default ?? ''; if (field.type === 'boolean') input.checked = !!field.default; manager.syncBlocksToLocale(); }));
            if (field.type === 'media') group.append(button('Biblioteca multimedia', () => manager.openBlockMedia(target, key, field)), button('Quitar medio', () => { manager.clearBlockMedia(target, key); input.value = ''; }));
            if (field.type === 'textarea' && key === 'content' && target.type === 'text') {
                const visual = element('div', undefined, 'admin-rich-preview rounded-lg border border-[color:var(--admin-border)] p-3');
                group.append(button('Editor visual HTML', () => { target.format = 'html'; manager.syncBlocksToLocale(); mountRichText(visual, manager, target); }), visual);
                cleanups.push(() => unmountRichText(visual));
            }
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

function richTextMarkup(html) {
    const template = document.createElement('template'); template.innerHTML = html;
    const signature = node => node.nodeType === 1
        ? [node.localName, [...node.attributes].map(attribute => [attribute.name, attribute.value]).sort(([a], [b]) => a.localeCompare(b)), [...node.childNodes].map(signature)]
        : [node.nodeType, node.nodeValue];
    return JSON.stringify([...template.content.childNodes].map(signature));
}

export async function mountRichText(host, manager, node) {
    const source = String(node.content || '');
    const current = richEditors.get(host);
    if (node.format === 'html' && current?.node === raw(node) && current.source === source) return;
    unmountRichText(host); host.replaceChildren();
    // Mounting or checking a representation never changes the stored source.
    // Markdown remains exclusively in its source editor until explicitly chosen.
    if (node.format !== 'html') return;
    richHosts.add(host);
    const ticket = { node: raw(node), source }; richEditors.set(host, ticket);
    const [{ Editor, createDocument, getSchema, generateHTML }, { default: StarterKit }] = await Promise.all([import('@tiptap/core'), import('@tiptap/starter-kit')]);
    if (!host.isConnected || richEditors.get(host) !== ticket) return;
    const extensions = [StarterKit.configure({ link: { openOnClick: false, HTMLAttributes: { target: null, rel: null, class: null } } })];
    const parseOptions = { preserveWhitespace: 'full' };
    let content;
    try {
        content = createDocument(manager.safeContentPreview(source), getSchema(extensions), parseOptions, { errorOnInvalidContent: true }).toJSON();
        const serialized = generateHTML(content, extensions);
        if (source.trim() !== '' && richTextMarkup(source) !== richTextMarkup(serialized)) throw new Error('Unrepresentable HTML');
    } catch {
        const message = element('p', 'Este HTML contiene estructuras o atributos que el editor visual no puede conservar. Edita el código fuente; el contenido original permanece intacto.', 'admin-field-hint');
        message.setAttribute('role', 'status'); message.dataset.richSourceFallback = 'true'; host.replaceChildren(message); return;
    }
    const toolbar = element('div', undefined, 'flex flex-wrap gap-2 mb-3'); const canvas = element('div', undefined, 'min-h-32 prose admin-rich-preview max-w-none');
    host.replaceChildren(toolbar, canvas);
    const editor = new Editor({ element: canvas, extensions, content, parseOptions, editorProps: { attributes: { role: 'textbox', 'aria-multiline': 'true', 'aria-label': 'Contenido HTML', class: 'outline-none min-h-32' } }, onUpdate({ editor: instance }) {
        // A source edit may precede Alpine's next effect. A stale visual model
        // must never overwrite newer HTML, even while it still has focus.
        if (richEditors.get(host) !== ticket || node.format !== 'html' || String(node.content || '') !== ticket.source) return;
        ticket.source = instance.getHTML(); node.content = ticket.source; manager.syncBlocksToLocale();
    } });
    ticket.editor = editor;
    toolbar.append(button('Negrita', () => editor.chain().focus().toggleBold().run()), button('Cursiva', () => editor.chain().focus().toggleItalic().run()), button('Título', () => editor.chain().focus().toggleHeading({ level: 2 }).run()), button('Lista', () => editor.chain().focus().toggleBulletList().run()), button('Cita', () => editor.chain().focus().toggleBlockquote().run()), button('Deshacer', () => editor.chain().focus().undo().run()), button('Rehacer', () => editor.chain().focus().redo().run()));
}
export function unmountRichText(host) { richEditors.get(host)?.editor?.destroy(); richEditors.delete(host); richHosts.delete(host); }

export function createEditorFeatures(options = {}) {
    const history = new EditorHistory(options.initial || {});
    const compositionPreview = new DebouncedPreview();
    let restoring = false;
    let structural = false;
    let previewRequest;
    let lifecycle;
    const rootCleanups = [];
    return {
        editorOptions: options, selectedPath: '[]', selectedId: null, historyVersion: 0, appearanceState: 'base', showStructure: true, showPreview: true, previewBusy: false, previewError: '', previewHtml: '', dataExport: '', copyStatus: '',
        initializeEditorLifecycle() {
            if (typeof MutationObserver === 'undefined' || !this.$el) return;
            lifecycle = new MutationObserver(() => {
                for (const host of schemaHosts) if (!host.isConnected) unmountSchemaEditor(host);
                for (const host of richHosts) if (!host.isConnected) unmountRichText(host);
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
        destroy() { lifecycle?.disconnect(); previewRequest?.abort(); compositionPreview.reset(); this.destroyInspector(); rootCleanups.splice(0).reverse().forEach(cleanup => cleanup()); for (const host of schemaHosts) if (this.$el?.contains(host)) unmountSchemaEditor(host); for (const host of richHosts) if (this.$el?.contains(host) || !host.isConnected) unmountRichText(host); },
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
                }
            }
        },
        recordHistory() { if (!restoring && history.record(this.sectionsByLocale, structural ? '' : `typing:${this.activeLocale}`)) this.historyVersion++; structural = false; },
        historyBoundary() { history.boundary(); structural = true; },
        canUndo() { this.historyVersion; return history.canUndo; }, canRedo() { this.historyVersion; return history.canRedo; },
        undo() { this.clearInspectorDrafts(); this.recordHistory(); const snapshot = history.undo(); if (snapshot) { restoring = true; this.sectionsByLocale = snapshot; this.blocks = snapshot[this.activeLocale] || []; this.historyVersion++; restoring = false; } this.refreshInspectorControls(true); this.scheduleCompositionPreview(); },
        redo() { this.clearInspectorDrafts(); const snapshot = history.redo(); if (snapshot) { restoring = true; this.sectionsByLocale = snapshot; this.blocks = snapshot[this.activeLocale] || []; this.historyVersion++; restoring = false; } this.refreshInspectorControls(true); this.scheduleCompositionPreview(); },
        structureRows() { return treeRows(this.blocks, this.availableTypes); },
        selectedNode() { if (this.selectedId !== null) return this.structureRows().find(row => String(row.node.id) === String(this.selectedId))?.node || null; try { const path = JSON.parse(this.selectedPath); return path.length ? getAtPath(this.blocks, path) : null; } catch { return null; } },
        selectNode(path) { this.inspectorColumnId = null; this.selectedPath = JSON.stringify(path); this.selectedId = getAtPath(this.blocks, path)?.id ?? null; const node = this.selectedNode(); if (node) node.is_collapsed = false; this.dataExport = ''; this.copyStatus = ''; this.sendPreviewSelection(); this.scheduleCompositionPreview(); },
        mountSchemaEditor(host, node, group = 'all') { mountSchemaEditor(host, this, node, group); },
        mountInspectorColumn(host) { const selected = this.inspectorColumn(); if (!selected || mounted.get(host)?.node === raw(selected.column)) return; unmountSchemaEditor(host); const cleanups = []; mounted.set(host, { node: raw(selected.column), cleanups }); schemaHosts.add(host); host.replaceChildren(); renderSchema(host, this, selected.column, { blocks: { type: 'blocks', label: 'Bloques de la columna' } }, selected.path, cleanups, selected.owner.type); },
        mountSchemaField(host, target, key, field) { if (mounted.has(host)) return; const cleanups = []; mounted.set(host, { node: raw(target), cleanups }); schemaHosts.add(host); renderSchema(host, this, target, { [key]: field }, [], cleanups); },
        mountRichText(host, node) { mountRichText(host, this, node).catch(() => { host.textContent = 'El editor visual no está disponible. Puedes seguir usando el código HTML.'; }); },
        unmountRichText(host) { unmountRichText(host); },
        async searchBlockReferences(type, field, search = '', offset = 0) {
            if (!this.editorOptions.referenceUrl) throw new Error('La búsqueda contextual no está disponible.');
            const url = new URL(this.editorOptions.referenceUrl, location.origin);
            const context = { entity_type: this.editorOptions.entityType, entity_id: this.editorOptions.entityId, locale: this.activeLocale, market: this.editorMarket(), block_type: type, source: field.options_source, search, limit: 20, offset };
            for (const [key, value] of Object.entries(context)) if (value !== null && value !== undefined && value !== '') url.searchParams.set(key, value);
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); const body = await response.json();
            if (!response.ok) throw new Error(body.message || 'No se pudieron cargar las referencias.'); return body;
        },
        editorMarket() { const selectedId = globalThis.cmsContext?.marketIds?.[0]; return this.editorOptions.markets?.[selectedId] ?? this.editorOptions.market ?? null; },
        bindingFields() { const node = this.selectedNode(); return node ? this.schemaFields(node).filter(field => ['text', 'textarea', 'url', 'media', 'number', 'integer', 'string'].includes(field.type)) : []; },
        bindingItemSource() { const selected = this.structureRows().find(row => row.node === this.selectedNode()); return this.structureRows().filter(row => row.path.length < (selected?.path.length || 0) && row.path.every((part, index) => selected.path[index] === part) && row.node.type === 'collection').at(-1)?.node.source; },
        bindingSourceOptions() { return { '': 'Contenido manual', ...(this.bindingItemSource() ? { item: 'Elemento de la colección contenedora' } : {}), ...Object.fromEntries(Object.entries(this.editorOptions.sources || {}).map(([key, source]) => [key, source.name || key])) }; },
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
        closePreview() { this.inspectorMobilePane = 'edit'; this.showPreview = false; },
        compositionPreviewInput() {
            const node = this.previewComposition();
            if (!node || !this.editorOptions.previewUrl) return null;
            const column = this.inspectorColumn();
            return { type: node.type || 'text', payload: cleanEditorValue(node), entity_type: this.editorOptions.entityType, entity_id: this.editorOptions.entityId, locale: this.activeLocale, market: this.editorMarket(), selected_node_id: column ? null : String(this.selectedNode()?.id || ''), selected_column_index: column?.index ?? null };
        },
        scheduleCompositionPreview() {
            if (typeof document === 'undefined') return;
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
                const response = await fetch(this.editorOptions.previewUrl, { method: 'POST', credentials: 'same-origin', signal: controller.signal, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: JSON.stringify(input) });
                const body = await response.json(); if (!response.ok) throw new Error(body.message || 'No se pudo generar la vista previa.');
                if (current()) { this.previewHtml = previewNonce(body.document || body.html || '', document.querySelector('script[nonce]')?.nonce); this.showPreview = true; }
            } catch (error) { if (current() && error.name !== 'AbortError') this.previewError = error.message; }
            finally { if (current()) this.previewBusy = false; }
        },
        async previewSelected() {
            this.showPreview = true; this.inspectorMobilePane = 'preview';
            if (this.inspectorHasInvalid()) return;
            const input = this.compositionPreviewInput(); if (!input) return;
            const { selected_node_id, selected_column_index, ...content } = input;
            await compositionPreview.runNow(JSON.stringify(content), (controller, current) => this.fetchCompositionPreview(input, controller, current));
        },
        async copySelected(format = 'json') {
            const node = this.selectedNode(); if (!node) return;
            const json = JSON.stringify(cleanEditorValue(node), null, 2);
            const php = value => { if (value === null) return 'null'; if (typeof value === 'boolean') return value ? 'true' : 'false'; if (typeof value === 'number') return String(value); if (typeof value === 'string') return "'" + value.replaceAll('\\', '\\\\').replaceAll("'", "\\'") + "'"; if (Array.isArray(value)) return '[' + value.map(php).join(', ') + ']'; return '[' + Object.entries(value).map(([key, child]) => php(key) + ' => ' + php(child)).join(', ') + ']'; };
            this.dataExport = format === 'blade' ? `@php\n    $cmsBlockPayload = ${php(cleanEditorValue(node)).replaceAll('@', "' . chr(64) . '")};\n@endphp\n<x-cms::block type="${String(node.type).replaceAll('"', '&quot;')}" :payload="$cmsBlockPayload" />` : json;
            try { await navigator.clipboard.writeText(this.dataExport); this.copyStatus = 'Copiado'; } catch { this.copyStatus = 'Selecciona y copia el texto'; }
        },
        insertPreset(preset) {
            const nodes = Array.isArray(preset.nodes) ? preset.nodes : (preset.payload ? [{ type: preset.type, ...preset.payload }] : []);
            if (nodes.some(node => containsUnavailable(node, this.availableTypes))) return;
            this.historyBoundary(); const position = this.insertIndex === null ? this.blocks.length : Math.max(0, Math.min(this.blocks.length, this.insertIndex));
            this.blocks.splice(position, 0, ...nodes.map(node => cloneBlockTree(node, this.availableTypes))); this.insertIndex = null;
            this.syncBlocksToLocale(); if (nodes.length) { this.selectNode([position]); this.inspectorTab = 'content'; } this.closeAddModal();
        },
        mountRootSortable(host) {
            if (sortableLists.has(host)) return;
            const manager = this;
            attachSortable(host, { get nodes() { return manager.blocks; }, manager, render: () => {}, root: true }, rootCleanups).catch(() => {});
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
